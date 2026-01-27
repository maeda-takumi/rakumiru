<?php
// ★ Gitに絶対入れない運用が理想（.envなど）だけど、まずはここでOK
// 後で .env に逃がすなら、このファイルから読む形に変更する。

define('DB_HOST', 'localhost');
define('DB_NAME', 'ss911157_rakumiru');
define('DB_USER', 'ss911157_sedo');
define('DB_PASS', 'sedorisedori');
define('DB_CHARSET', 'utf8mb4');
define('APP_SECRET_KEY', 'change_this_to_long_random_secret_32chars_or_more');


// アプリ設定
define('APP_NAME', 'ラクミル');
define('APP_BASE_URL', ''); // 例: https://example.com/rakumiru（必要になったら）
