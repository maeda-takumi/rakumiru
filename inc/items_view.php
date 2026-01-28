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
      <div class="group-items__head">
        <span>商品名</span>
        <span>価格</span>
        <span>順位</span>
        <span>レビュー</span>
        <span>リンク</span>
      </div>
      <?php foreach ($items as $item): ?>
        <?php
          $genreDisplay = '';
          if (!empty($item['last_genre_id'])) {
            $genreDisplay = format_genre_display((string)$item['last_genre_id'], $genreLabels);
          }
        ?>
        <div class="group-items__row">
          <div class="group-items__main">
            <div class="group-items__name"><?= h($item['item_name']) ?></div>
            <?php if (!empty($item['catchcopy'])): ?>
              <div class="group-items__catch"><?= h($item['catchcopy']) ?></div>
            <?php endif; ?>
            <?php if ($genreDisplay !== ''): ?>
              <div class="group-items__genre">ジャンル: <?= h($genreDisplay) ?></div>
            <?php endif; ?>
          </div>
          <div class="group-items__cell"><?= h(number_format((int)$item['item_price'])) ?>円</div>
          <div class="group-items__cell"><?= !empty($item['last_rank']) ? h((string)$item['last_rank']) : '-' ?></div>
          <div class="group-items__cell"><?= h((string)$item['review_average']) ?> (<?= h((string)$item['review_count']) ?>件)</div>
          <div class="group-items__cell group-items__links">
            <?php if (!empty($item['item_url'])): ?>
              <a class="btn btn--ghost" href="<?= h($item['item_url']) ?>" target="_blank" rel="noopener">商品ページ</a>
            <?php endif; ?>
            <?php if (!empty($item['affiliate_url'])): ?>
              <a class="btn btn--ghost" href="<?= h($item['affiliate_url']) ?>" target="_blank" rel="noopener">アフィリURL</a>
            <?php endif; ?>     
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif;
}                   