<?php
require_once __DIR__ . '/_response.php';
require_once __DIR__ . '/../inc/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_ng('Method Not Allowed', 405);

$rakutenAppId = trim($_POST['rakuten_app_id'] ?? '');
$geminiKey    = trim($_POST['gemini_api_key'] ?? '');

if ($rakutenAppId === '' || $geminiKey === '') json_ng('両方入力してください');
if (!preg_match('/^\d+$/', $rakutenAppId)) json_ng('楽天アプリIDは数字で入力してください');

$userId = 1;

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

    if ($raw === false) throw new RuntimeException("通信失敗: {$err}");
    if ($http >= 400) throw new RuntimeException("HTTP {$http}: {$raw}");
    $json = json_decode($raw, true);
    if (!is_array($json)) throw new RuntimeException("JSON解析に失敗しました");
    return $json;
}

function http_post_json(string $url, array $body): array {
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
    if ($payload === false) throw new RuntimeException("JSON生成に失敗しました");

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
    ]);
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) throw new RuntimeException("通信失敗: {$err}");
    if ($http >= 400) throw new RuntimeException("HTTP {$http}: {$raw}");
    $json = json_decode($raw, true);
    if (!is_array($json)) throw new RuntimeException("JSON解析に失敗しました");
    return $json;
}

function hint(string $s): string {
    $s = trim($s);
    if ($s === '') return '****';
    $len = strlen($s);
    $tail = ($len >= 4) ? substr($s, -4) : $s;
    return '****' . $tail;
}

/**
 * テキストが返ればOK、という前提で「通りやすい候補モデル」を上から順に試し、
 * 最初に成功したモデル名と返答テキストを返す。
 */
function pick_and_test_gemini_model(string $geminiKey): array {
    $candidates = [
        'models/gemini-2.0-flash',
        'models/gemini-2.0-flash-lite',
        'models/gemini-2.5-flash-lite',
        'models/gemini-2.5-flash',
        'models/gemini-2.5-pro',
        'models/gemini-1.5-flash',
        'models/gemini-1.5-pro',
    ];

    $lastErr = null;

    foreach ($candidates as $model) {
        try {
            $res = http_post_json(
                "https://generativelanguage.googleapis.com/v1beta/{$model}:generateContent?key=" . rawurlencode($geminiKey),
                [
                    "contents" => [[
                        "parts" => [[ "text" => "OKとだけ返して" ]]
                    ]]
                ]
            );

            $text = $res['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $text = is_string($text) ? trim($text) : '';

            if ($text !== '') {
                return [$model, $text];
            }

            $lastErr = "Empty response from {$model}";
        } catch (Throwable $e) {
            $lastErr = $e->getMessage();
            continue;
        }
    }

    throw new RuntimeException("Gemini APIテスト失敗（候補モデルが全て失敗）: " . ($lastErr ?? 'unknown'));
}

try {
    // ===== Rakuten test (Ranking) =====
    $rakutenUrl =
        "https://app.rakuten.co.jp/services/api/IchibaItem/Ranking/20220601" .
        "?applicationId=" . rawurlencode($rakutenAppId) .
        "&format=json&formatVersion=2&page=1";

    $r = http_get_json($rakutenUrl);
    $items = $r['items'] ?? $r['Items'] ?? null;
    if (!is_array($items) || count($items) === 0) {
        throw new RuntimeException("楽天APIテスト失敗（ランキングを取得できませんでした）");
    }
    $rakutenSample = $items[0]['itemName'] ?? '(sample)';

    // ===== Gemini test =====
    [$picked, $geminiText] = pick_and_test_gemini_model($geminiKey);

    // ===== Save to DB =====
    $pdo = db();
    $stmt = $pdo->prepare("
      INSERT INTO api_keys (user_id, provider, value, key_hint)
      VALUES (:user_id, :provider, :value, :key_hint)
      ON DUPLICATE KEY UPDATE
        value=VALUES(value),
        key_hint=VALUES(key_hint),
        updated_at=CURRENT_TIMESTAMP
    ");

    $stmt->execute([
        ':user_id' => $userId,
        ':provider' => 'rakuten_app_id',
        ':value' => $rakutenAppId,
        ':key_hint' => hint($rakutenAppId),
    ]);

    $stmt->execute([
        ':user_id' => $userId,
        ':provider' => 'gemini',
        ':value' => $geminiKey,
        ':key_hint' => hint($geminiKey),
    ]);

    json_ok([
        'message' => 'テストOK。保存しました。',
        'rakuten_sample' => $rakutenSample,
        'gemini_sample' => mb_substr($geminiText, 0, 30),
        'model' => $picked
    ]);

} catch (Throwable $e) {
    json_ng($e->getMessage(), 400);
}
