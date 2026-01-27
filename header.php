<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h(APP_NAME) ?></title>

  <link rel="icon" type="image/png" href="icon/icon.png">
  <link rel="apple-touch-icon" href="icon/icon.png">

  <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
</head>
<body>

  <!-- Overlay -->
  <div id="drawerOverlay" class="drawer-overlay" hidden></div>

  <!-- Drawer -->
  <aside id="drawer" class="drawer" aria-hidden="true">
    <div class="drawer__head">
      <div class="drawer__title"><?= h(APP_NAME) ?></div>
      <button id="drawerClose" class="iconbtn" type="button" aria-label="閉じる">
        ✕
      </button>
    </div>

    <nav class="drawer__nav">
      <a class="drawer__link" href="api_settings.php">API設定</a>
      <a class="drawer__link" href="items.php">商品一覧</a>
    </nav>

    <div class="drawer__foot">
      <div class="muted">楽天×リサーチ×AI 投稿補助</div>
    </div>
  </aside>

  <header class="topbar">
    <div class="container topbar__inner">
      <div class="brand">
        <img class="brand__icon" src="icon/icon.png" alt="<?= h(APP_NAME) ?>">
        <div class="brand__text">
          <div class="brand__name"><?= h(APP_NAME) ?></div>
          <div class="brand__sub">楽天×リサーチ×AI 投稿補助</div>
        </div>
      </div>

      <!-- Hamburger -->
      <button id="drawerOpen" class="iconbtn iconbtn--primary" type="button" aria-label="メニュー">
        <span class="hamburger" aria-hidden="true"></span>
      </button>
    </div>
  </header>

  <main class="container">
