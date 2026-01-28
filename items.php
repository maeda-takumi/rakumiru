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
?>

<style>
  /* ===== モーダル（最低限の内製） ===== */
  .modal-overlay[hidden]{ display:none; }
  .modal-overlay{
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 16px;
  }
  .modal{
    width: min(680px, 100%);
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 18px 60px rgba(0,0,0,.25);
    overflow: hidden;
  }
  .modal__head{
    padding: 14px 16px;
    border-bottom: 1px solid rgba(0,0,0,.08);
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
  }
  .modal__title{
    font-size: 16px;
    font-weight: 700;
    margin: 0;
  }
  .modal__body{
    padding: 12px 16px 16px;
    max-height: min(70vh, 520px);
    overflow: auto;
  }
  .modal__foot{
    padding: 12px 16px;
    border-top: 1px solid rgba(0,0,0,.08);
    display:flex;
    justify-content:flex-end;
    gap:10px;
  }
  .genre-grid{
    display:grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px 12px;
  }
  .genre-stack{
    display:flex;
    flex-direction: column;
    gap: 12px;
  }
  .genre-parent{
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .genre-children{
    padding-left: 18px;
    display: none;
  }
  .genre-children.is-open{
    display: block;
  }
  @media (max-width: 520px){
    .genre-grid{ grid-template-columns: 1fr; }
  }
  .genre-option{
    position: relative;
    display:flex;
    align-items:center;
    padding: 4px 6px;
    cursor: pointer;
    user-select:none;
  }
  .genre-option input{
    position: absolute;
    opacity: 0;
    pointer-events: none;
  }
  .genre-option span{
    position: relative;
    padding-left: 22px;
    font-weight: 600;
  }
  .genre-option span::before{
    content: "○";
    position: absolute;
    left: 0;
    top: 0;
    color: #666;
    font-weight: 700;
  }
  .genre-option input:checked + span::before{
    content: "✓";
    color: #111;
  }
  .genre-option input{ transform: scale(1.1); }
  .genre-selected{
    margin-top: 8px;
    display:flex;
    flex-wrap: wrap;
    gap: 6px;
  }
  .genre-pill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(0,0,0,.06);
    font-size: 12px;
  }
  .no-scroll{ overflow:hidden; }
</style>

<div class="card">
  <div class="card__head">
    <div class="fetch-head">
      <h1 class="card__title">売れ筋ランキング取得</h1>

      <button id="btnInfo" class="iconbtn" type="button" aria-label="検索条件の説明">
        <img src="icon/info.png" alt="" class="info-icon">
      </button>
    </div>

  </div>
  <div class="form">

    <!-- ジャンル選択ボタン -->
    <div class="row">
      <button id="btnOpenGenreModal" class="btn" type="button">ジャンル選択</button>
    </div>

    <!-- 期間選択 -->
    <div class="row">
      <label class="muted" for="period">取得期間</label>
      <select class="input" id="period" name="period">
        <option value="realtime" selected>リアルタイム</option>
        <option value="daily">デイリー</option>
      </select>
    </div>

    <!-- 選択ジャンル（×付き） -->
    <div class="row">
      <div id="genreSelected" class="chips">
        <span class="muted">未選択（総合ランキング）</span>
      </div>
    </div>

    <!-- 検索ボタン（取得） -->
    <div class="row">
      <button id="btnFetchRanking" class="btn btn--primary" type="button">検索</button>
    </div>

    <div id="fetchStatus" class="status muted"></div>
  </div>

</div>
<!-- ▼ 検索条件説明モーダル -->
<div id="infoModal" class="modal-overlay" hidden aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="infoModalTitle">
    <div class="modal__head">
      <h3 class="modal__title" id="infoModalTitle">検索条件について</h3>
      <button id="btnInfoClose" class="btn btn--ghost" type="button">閉じる</button>
    </div>

    <div class="modal__body">
      <ul class="list">
        <li><b>取得期間：</b>リアルタイム / デイリー</li>
        <li><b>取得件数：</b>30件固定</li>
        <li><b>ジャンル未選択：</b>総合ランキングを取得</li>
        <li><b>ジャンル選択：</b>選択したジャンルのランキングを取得して保存</li>
      </ul>
    </div>
  </div>
</div>

<!-- ▼ モーダル（ポップアップ） -->
<div id="genreModal" class="modal-overlay" hidden aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="genreModalTitle">
    <div class="modal__head">
      <h3 class="modal__title" id="genreModalTitle">ジャンルを選択</h3>
      <button id="btnGenreModalClose" class="btn btn--ghost" type="button">閉じる</button>
    </div>

    <div class="modal__body">
      <div class="muted" style="margin-bottom:10px;">
        親ジャンルを選ぶと子ジャンルが表示されます。未選択なら総合ランキング。
      </div>

      <div class="genre-stack">
        <div class="muted" style="margin:0 0 4px;">親ジャンル</div>
        <div class="genre-grid" id="parentGenreSelect" style="grid-template-columns: 1fr;">
          <?php foreach ($genreOptions as $genre): ?>
            <div class="genre-parent">
              <label class="genre-option">
                <input type="radio"
                      name="parent_genre_id"
                      value="<?= h($genre['id']) ?>"
                      data-label="<?= h($genre['label']) ?>">
                <span><?= h($genre['label']) ?></span>
              </label>
              <div class="genre-children" data-parent-id="<?= h($genre['id']) ?>">
                <ul class="genre-tree">
                  <li class="genre-tree__item">
                    <span class="muted">親ジャンルを選ぶと表示されます</span>
                  </li>
                </ul>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>


    <div class="modal__foot">
      <button id="btnGenreModalCancel" class="btn btn--ghost" type="button">キャンセル</button>
      <button id="btnGenreModalOk" class="btn btn--primary" type="button">決定</button>
    </div>
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
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('genreModal');
  const btnOpen = document.getElementById('btnOpenGenreModal');
  const btnClose = document.getElementById('btnGenreModalClose');
  const btnCancel = document.getElementById('btnGenreModalCancel');
  const btnOk = document.getElementById('btnGenreModalOk');
  const btnClear = document.getElementById('btnClearGenres');

  let snapshot = { parentId: null, childId: null };
  const renderSelectedGenres = () => {
    if (typeof window.renderSelectedGenres === 'function') {
      window.renderSelectedGenres();
    }
  };      
  function openModal() {
    // snapshotを保存
    snapshot = {
      parentId: document.querySelector('input[name="parent_genre_id"]:checked')?.value ?? null,
      childId: document.querySelector('input[name="child_genre_id"]:checked')?.value ?? null
    };
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('no-scroll');
    document.body.classList.add('no-scroll');
  }

  function closeModal() {
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('no-scroll');
    document.body.classList.remove('no-scroll');
  }

  function restoreSnapshot() {

    const parentInputs = Array.from(document.querySelectorAll('input[name="parent_genre_id"]'));
    const childInputs = Array.from(document.querySelectorAll('input[name="child_genre_id"]'));
    const childContainers = Array.from(document.querySelectorAll('.genre-children[data-parent-id]'));
    const resetChildContainers = () => {
      childContainers.forEach((container) => {
        container.classList.remove('is-open');
        container.innerHTML = `
          <ul class="genre-tree">
            <li class="genre-tree__item">
              <span class="muted">親ジャンルを選ぶと表示されます</span>
            </li>
          </ul>
        `;
      });
    };

    if (!snapshot?.parentId) {
      parentInputs.forEach((i) => { i.checked = false; });
      childInputs.forEach((i) => { i.checked = false; });
      resetChildContainers();
      renderSelectedGenres();
      return;
    }

    parentInputs.forEach((i) => { i.checked = i.value === snapshot.parentId; });

    const parent = parentInputs.find((i) => i.checked);
    if (parent) {
      parent.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (!snapshot.childId) {
      renderSelectedGenres();
      return;
    }

    const applyChildSelection = () => {
      const nextChildInputs = Array.from(document.querySelectorAll('input[name="child_genre_id"]'));
      const match = nextChildInputs.find((i) => i.value === snapshot.childId);
      if (!match) return false;
      match.checked = true;
      renderSelectedGenres();
      return true;
    };

    if (applyChildSelection()) return;

    const childContainer = document.querySelector(`.genre-children[data-parent-id="${snapshot.parentId}"]`);
    if (!childContainer) return;
    const observer = new MutationObserver(() => {
      if (applyChildSelection()) {
        observer.disconnect();
      }
    });
    observer.observe(childContainer, { childList: true, subtree: true });
  }

  btnOpen?.addEventListener('click', openModal);

  btnClose?.addEventListener('click', () => {
    // 閉じる＝キャンセル扱い（元に戻す）
    restoreSnapshot();
    closeModal();
  });

  btnCancel?.addEventListener('click', () => {
    restoreSnapshot();
    closeModal();
  });

  btnOk?.addEventListener('click', () => {
    closeModal();
    renderSelectedGenres();
  });

  // 背景クリックで閉じる（キャンセル扱い）
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) {
      restoreSnapshot();
      closeModal();
    }
  });

  btnClear?.addEventListener('click', () => {
    document.querySelectorAll('input[name="parent_genre_id"]').forEach(i => i.checked = false);
    document.querySelectorAll('input[name="child_genre_id"]').forEach(i => i.checked = false);
    renderSelectedGenres();
  });

  // 初期描画
  renderSelectedGenres();
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
