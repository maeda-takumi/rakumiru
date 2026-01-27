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
?>

<div class="card">
  <div class="card__head">
    <h1 class="card__title">売れ筋ランキング取得</h1>
    <p class="card__desc">楽天ランキングAPIから売れ筋商品を取得し、DBに保存します。</p>
  </div>

  <div class="form">
    <div class="row">
      <div class="field">
        <label class="label" for="genreId">ジャンルID（任意）</label>
        <input id="genreId" class="input" type="text" inputmode="numeric" placeholder="例：100371">
      </div>
      <div class="field">
        <label class="label" for="period">期間</label>
        <select id="period" class="input">
          <option value="daily">daily（デイリー）</option>
          <option value="realtime">realtime（リアルタイム）</option>
          <option value="weekly">weekly（ウィークリー）</option>
          <option value="monthly">monthly（マンスリー）</option>
        </select>
      </div>
      <div class="field">
        <label class="label" for="hits">取得件数</label>
        <select id="hits" class="input">
          <option value="10">10</option>
          <option value="20">20</option>
          <option value="30" selected>30</option>
        </select>
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
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>