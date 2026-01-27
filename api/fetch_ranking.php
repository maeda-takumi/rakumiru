<?php
require_once __DIR__ . '/_response.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_ng('Method Not Allowed', 405);
}

$userId = 1;

$genreId = trim($_POST['genre_id'] ?? '');
$genreIds = $_POST['genre_ids'] ?? [];
$period = trim($_POST['period'] ?? 'daily');
$hits = (int)($_POST['hits'] ?? 30);

$normalizedGenreIds = [];
if (is_array($genreIds)) {
    $normalizedGenreIds = $genreIds;
} elseif ($genreIds !== '') {
    $normalizedGenreIds = [$genreIds];
} elseif ($genreId !== '') {
    $normalizedGenreIds = [$genreId];
}

$normalizedGenreIds = array_values(array_unique(array_filter(array_map('trim', $normalizedGenreIds), 'strlen')));
foreach ($normalizedGenreIds as $candidate) {
    if (!preg_match('/^\d+$/', $candidate)) {
        json_ng('genre_id は数字で入力してください');
    }
}

$allowedPeriods = ['realtime', 'daily', 'weekly', 'monthly'];
if (!in_array($period, $allowedPeriods, true)) {
    json_ng('period が不正です');
}

$forcedRealtime = false;
if ($period !== 'realtime') {
    $period = 'realtime';
    $forcedRealtime = true;
}

if ($hits <= 0 || $hits > 30) {
    json_ng('hits は1〜30で指定してください');
}

function http_get_json(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException("通信失敗: {$err}");
    }
    if ($http >= 400) {
        throw new RuntimeException("HTTP {$http}: {$raw}");
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('JSON解析に失敗しました');
    }
    return $json;
}

function pick_image_url(array $item): ?string {
    $sources = $item['mediumImageUrls'] ?? $item['smallImageUrls'] ?? $item['imageUrl'] ?? null;
    if (is_string($sources)) {
        return $sources;
    }
    if (is_array($sources)) {
        $first = $sources[0] ?? null;
        if (is_string($first)) {
            return $first;
        }
        if (is_array($first)) {
            return $first['imageUrl'] ?? null;
        }
    }
    return null;
}

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT value FROM api_keys WHERE user_id = :uid AND provider = :provider LIMIT 1');
    $stmt->execute([':uid' => $userId, ':provider' => 'rakuten_app_id']);
    $rakutenAppId = $stmt->fetchColumn();

    if (!$rakutenAppId) {
        json_ng('楽天AppIDが未保存です');
    }


    $pdo->beginTransaction();
    $upsert = $pdo->prepare("
      INSERT INTO items (
        user_id, item_code, item_name, catchcopy, item_price, item_url, affiliate_url,
        image_url, review_count, review_average, availability, last_rank, last_genre_id,
        last_period, last_fetched_at
      ) VALUES (
        :user_id, :item_code, :item_name, :catchcopy, :item_price, :item_url, :affiliate_url,
        :image_url, :review_count, :review_average, :availability, :last_rank, :last_genre_id,
        :last_period, :last_fetched_at
      )
      ON DUPLICATE KEY UPDATE
        item_name = VALUES(item_name),
        catchcopy = VALUES(catchcopy),
        item_price = VALUES(item_price),
        item_url = VALUES(item_url),
        affiliate_url = VALUES(affiliate_url),
        image_url = VALUES(image_url),
        review_count = VALUES(review_count),
        review_average = VALUES(review_average),
        availability = VALUES(availability),
        last_rank = VALUES(last_rank),
        last_genre_id = VALUES(last_genre_id),
        last_period = VALUES(last_period),
        last_fetched_at = VALUES(last_fetched_at),
        updated_at = CURRENT_TIMESTAMP
    ");

    $count = 0;
    $fetchedAt = now();
    $seen = [];
    $targets = count($normalizedGenreIds) > 0 ? $normalizedGenreIds : [null];
    foreach ($targets as $targetGenreId) {
        $query = [
            'applicationId' => $rakutenAppId,
            'format' => 'json',
            'formatVersion' => 2,
            'page' => 1,
            'hits' => $hits,
            'period' => $period,
        ];
        if ($targetGenreId !== null && $targetGenreId !== '') {
            $query['genreId'] = $targetGenreId;
        }
        $rakutenUrl = 'https://app.rakuten.co.jp/services/api/IchibaItem/Ranking/20220601?' . http_build_query($query);
        $r = http_get_json($rakutenUrl);
        $items = $r['items'] ?? $r['Items'] ?? null;
        if (!is_array($items) || count($items) === 0) {
            $label = $targetGenreId ? "ジャンルID: {$targetGenreId}" : '総合';
            throw new RuntimeException("楽天APIから商品が取得できませんでした（{$label}）");
        }

        foreach ($items as $item) {
            $code = $item['itemCode'] ?? $item['item_code'] ?? '';
            if ($code === '') {
                continue;
            }

            $upsert->execute([
                ':user_id' => $userId,
                ':item_code' => $code,
                ':item_name' => $item['itemName'] ?? '',
                ':catchcopy' => $item['catchcopy'] ?? null,
                ':item_price' => (int)($item['itemPrice'] ?? 0),
                ':item_url' => $item['itemUrl'] ?? null,
                ':affiliate_url' => $item['affiliateUrl'] ?? ($item['itemUrl'] ?? null),
                ':image_url' => pick_image_url($item),
                ':review_count' => (int)($item['reviewCount'] ?? 0),
                ':review_average' => (float)($item['reviewAverage'] ?? 0),
                ':availability' => (int)($item['availability'] ?? 0),
                ':last_rank' => isset($item['rank']) ? (int)$item['rank'] : null,
                ':last_genre_id' => $targetGenreId !== null && $targetGenreId !== '' ? (int)$targetGenreId : null,
                ':last_period' => $period,
                ':last_fetched_at' => $fetchedAt,
            ]);

            if (!isset($seen[$code])) {
                $count++;
                $seen[$code] = true;
            }
        }
    }

    $pdo->commit();
    $genreSummary = count($normalizedGenreIds) > 0 ? count($normalizedGenreIds) . 'ジャンル' : '総合';
    $note = $forcedRealtime ? ' ※期間はリアルタイム固定です' : '';

    json_ok([
        'message' => "{$count}件を保存しました（{$genreSummary}）{$note}",
        'count' => $count,
        'fetched_at' => $fetchedAt,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_ng($e->getMessage(), 400);
}
