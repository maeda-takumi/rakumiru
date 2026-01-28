<?php
/**
 * sync_genre_children.php
 * 親ジャンル( DB ) -> IchibaGenre/Search で children だけ取得 -> rakuten_genre_children に保存
 *
 * WEB:
 *   /tools/sync_genre_children.php
 *   /tools/sync_genre_children.php?parent=100371
 *   /tools/sync_genre_children.php?sleep=0
 *
 * CLI:
 *   php tools/sync_genre_children.php
 *   php tools/sync_genre_children.php --parent=100371 --sleep=0
 */

declare(strict_types=1);
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../inc/db.php';

// ===== 設定 =====
const RAKUTEN_GENRE_ENDPOINT = 'https://app.rakuten.co.jp/services/api/IchibaGenre/Search/20140222';
const RAKUTEN_APP_ID = '1025854062340321330';

$PARENT_TABLE = 'genre'; // 親ジャンルが入っているテーブル
$CHILD_TABLE  = 'rakuten_genre_children'; // 子ジャンル保存先

// ===== オプション =====
$sleepMs = 50;

// CLI args
if (PHP_SAPI === 'cli') {
  foreach ($argv ?? [] as $a) {
    if (preg_match('/^--sleep=(\d+)$/', $a, $m))  $sleepMs = (int)$m[1];
    if (preg_match('/^--parent=(\d+)$/', $a, $m)) $_GET['parent'] = $m[1];
  }
} else {
  header('Content-Type: text/plain; charset=UTF-8');
  if (isset($_GET['sleep'])) $sleepMs = (int)$_GET['sleep'];
}

$appId = (string)RAKUTEN_APP_ID;
if ($appId === '') {
  echo "ERROR: RAKUTEN_APP_ID is empty.\n";
  exit(1);
}

// ===== HTTP =====
function http_get_json(string $url, array $params): array {
  $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
  $full = $url . '?' . $qs;

  $ch = curl_init($full);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'Rakumiru/GenreChildrenSync',
  ]);

  $body = curl_exec($ch);
  $errno = curl_errno($ch);
  $err   = curl_error($ch);
  $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($errno !== 0) throw new RuntimeException("cURL error: {$err} ({$errno})");
  if ($code < 200 || $code >= 300) throw new RuntimeException("HTTP {$code}: " . substr((string)$body, 0, 400));

  $json = json_decode((string)$body, true);
  if (!is_array($json)) throw new RuntimeException("Invalid JSON: " . substr((string)$body, 0, 400));
  return $json;
}

function fetch_children_only(string $appId, int $genreId): array {
  $res = http_get_json(RAKUTEN_GENRE_ENDPOINT, [
    'applicationId' => $appId,
    'genreId'       => (string)$genreId,
    'format'        => 'json',
  ]);

  $children = $res['children'] ?? [];
  if (!is_array($children)) return [];

  $out = [];
  foreach ($children as $c) {
    $info = $c['child'] ?? null;
    if (!is_array($info)) continue;

    $out[] = [
      'child_genre_id'    => (int)($info['genreId'] ?? 0),
      'child_genre_name'  => (string)($info['genreName'] ?? ''),
      'child_genre_level' => isset($info['genreLevel']) ? (int)$info['genreLevel'] : null,
      'child_genre_path'  => isset($info['genrePath'])  ? (string)$info['genrePath']  : null,
    ];
  }
  return $out;
}

// ===== DB =====
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 親ジャンル取得（特定parent指定があればそれだけ）
$parents = [];
if (!empty($_GET['parent'])) {
  $parents = [(int)$_GET['parent']];
} else {
  $parents = $pdo->query("SELECT genre_id FROM {$PARENT_TABLE} ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
  $parents = array_map('intval', $parents);
}

if (!$parents) {
  echo "ERROR: 親ジャンルが取得できませんでした。({$PARENT_TABLE}.genre_id)\n";
  exit(1);
}

// upsert（親×子の組み合わせで一意）
$upsert = $pdo->prepare("
  INSERT INTO {$CHILD_TABLE}
    (parent_genre_id, child_genre_id, child_genre_name, child_genre_level, child_genre_path)
  VALUES
    (:parent_genre_id, :child_genre_id, :child_genre_name, :child_genre_level, :child_genre_path)
  ON DUPLICATE KEY UPDATE
    child_genre_name  = VALUES(child_genre_name),
    child_genre_level = VALUES(child_genre_level),
    child_genre_path  = VALUES(child_genre_path)
");

// 実行
echo "== Sync children start ==\n";
echo "Parents: " . count($parents) . "\n";
echo "sleepMs={$sleepMs}\n\n";

foreach ($parents as $pid) {
  if ($pid <= 0) continue;

  echo "## Parent genre_id: {$pid}\n";

  try {
    $children = fetch_children_only($appId, $pid);
    echo "children: " . count($children) . "\n";

    foreach ($children as $node) {
      $cid = $node['child_genre_id'];
      if ($cid <= 0) continue;

      echo "- {$node['child_genre_name']} ({$cid})\n";

      $upsert->execute([
        ':parent_genre_id'   => $pid,
        ':child_genre_id'    => $cid,
        ':child_genre_name'  => $node['child_genre_name'],
        ':child_genre_level' => $node['child_genre_level'],
        ':child_genre_path'  => $node['child_genre_path'],
      ]);
    }

    echo "\n";
    if ($sleepMs > 0) usleep($sleepMs * 1000);

  } catch (Throwable $e) {
    echo "ERROR parent={$pid}: " . $e->getMessage() . "\n\n";
  }
}

echo "== Sync children done ==\n";
