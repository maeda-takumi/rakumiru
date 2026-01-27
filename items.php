<?php
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/header.php';

$userId = 1;
$keyword = trim($_GET['q'] ?? '');
$order = $_GET['order'] ?? 'rank';

$orderSql = 'last_rank ASC, updated_at DESC';
if ($order === 'new') {
  $orderSql = 'updated_at DESC';
}

$pdo = db();
$maxFetched = $pdo->prepare('SELECT MAX(last_fetched_at) FROM items WHERE user_id = :uid');
$maxFetched->execute([':uid' => $userId]);
$lastFetchedAt = $maxFetched->fetchColumn();

$itemsStmt = $pdo->prepare("
  SELECT *
  FROM items
  WHERE user_id = :uid
    AND (:q = '' OR item_name LIKE :q_like_name OR catchcopy LIKE :q_like_catch)
  ORDER BY {$orderSql}
  LIMIT 200
");
$itemsStmt->execute([
  ':uid' => $userId,
  ':q' => $keyword,
  ':q_like_name' => '%' . $keyword . '%',
  ':q_like_catch' => '%' . $keyword . '%',
]);
$items = $itemsStmt->fetchAll();

$genreOptions = [
  ['id' => '100371', 'label' => 'レディースファッション'],
  ['id' => '100433', 'label' => 'メンズファッション'],
  ['id' => '100804', 'label' => 'インテリア・寝具・収納'],
  ['id' => '558944', 'label' => 'キッチン用品・食器'],
  ['id' => '100026', 'label' => '家電'],
  ['id' => '100227', 'label' => '食品'],
  ['id' => '100939', 'label' => '美容・コスメ・香水'],
  ['id' => '101070', 'label' => 'スポーツ・アウトドア'],
  ['id' => '101164', 'label' => 'おもちゃ'],
];
?>

<div class="card">
  <div class="card__head">
    <h1 class="card__title">売れ筋ランキング取得</h1>
    <p class="card__desc">楽天ランキングAPIから売れ筋商品を取得し、DBに保存します。</p>
  </div>

  <div class="form">
    <div class="row">
      <div class="field field--full">
        <label class="label">ジャンル（複数選択可）</label>
        <div class="genre-select" id="genreSelect">
          <?php foreach ($genreOptions as $genre): ?>
            <label class="genre-option">
              <input type="checkbox" name="genre_ids[]" value="<?= h($genre['id']) ?>" data-label="<?= h($genre['label']) ?>">
              <span><?= h($genre['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="genre-selected" id="genreSelected">
          <span class="muted">未選択（総合ランキング）</span>
        </div>
        <button id="btnClearGenres" class="btn btn--ghost" type="button">選択クリア</button>
      </div>
    </div>
    <div class="row">
      <div class="field">
        <span class="label">期間</span>
        <div class="input">realtime</div>
      </div>
      <div class="field">
        <span class="label">取得件数</span>
        <div class="input">30件</div>
      </div>
    </div>
    <div class="row">
      <button id="btnFetchRanking" class="btn btn--primary" type="button">ランキングを取得して保存</button>
      <div class="muted">最後の取得: <?= $lastFetchedAt ? h($lastFetchedAt) : '未取得' ?></div>
    </div>

    <div id="fetchStatus" class="status muted"></div>
  </div>
</div>

<div class="card">
  <div class="card__head">
    <h2 class="card__title">商品一覧</h2>
    <p class="card__desc">保存済みの商品を表示します（最大200件）。</p>
  </div>

  <form class="row" method="get">
    <input class="input" type="text" name="q" placeholder="キーワード検索" value="<?= h($keyword) ?>">
    <select class="input" name="order">
      <option value="rank" <?= $order === 'rank' ? 'selected' : '' ?>>ランキング順</option>
      <option value="new" <?= $order === 'new' ? 'selected' : '' ?>>新着順</option>
    </select>
    <button class="btn" type="submit">検索</button>
  </form>

  <?php if (count($items) === 0): ?>
    <p class="muted">まだ商品がありません。上の「ランキングを取得して保存」を実行してください。</p>
  <?php else: ?>
    <div class="items-grid">
      <?php foreach ($items as $item): ?>
        <article class="item-card">
          <div class="item-card__image">
            <?php if (!empty($item['image_url'])): ?>
              <img src="<?= h($item['image_url']) ?>" alt="<?= h($item['item_name']) ?>">
            <?php else: ?>
              <div class="item-card__placeholder">No Image</div>
            <?php endif; ?>
          </div>
          <div class="item-card__body">
            <div class="item-card__title"><?= h($item['item_name']) ?></div>
            <button class="btn btn--ghost item-card__toggle" type="button" aria-expanded="false">開く</button>
            <div class="item-card__details" hidden>
              <?php if (!empty($item['catchcopy'])): ?>
                <div class="item-card__catch"><?= h($item['catchcopy']) ?></div>
              <?php endif; ?>
              <div class="item-card__meta">
                <span>価格: <?= h(number_format((int)$item['item_price'])) ?>円</span>
                <?php if (!empty($item['last_rank'])): ?>
                  <span>順位: <?= h((string)$item['last_rank']) ?></span>
                <?php endif; ?>
              </div>
              <div class="item-card__meta">
                <span>レビュー: <?= h((string)$item['review_average']) ?> (<?= h((string)$item['review_count']) ?>件)</span>
                <?php if (!empty($item['last_fetched_at'])): ?>
                  <span>取得: <?= h($item['last_fetched_at']) ?></span>
                <?php endif; ?>
              </div>
              <div class="item-card__actions">
                <?php if (!empty($item['item_url'])): ?>
                  <a class="btn" href="<?= h($item['item_url']) ?>" target="_blank" rel="noopener">商品ページ</a>
                <?php endif; ?>
                <?php if (!empty($item['affiliate_url'])): ?>
                  <a class="btn" href="<?= h($item['affiliate_url']) ?>" target="_blank" rel="noopener">アフィリURL</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>