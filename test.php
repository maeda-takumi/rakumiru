<?php
/**
 * Rakuten Ichiba Genre Tree Dumper (recursive)
 * - Echo children tree as indented text
 *
 * Usage (CLI):
 *   php genre_tree.php YOUR_APP_ID 0
 *
 * Usage (Browser):
 *   genre_tree.php?appId=YOUR_APP_ID&genreId=0
 */

declare(strict_types=1);
mb_internal_encoding('UTF-8');

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function http_get_json(string $url, array $params): array {
  $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
  $full = $url . '?' . $qs;

  $ch = curl_init($full);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'RakutenGenreTreeDumper/1.0',
  ]);

  $body = curl_exec($ch);
  $errno = curl_errno($ch);
  $err   = curl_error($ch);
  $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($errno !== 0) {
    throw new RuntimeException("cURL error: {$err} ({$errno})");
  }
  if ($code < 200 || $code >= 300) {
    throw new RuntimeException("HTTP {$code}: " . substr((string)$body, 0, 300));
  }

  $json = json_decode((string)$body, true);
  if (!is_array($json)) {
    throw new RuntimeException("Invalid JSON response: " . substr((string)$body, 0, 300));
  }
  return $json;
}

/**
 * Fetch children list for a genreId.
 * Returns array of children nodes: [ ['genreId'=>..., 'genreName'=>..., 'genreLevel'=>..., 'genrePath'=>...] ... ]
 */
function fetch_children(string $appId, int $genreId): array {
  $endpoint = 'https://app.rakuten.co.jp/services/api/IchibaGenre/Search/20140222';

  $res = http_get_json($endpoint, [
    'applicationId' => $appId,
    'genreId'       => (string)$genreId,
    'format'        => 'json',
  ]);

  // children may be absent if leaf
  $children = $res['children'] ?? [];
  if (!is_array($children)) return [];

  $out = [];
  foreach ($children as $c) {
    if (!is_array($c)) continue;
    $info = $c['child'] ?? null;
    if (!is_array($info)) continue;

    $out[] = [
      'genreId'    => (int)($info['genreId'] ?? 0),
      'genreName'  => (string)($info['genreName'] ?? ''),
      'genreLevel' => (int)($info['genreLevel'] ?? 0),
      'genrePath'  => (string)($info['genrePath'] ?? ''),
    ];
  }
  return $out;
}

/**
 * Recursively dump tree.
 */
function dump_tree(string $appId, int $genreId, int $depth = 0, int $maxDepth = 6, array &$visited = []): void {
  if ($depth > $maxDepth) return;

  $children = fetch_children($appId, $genreId);

  foreach ($children as $node) {
    $cid = $node['genreId'];

    // cycle guard (usually not needed, but safe)
    if (isset($visited[$cid])) continue;
    $visited[$cid] = true;

    $indent = str_repeat('  ', $depth);
    echo $indent . "- {$node['genreName']} ({$cid})\n";

    // polite pacing (avoid burst)
    usleep(150000); // 0.15s

    dump_tree($appId, $cid, $depth + 1, $maxDepth, $visited);
  }
}

// -------- entry --------
$appId = '';
$genreId = 0;
$maxDepth = 6;

// CLI args > GET params
if (PHP_SAPI === 'cli') {
  $appId = (string)($argv[1] ?? '');
  $genreId = (int)($argv[2] ?? 0);
  $maxDepth = (int)($argv[3] ?? 6);
} else {
  header('Content-Type: text/plain; charset=UTF-8');
  $appId = (string)($_GET['appId'] ?? '');
  $genreId = (int)($_GET['genreId'] ?? 0);
  $maxDepth = (int)($_GET['maxDepth'] ?? 6);
}

if ($appId === '') {
  echo "ERROR: applicationId(appId) is required.\n";
  echo "CLI: php genre_tree.php YOUR_APP_ID 0 [maxDepth]\n";
  echo "WEB: ?appId=YOUR_APP_ID&genreId=0&maxDepth=6\n";
  exit(1);
}

try {
  echo "Root genreId: {$genreId}\n";
  echo "maxDepth: {$maxDepth}\n\n";

  $visited = [$genreId => true];
  dump_tree($appId, $genreId, 0, $maxDepth, $visited);

} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  exit(1);
}
