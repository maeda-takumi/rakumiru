<?php
require_once __DIR__ . '/_response.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/items_view.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_ng('Method Not Allowed', 405);
}

$userId = 1;
$runId = isset($_GET['run_id']) ? (int)$_GET['run_id'] : 0;

if ($runId <= 0) {
    json_ng('run_id が不正です');
}


try {
    $pdo = db();
    $runCheck = $pdo->prepare('SELECT id FROM fetch_runs WHERE id = :id AND user_id = :uid');
    $runCheck->execute([':id' => $runId, ':uid' => $userId]);
    if (!$runCheck->fetchColumn()) {
        json_ng('取得履歴が見つかりません');
    }

    $genreOptions = [
      ['id' => '100371', 'label' => 'レディースファッション'],
      ['id' => '551177', 'label' => 'メンズファッション'],
      ['id' => '100433', 'label' => 'インナー・下着・ナイトウェア'],
      ['id' => '216131', 'label' => 'バッグ・小物・ブランド雑貨'],
      ['id' => '558885', 'label' => '靴'],
      ['id' => '558929', 'label' => '腕時計'],
      ['id' => '216129', 'label' => 'ジュエリー・アクセサリー'],
      ['id' => '100533', 'label' => 'キッズ・ベビー・マタニティ'],
      ['id' => '566382', 'label' => 'おもちゃ'],
      ['id' => '101070', 'label' => 'スポーツ・アウトドア'],
      ['id' => '562637', 'label' => '家電'],
      ['id' => '211742', 'label' => 'TV・オーディオ・カメラ'],
      ['id' => '100026', 'label' => 'パソコン・周辺機器'],
      ['id' => '564500', 'label' => 'スマートフォン・タブレット'],
      ['id' => '565004', 'label' => '光回線・モバイル通信'],
      ['id' => '100227', 'label' => '食品'],
      ['id' => '551167', 'label' => 'スイーツ・お菓子'],
      ['id' => '100316', 'label' => '水・ソフトドリンク'],
      ['id' => '510915', 'label' => 'ビール・洋酒'],
      ['id' => '510901', 'label' => '日本酒・焼酎'],
      ['id' => '100804', 'label' => 'インテリア・寝具・収納'],
      ['id' => '215783', 'label' => '日用品雑貨・文房具・手芸'],
      ['id' => '558944', 'label' => 'キッチン用品・食器・調理器具'],
      ['id' => '200162', 'label' => '本・雑誌・コミック'],
      ['id' => '101240', 'label' => 'CD・DVD'],
      ['id' => '101205', 'label' => 'テレビゲーム'],
      ['id' => '101164', 'label' => 'ホビー'],
      ['id' => '112493', 'label' => '楽器・音響機器'],
      ['id' => '101114', 'label' => '車・バイク'],
      ['id' => '503190', 'label' => '車用品・バイク用品'],
      ['id' => '100939', 'label' => '美容・コスメ・香水'],
      ['id' => '100938', 'label' => 'ダイエット・健康'],
      ['id' => '551169', 'label' => '医薬品・コンタクト・介護'],
      ['id' => '101213', 'label' => 'ペット・ペットグッズ'],
      ['id' => '100005', 'label' => '花・ガーデン・DIY'],
      ['id' => '101438', 'label' => 'サービス・リフォーム'],
      ['id' => '111427', 'label' => '住宅・不動産'],
      ['id' => '101381', 'label' => 'カタログギフト・チケット'],
      ['id' => '100000', 'label' => '百貨店・総合通販・ギフト'],
    ];
    $genreLabels = [];
    foreach ($genreOptions as $genreOption) {
      $genreLabels[$genreOption['id']] = $genreOption['label'];
    }

    $itemsStmt = $pdo->prepare("
      SELECT
        i.*,
        fri.rank AS last_rank,
        fr.fetched_at AS last_fetched_at
      FROM fetch_run_items fri
      JOIN items i ON i.id = fri.item_id
      JOIN fetch_runs fr ON fr.id = fri.fetch_run_id
      WHERE fri.fetch_run_id = :run_id
        AND i.user_id = :uid
      ORDER BY fri.rank ASC, i.updated_at DESC
      LIMIT 200
    ");
    $itemsStmt->execute([
        ':run_id' => $runId,
        ':uid' => $userId,
    ]);
    $items = $itemsStmt->fetchAll();

    ob_start();
    render_group_items_list($items, $genreLabels);
    $html = ob_get_clean();

    json_ok([
        'html' => $html,
        'count' => count($items),
    ]);
} catch (Throwable $e) {
    json_ng($e->getMessage(), 400);
}