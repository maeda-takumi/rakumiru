<?php
require_once __DIR__ . '/functions.php';

function render_items_grid(array $items, array $genreLabels): void {
  if (count($items) === 0): ?>
    <p class="muted">この取得履歴には商品がありません。</p>
  <?php else: ?>
    <div class="items-grid">
      <?php foreach ($items as $item): ?>
        <?php
          $genreDisplay = '';
          if (!empty($item['last_genre_id'])) {
            $ids = array_values(array_filter(array_map('trim', explode(',', (string)$item['last_genre_id'])), 'strlen'));
            $labels = [];
            foreach ($ids as $id) {
              $label = $genreLabels[$id] ?? null;
              if ($label) {
                $labels[] = $label . ' (' . $id . ')';
              } else {
                $labels[] = $id;
              }
            }
            $genreDisplay = implode(' / ', $labels);
          }
        ?>
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
              <?php if ($genreDisplay !== ''): ?>
                <div class="item-card__meta">
                  <span>ジャンル: <?= h($genreDisplay) ?></span>
                </div>
              <?php endif; ?>
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
  <?php endif;
}