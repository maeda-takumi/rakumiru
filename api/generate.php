<?php
require_once __DIR__ . '/_response.php';
require_once __DIR__ . '/../inc/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_ng('Method Not Allowed', 405);
}

$name  = trim($_POST['name'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if ($name === '') {
    json_ng('商品名を入力してください');
}

// ▼ いったんダミー文生成（後でGemini API呼び出しに差し替え）
$text = "【{$name}】\n"
      . "気になるポイントをサクッとまとめました✨\n\n"
      . ($notes ? "▼特徴メモ\n{$notes}\n\n" : "")
      . "✅ おすすめポイント\n"
      . "・デザインが合わせやすい\n"
      . "・普段使いにちょうどいい\n"
      . "・口コミもチェックして選ぶと安心\n\n"
      . "気になったら商品ページでサイズ感やレビューも確認してみてください！";

json_ok(['text' => $text, 'generated_at' => now()]);
