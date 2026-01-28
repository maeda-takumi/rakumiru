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
$period = trim($_POST['period'] ?? 'realtime');
$hits = 30;

$normalizedGenreIds = [];
if (isset($_POST['genre_ids'])) {
    if (is_array($genreIds) && count($genreIds) > 0) {
        $normalizedGenreIds = $genreIds;
    } elseif ($genreIds !== '') {
        $normalizedGenreIds = [$genreIds];
    } elseif ($genreId !== '') {
        $normalizedGenreIds = [$genreId];
    }
} elseif ($genreId !== '') {
    $normalizedGenreIds = [$genreId];
}

$normalizedGenreIds = array_values(array_unique(array_filter(array_map('trim', $normalizedGenreIds), 'strlen')));
foreach ($normalizedGenreIds as $candidate) {
    if (!preg_match('/^\d+$/', $candidate)) {
        json_ng('genre_id は数字で入力してください');
    }
}

$period = $period === '' ? 'realtime' : $period;
$allowedPeriods = ['realtime', 'daily'];
if (!in_array($period, $allowedPeriods, true)) {
    json_ng('period が不正です');
}


if (isset($_POST['hits']) && (int)$_POST['hits'] !== $hits) {
    json_ng('hits は30のみ対応しています');
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

function fetch_item_genre_id(string $appId, string $itemCode): ?string {
    if ($itemCode === '') {
        return null;
    }

    static $lastRequestAt = 0.0;
    $now = microtime(true);
    $elapsed = $now - $lastRequestAt;
    if ($elapsed < 1.05) {
        usleep((int)((1.05 - $elapsed) * 1000000));
    }
    $lastRequestAt = microtime(true);

    $query = [
        'applicationId' => $appId,
        'format' => 'json',
        'formatVersion' => 2,
        'itemCode' => $itemCode,
        'hits' => 1,
        'elements' => 'genreId',
    ];
    $url = 'https://app.rakuten.co.jp/services/api/IchibaItem/Search/20220601?' . http_build_query($query);

    try {
        $response = http_get_json($url);
    } catch (Throwable $e) {
        return null;
    }

    $items = $response['items'] ?? $response['Items'] ?? null;
    if (!is_array($items) || count($items) === 0) {
        return null;
    }

    $genreId = $items[0]['genreId'] ?? null;
    if ($genreId === null || $genreId === '') {
        return null;
    }

    return (string)$genreId;
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

    $fetchedAt = now();
    $genreValueForRun = count($normalizedGenreIds) > 0 ? implode(',', $normalizedGenreIds) : null;

    $runInsert = $pdo->prepare("
      INSERT INTO fetch_runs (user_id, fetched_at, period, genre_id)
      VALUES (:user_id, :fetched_at, :period, :genre_id)
    ");
    $runInsert->execute([
      ':user_id' => $userId,
      ':fetched_at' => $fetchedAt,
      ':period' => $period,
      ':genre_id' => $genreValueForRun,
    ]);
    $fetchRunId = (int)$pdo->lastInsertId();

    $itemsByCode = [];
    $genreMap = [];
    $targets = count($normalizedGenreIds) > 0 ? $normalizedGenreIds : [null];
    foreach ($targets as $targetGenreId) {
        $query = [
            'applicationId' => $rakutenAppId,
            'format' => 'json',
            'formatVersion' => 2,
            'page' => 1,
            'hits' => $hits,
        ];
        if ($period === 'realtime') {
            $query['period'] = 'realtime';
        }
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

            $itemsByCode[$code] = [
                'item_code' => $code,
                'item_name' => $item['itemName'] ?? '',
                'catchcopy' => $item['catchcopy'] ?? null,
                'item_price' => (int)($item['itemPrice'] ?? 0),
                'item_url' => $item['itemUrl'] ?? null,
                'affiliate_url' => $item['affiliateUrl'] ?? ($item['itemUrl'] ?? null),
                'image_url' => pick_image_url($item),
                'review_count' => (int)($item['reviewCount'] ?? 0),
                'review_average' => (float)($item['reviewAverage'] ?? 0),
                'availability' => (int)($item['availability'] ?? 0),
                'last_rank' => isset($item['rank']) ? (int)$item['rank'] : null,
            ];

            if ($targetGenreId !== null && $targetGenreId !== '') {
                $genreMap[$code][$targetGenreId] = true;
            }
        }
    }
    $itemIdStmt = $pdo->prepare('SELECT id FROM items WHERE user_id = :uid AND item_code = :item_code LIMIT 1');
    $runItemInsert = $pdo->prepare("
      INSERT INTO fetch_run_items (fetch_run_id, item_id, rank)
      VALUES (:fetch_run_id, :item_id, :rank)
      ON DUPLICATE KEY UPDATE rank = VALUES(rank)
    ");
    foreach ($itemsByCode as $code => $data) {
        $orderedGenreIds = [];
        foreach ($normalizedGenreIds as $genreId) {
            if (isset($genreMap[$code][$genreId])) {
                $orderedGenreIds[] = $genreId;
            }
        }
        if (empty($orderedGenreIds)) {
            $orderedGenreIds = array_keys($genreMap[$code] ?? []);
        }
        $genreValue = count($orderedGenreIds) > 0 ? implode(',', $orderedGenreIds) : null;

        $searchGenreId = fetch_item_genre_id($rakutenAppId, $code);
        if ($searchGenreId !== null && $searchGenreId !== '') {
            $genreValue = $searchGenreId;
        }
        $upsert->execute([
            ':user_id' => $userId,
            ':item_code' => $data['item_code'],
            ':item_name' => $data['item_name'],
            ':catchcopy' => $data['catchcopy'],
            ':item_price' => $data['item_price'],
            ':item_url' => $data['item_url'],
            ':affiliate_url' => $data['affiliate_url'],
            ':image_url' => $data['image_url'],
            ':review_count' => $data['review_count'],
            ':review_average' => $data['review_average'],
            ':availability' => $data['availability'],
            ':last_rank' => $data['last_rank'],
            ':last_genre_id' => $genreValue,
            ':last_period' => $period,
            ':last_fetched_at' => $fetchedAt,
        ]);
        $itemIdStmt->execute([
          ':uid' => $userId,
          ':item_code' => $data['item_code'],
        ]);
        $itemId = $itemIdStmt->fetchColumn();
        if ($itemId) {
          $runItemInsert->execute([
            ':fetch_run_id' => $fetchRunId,
            ':item_id' => (int)$itemId,
            ':rank' => $data['last_rank'],
          ]);
        }
    }

    $cleanupStmt = $pdo->prepare("
      DELETE fri
      FROM fetch_run_items fri
      JOIN fetch_runs fr ON fr.id = fri.fetch_run_id
      WHERE fr.user_id = :uid
        AND fr.id NOT IN (
          SELECT id FROM (
            SELECT id FROM fetch_runs
            WHERE user_id = :uid2
            ORDER BY fetched_at DESC
            LIMIT 5
          ) AS keep_runs
        )
    ");
    $cleanupStmt->execute([':uid' => $userId, ':uid2' => $userId]);

    $cleanupRunsStmt = $pdo->prepare("
      DELETE FROM fetch_runs
      WHERE user_id = :uid
        AND id NOT IN (
          SELECT id FROM (
            SELECT id FROM fetch_runs
            WHERE user_id = :uid2
            ORDER BY fetched_at DESC
            LIMIT 5
          ) AS keep_runs
        )
    ");
    $cleanupRunsStmt->execute([':uid' => $userId, ':uid2' => $userId]);

    $count = count($itemsByCode);
    $pdo->commit();
    $genreSummary = count($normalizedGenreIds) > 0 ? count($normalizedGenreIds) . 'ジャンル' : '総合';

    json_ok([
        'message' => "{$count}件を保存しました（{$genreSummary} / {$period}）",
        'count' => $count,
        'fetched_at' => $fetchedAt,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_ng($e->getMessage(), 400);
}
