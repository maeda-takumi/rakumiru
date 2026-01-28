# 楽天市場リサーチアプリ向け：楽天ウェブサービス（Rakuten Web Service）仕様まとめ（MD）

更新日: 2026-01-28

> 本ドキュメントは、楽天ウェブサービス公式ドキュメント（Rakuten Ichiba APIs）を元に、  
> **「リサーチアプリでよく使う部分」**に絞って要点を整理したものです。  
> 公式ドキュメントの最新版も併せて参照してください。

---

## 0. 目的（このMDのスコープ）

あなたのリサーチアプリで実装したい機能に対応して、主に以下をまとめます。

- **商品検索（リアルタイム検索の基礎）**：IchibaItem/Search
- **ランキング（デイリー／リアルタイム）**：IchibaItem/Ranking
- **ジャンル（カテゴリ）ツリー取得**：IchibaGenre/Search

---

## 1. アプリID / Affiliate ID（キー）

### 1.1 applicationId（必須）
- APIリクエストに必ず必要な開発者用ID（App ID）。

### 1.2 affiliateId（任意）
- 付与すると **affiliateUrl** が返ったり、URLがアフィリエイトURLになるパラメータがあります。
- Affiliate IDは「開発者につき1つ」で、登録した複数アプリで共通利用する前提の説明があります。

公式: https://webservice.rakuten.co.jp/guide

---

## 2. リクエスト制限（超重要）

- **1つの application_id につき、1秒に1回以下**のリクエスト推奨（制限）。
- 429（Too many requests）が返るケースがあります。

公式（ヘルプ）:
- https://webservice.faq.rakuten.net/hc/ja/articles/900001974383

---

## 3. 共通仕様（全APIに共通しやすい考え方）

### 3.1 形式
- REST（GET）で呼び出し、`format=json`（デフォルト）または `format=xml`
- JSONPも callback 指定で可能（Webフロントから直呼びする場合はCORS等も含め要検討）

### 3.2 formatVersion（重要）
- 多くのAPIで `formatVersion=2` を指定すると、JSONが扱いやすい形に改善されます。
  - v1: `items[0].item.itemName`
  - v2: `items[0].itemName`

### 3.3 elements（出力項目の絞り込み）
- `elements` にカンマ区切りでフィールド名を指定すると、必要な項目だけ返せて高速化／転送量削減になります。

---

## 4. エラー仕様（共通）

典型的に以下のHTTPステータスが返ります（APIによって例が掲載されています）。

- **400**: パラメータ不正／必須不足（`wrong_parameter`）
- **404**: データなし（`not_found`）
- **429**: リクエスト過多（`too_many_requests`）
- **500**: サーバ内部エラー（`system_error`）
- **503**: メンテ／過負荷（`service_unavailable`）

> 実装では「HTTPステータス + error/error_description」をログに残して、  
> 429はバックオフ（待って再試行）・503は時間を空けて再試行、などを推奨。

---

# 5. API別仕様

---

## 5.1 楽天市場商品検索API（IchibaItem/Search） v2022-06-01

### Endpoint
`https://app.rakuten.co.jp/services/api/IchibaItem/Search/20220601?[parameter]=[value]…`

公式:
- https://webservice.rakuten.co.jp/documentation/ichiba-item-search

### 必須（いずれか条件あり）
- `applicationId`（必須）
- **検索条件は「いずれか必須」**：`keyword` / `genreId` / `itemCode` / `shopCode`
  - 例：keyword検索、ジャンル絞り込み検索、特定商品コード検索、ショップ内検索

### よく使う入力パラメータ（実装で重要）
- `keyword`：検索キーワード（UTF-8 URLエンコード）
  - ANDが基本。OR検索したい場合は `orFlag=1` を使う
- `genreId`：ジャンルID（カテゴリID）
- `hits`：1〜30（1ページの件数）
- `page`：ページ番号
- `sort`：ソート（例：`+itemPrice` など ※値はURLエンコード）
- `minPrice` / `maxPrice`：価格帯絞り込み
- `hasReviewFlag`：レビュー有りのみ
- `elements`：返却フィールドを絞る（転送量削減）
- `formatVersion=2`：レスポンスを扱いやすくする（強く推奨）
- `genreInformationFlag`：ジャンル毎の件数情報を取得（必要な場合）
- `tagInformationFlag`：タグ毎の件数情報を取得（必要な場合）

> 注意：公式に「同一URLへの短時間大量アクセスで一定期間応答しなくなる可能性」が明記されています。  
> **キャッシュ**と**連打防止**は必須です。

### リクエスト例（keyword + 安い順）
```
https://app.rakuten.co.jp/services/api/IchibaItem/Search/20220601
?applicationId=YOUR_APP_ID
&format=json
&formatVersion=2
&keyword=%E7%A6%8F%E8%A2%8B
&sort=%2BitemPrice
&hits=30
&page=1
```

### 返ってくる主な情報（例）
- `items[]`：商品配列（名前、価格、URL、画像URL、レビュー数/平均、ショップ情報など）
- 画像は `smallImageUrls` / `mediumImageUrls` など配列で返る

---

## 5.2 楽天市場ランキングAPI（IchibaItem/Ranking） v2022-06-01

### Endpoint
`https://app.rakuten.co.jp/services/api/IchibaItem/Ranking/20220601?[parameter]=[value]…`

公式:
- https://webservice.rakuten.co.jp/documentation/ichiba-item-ranking

### 目的
- 「楽天市場ランキング（Ichiba Ranking）」相当のランキング情報を取得
- **デイリー**と**リアルタイム**の両方を扱えます

### 主な入力パラメータ（重要）
- `applicationId`（必須）
- `genreId`（ジャンル別ランキング）
- `page`：1〜34（30位以下もページで取得でき、最大1000位相当まで）
- `period`：
  - **指定なし**：デイリー（デフォルト）
  - `realtime`：リアルタイムランキング
- `age`（10/20/30/40/50）
- `sex`（0:男性, 1:女性）
- `carrier`（0:PC, 1:mobile）
- `formatVersion=2`、`elements`（任意）

### パラメータの注意（排他）
- `genreId` は `age`/`sex` と同時指定不可（公式エラー例あり）
- `age` と `sex` は同時指定可

### リクエスト例
**(1) 総合ランキング（デイリー）**
```
https://app.rakuten.co.jp/services/api/IchibaItem/Ranking/20220601
?applicationId=YOUR_APP_ID
&format=json
&formatVersion=2
```

**(2) ジャンル別ランキング（例：genreId=100283）**
```
https://app.rakuten.co.jp/services/api/IchibaItem/Ranking/20220601
?applicationId=YOUR_APP_ID
&genreId=100283
&format=json
&formatVersion=2
```

**(3) リアルタイムランキング**
```
https://app.rakuten.co.jp/services/api/IchibaItem/Ranking/20220601
?applicationId=YOUR_APP_ID
&period=realtime
&format=json
&formatVersion=2
```

### エラー（ランキングAPIは公式に例が詳しい）
- 429 Too many requests など（バックオフ推奨）

---

## 5.3 楽天市場ジャンル検索API（IchibaGenre/Search） v2017-07-11

### Endpoint
`https://app.rakuten.co.jp/services/api/IchibaGenre/Search/20170711?[parameter]=[value]…`

公式:
- https://webservice.rakuten.co.jp/documentation/ichiba-genre-search

### 目的
- 楽天市場の「ジャンル（カテゴリ）」の **名前** と **階層構造（親子関係）** を取得

### 主な入力パラメータ
- `applicationId`（必須）
- `genreId`（必須）
  - `genreId=0` を指定すると **最上位（第1階層）** が取れます（公式に例あり）

### リクエスト例（第1階層の取得）
```
https://app.rakuten.co.jp/services/api/IchibaGenre/Search/20170711
?applicationId=YOUR_APP_ID
&genreId=0
&format=json
```

### 返ってくる構造（ざっくり）
- current（現在のgenre）
- parents（親ジャンル）
- children（子ジャンル一覧）
- lowestFlg（最下層かどうか）など

> UIで「ジャンルをもっと増やす」場合は、  
> **genreId=0 → children を辿ってツリー表示**が王道です。  
> ただし階層が深く取得回数が増えるので、**サーバ側でキャッシュ**推奨。

---

# 6. あなたの「リサーチアプリ」向け実装メモ（おすすめ設計）

## 6.1 ジャンルUIを楽天市場並みにする
1. 初回 or 定期バッチで `genreId=0` を取得し第1階層を保存
2. ユーザーがカテゴリを開くたびに、その `genreId` の children を取得して保存
3. DB/JSONキャッシュを参照してUI表示（API呼び出し連打を防ぐ）

## 6.2 「デイリー / リアルタイム」の両方を付ける
- Ranking APIで `period` を切り替えるだけで実現できる
  - period未指定：デイリー
  - period=realtime：リアルタイム
- ジャンルフィルタは `genreId` を付与

## 6.3 API節約と速度（elements + formatVersion=2）
- 商品検索は `formatVersion=2` + `elements` で必要最低限に絞るのが体感効きます
- 例（最低限セット案）：
  - `itemName,itemPrice,itemUrl,mediumImageUrls,reviewAverage,reviewCount,shopName,genreId,itemCode`

---

# 7. 公式ドキュメントリンク集（このMDの参照元）

- Rakuten Web Service Guide: https://webservice.rakuten.co.jp/guide
- Ichiba Item Search API (2022-06-01): https://webservice.rakuten.co.jp/documentation/ichiba-item-search
- Ichiba Item Ranking API (2022-06-01): https://webservice.rakuten.co.jp/documentation/ichiba-item-ranking
- Ichiba Genre Search API (2017-07-11): https://webservice.rakuten.co.jp/documentation/ichiba-genre-search
- リクエスト制限（ヘルプ）: https://webservice.faq.rakuten.net/hc/ja/articles/900001974383

---

## 付録：実装チェックリスト（最低限）
- [ ] 1秒1回制限を守る（サーバ側でレート制御）
- [ ] 429/503はバックオフで再試行
- [ ] ジャンルはキャッシュ（ツリーを保存してUIに反映）
- [ ] formatVersion=2を基本
- [ ] elementsで返却項目を絞る
- [ ] 同一URL連打を避ける（クエリが同じ時はキャッシュヒット）

