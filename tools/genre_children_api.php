<?php
declare(strict_types=1);
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json; charset=UTF-8');

$parent = (int)($_GET['parent'] ?? 0);
if ($parent <= 0) {
  echo json_encode(['ok' => false, 'items' => [], 'error' => 'parent is required'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $pdo = db();
  $stmt = $pdo->prepare("
    SELECT child_genre_id, child_genre_name
    FROM rakuten_genre_children
    WHERE parent_genre_id = :p
    ORDER BY child_genre_name ASC
  ");
  $stmt->execute([':p' => $parent]);

  $items = [];
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $items[] = [
      'id' => (string)$r['child_genre_id'],
      'label' => (string)$r['child_genre_name'],
    ];
  }

  echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'items' => [], 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
