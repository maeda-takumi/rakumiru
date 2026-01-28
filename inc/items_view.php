<?php
require_once __DIR__ . '/functions.php';


function format_genre_display(?string $genreIds, array $genreLabels): string {
  if ($genreIds === null || $genreIds === '') {
    return '総合';
  }
  $ids = array_values(array_filter(array_map('trim', explode(',', $genreIds)), 'strlen'));
  if (count($ids) === 0) {
    return '総合';
  }
  $labels = [];
  foreach ($ids as $id) {
    $label = $genreLabels[$id] ?? null;
    $labels[] = $label ? $label . ' (' . $id . ')' : $id;
  }
  return implode(' / ', $labels);
}

function render_group_cards(array $runs, array $runCounts, array $genreLabels): void {
  if (count($runs) === 0): ?>
    <p class="muted">取得グループがありません。</p>
  <?php else: ?>
    <div class="group-grid">
      <?php foreach ($runs as $index => $run): ?>
        <?php
          $runId = (int)$run['id'];
          $label = '取得' . ($index + 1);
          $genreDisplay = format_genre_display($run['genre_id'] ?? null, $genreLabels);
          $count = $runCounts[$runId] ?? 0;
          $period = $run['period'] ?? '';
        ?>
        <button
          class="group-card"
          type="button"
          data-run-id="<?= h((string)$runId) ?>"
          data-run-label="<?= h($label) ?>"
          data-run-date="<?= h((string)($run['fetched_at'] ?? '')) ?>"
          data-run-genre="<?= h($genreDisplay) ?>"
          data-run-count="<?= h((string)$count) ?>"
        >
          <div class="group-card__head">
            <div class="group-card__title"><?= h($label) ?></div>
            <div class="group-card__date"><?= h((string)($run['fetched_at'] ?? '')) ?></div>
          </div>
          <div class="group-card__meta">
            <span>ジャンル: <?= h($genreDisplay) ?></span>
            <span>件数: <?= h((string)$count) ?>件</span>
            <?php if ($period !== ''): ?>
              <span>期間: <?= h((string)$period) ?></span>
            <?php endif; ?>
          </div>
          <div class="group-card__action">詳細を見る</div>
        </button>
      <?php endforeach; ?>
    </div>
  <?php endif;
}

function render_group_items_list(array $items, array $genreLabels): void {
    if (count($items) === 0): ?>
    <p class="muted">この取得グループには商品がありません。</p>
  <?php else: ?>
    <div class="group-items">
      <?php foreach ($items as $item): ?>
        <?php
          $genreDisplay = '';
          if (!empty($item['last_genre_id'])) {
            $genreDisplay = format_genre_display((string)$item['last_genre_id'], $genreLabels);
          }
          $imageUrl = $item['image_url'] ?? '';
        ?>
        <article class="group-item">
          <div class="group-item__media">
            <?php if (!empty($imageUrl)): ?>
              <img src="<?= h($imageUrl) ?>" alt="<?= h($item['item_name']) ?>">
            <?php else: ?>
              <span class="group-item__placeholder">画像なし</span>
            <?php endif; ?>
          </div>
          <div class="group-item__content">
            <div class="group-item__name"><?= h($item['item_name']) ?></div>
            <?php if (!empty($item['catchcopy'])): ?>
              <div class="group-item__catch"><?= h($item['catchcopy']) ?></div>
            <?php endif; ?>
            <?php if ($genreDisplay !== ''): ?>
              <div class="group-item__genre">ジャンル: <?= h($genreDisplay) ?></div>
            <?php endif; ?>
            <div class="group-item__meta">
              <span><?= h(number_format((int)$item['item_price'])) ?>円</span>
              <span>順位: <?= !empty($item['last_rank']) ? h((string)$item['last_rank']) : '-' ?></span>
              <span>レビュー: <?= h((string)$item['review_average']) ?> (<?= h((string)$item['review_count']) ?>件)</span>
            </div>
            <div class="group-item__actions">
              <?php if (!empty($item['item_url'])): ?>
                <a class="group-item__cta" href="<?= h($item['item_url']) ?>" target="_blank" rel="noopener">商品ページへ</a>
              <?php endif; ?>
              <?php if (!empty($item['affiliate_url'])): ?>
                <a class="group-item__link" href="<?= h($item['affiliate_url']) ?>" target="_blank" rel="noopener">アフィリURL</a>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif;
}                