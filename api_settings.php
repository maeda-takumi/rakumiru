<?php
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/header.php';

$userId = 1;

// DBから保存済み情報を取得（全文キーは出さず「保存済み/更新日時」だけ）
$pdo = db();
$stmt = $pdo->prepare("
  SELECT provider, updated_at
  FROM api_keys
  WHERE user_id = :uid
    AND provider IN ('rakuten_app_id', 'gemini')
");
$stmt->execute([':uid' => $userId]);
$rows = $stmt->fetchAll();

$saved = [
  'rakuten_app_id' => ['updated_at' => null],
  'gemini'         => ['updated_at' => null],
];

foreach ($rows as $r) {
  $p = $r['provider'] ?? '';
  if (isset($saved[$p])) {
    $saved[$p]['updated_at'] = $r['updated_at'] ?? null;
  }
}
?>

<div class="card">
  <h1 class="card__title">API設定</h1>
  <p class="card__desc">AppID と Gemini API Key を入力 → <b>テストOKなら自動保存</b>します。</p>

  <div class="form" autocomplete="off">
    <div class="api-status">
      <div class="api-status__row">
        <span class="api-status__label">楽天AppID</span>
        <span class="api-status__val">
          <?= $saved['rakuten_app_id']['updated_at']
            ? '保存済（更新: ' . h($saved['rakuten_app_id']['updated_at']) . '）'
            : '未保存' ?>
        </span>
      </div>

      <div class="api-status__row">
        <span class="api-status__label">Gemini Key</span>
        <span class="api-status__val">
          <?= $saved['gemini']['updated_at']
            ? '保存済（更新: ' . h($saved['gemini']['updated_at']) . '）'
            : '未保存' ?>
        </span>
      </div>
    </div>

    <!-- 自動補完対策のダミー（強いパスワードマネージャ対策） -->
    <input type="text" name="fake_user" autocomplete="username" style="position:absolute; left:-9999px; width:1px; height:1px;">
    <input type="password" name="fake_pass" autocomplete="current-password" style="position:absolute; left:-9999px; width:1px; height:1px;">

    <label class="label">楽天アプリID（applicationId）</label>
    <input
      id="rakutenAppId"
      name="rakuten_app_id"
      class="input"
      type="text"
      inputmode="numeric"
      placeholder="例：1025..."
      autocomplete="off"
      autocapitalize="off"
      autocorrect="off"
      spellcheck="false"
    >

    <label class="label">Gemini API Key</label>
    <input
      id="geminiKey"
      name="gemini_api_key"
      class="input"
      type="password"
      placeholder="AIza... を貼り付け"
      autocomplete="new-password"
      autocapitalize="off"
      autocorrect="off"
      spellcheck="false"
    >

    <div class="row">
      <button id="btnTestSave" class="btn btn--primary" type="button">テストして保存</button>
      <button id="btnToggleGemini" class="btn" type="button">Geminiキー 表示/非表示</button>
    </div>

    <div class="sep"></div>
    <div id="status" class="status muted"></div>
  </div>
</div>

<script>
// さらに確実に自動入力を潰す（ページロード後に強制クリア）
window.addEventListener('load', () => {
  const r = document.getElementById('rakutenAppId');
  const g = document.getElementById('geminiKey');
  if (r) r.value = '';
  if (g) g.value = '';
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
