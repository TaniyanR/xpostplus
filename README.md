# XPostPlus

XPostPlus は、FANZA・ソクミル・DUGA などのアフィリエイト API から商品情報を取得し、X（旧 Twitter）へ**手動投稿するための投稿文**を作成・管理するサーバー設置型管理ツールです。自動投稿は行いません。

## 主な機能

- WordPress 風のレスポンシブ管理画面
- ログイン、ログアウト、パスワード変更、ログイン制限
- CSRF / XSS / SQL Injection 対策
- FANZA / ソクミル / DUGA 用 API サービスクラス
- API キーを管理画面から保存
- 商品検索、保存、投稿文生成
- 置換タグ式テンプレート
- 商品情報からのハッシュタグ自動生成と NG ワード除外
- 複数商品からの一括投稿文生成
- コピー用 UI（X への投稿は手動）

## フォルダ構成

```text
app/
  Controllers/        画面ごとの処理
  Core/               ルーター、DB、ビュー、CSRF などの基盤
  Models/             DB モデル
  Services/           投稿生成、設定、ハッシュタグなど
  Services/Affiliate/ FANZA・ソクミル・DUGA API クラス
  Views/              HTML テンプレート
config/               アプリ・DB 設定
database/migrations/  将来のマイグレーション用
public/               公開ディレクトリ（ドキュメントルート）
storage/              SQLite DB やログ
```

## 必要環境

- PHP 8.3 以上推奨
- MySQL 8 以上、または SQLite
- Apache / Nginx
- HTTPS 推奨

## インストール

1. リポジトリをサーバーへアップロードします。
2. Web サーバーのドキュメントルートを `public/` に設定します。
3. `storage/` に PHP から書き込みできる権限を付与します。
4. ブラウザでサイトを開きます。
5. 初回アクセス時にログイン画面で管理者メールアドレスとパスワードを入力すると、管理者アカウントが作成されます。

### SQLite で使う場合（初心者向け）

追加設定なしで利用できます。初回アクセス時に `storage/database.sqlite` が作成されます。

### MySQL で使う場合

MySQL に DB を作成し、環境変数を設定してください。

```bash
export DB_DRIVER=mysql
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_DATABASE=xpostplus
export DB_USERNAME=your_user
export DB_PASSWORD=your_password
```

テーブルは初回アクセス時に自動作成されます。

## 初回ログイン

初回はユーザーが存在しないため、ログイン画面が管理者作成画面として動作します。

- メールアドレス: 任意
- パスワード: 8文字以上

2回目以降は通常のログイン画面になります。

## API 設定

管理画面の「設定」から各サービスのキーを保存します。キーはコードに直接書かず DB に保存されます。

- FANZA: `api_id`, `affiliate_id`
- ソクミル: `affiliate_id`, `endpoint`
- DUGA: `appid`

キー未設定でもデモデータで画面操作を確認できます。

## 使い方

1. 「設定」で API 情報と NG ワードを保存します。
2. 「商品管理」でサービスとキーワードを選んで検索します。
3. 検索結果から必要な商品を保存します。
4. 「テンプレート」で投稿文テンプレートを作成します。
5. 「投稿作成」で商品とテンプレートを選び、投稿文を生成します。
6. 生成済み投稿の「コピー」ボタンで X に手動投稿します。

## テンプレートタグ

以下のタグを本文に書くと商品情報に置換されます。

- `{title}`
- `{article_url}`
- `{affiliate_url}`
- `{sample_movie_url}`
- `{image_url}`
- `{hashtags}`
- `{service}`
- `{actress}`
- `{genre}`

## セキュリティ設計

- パスワードは `password_hash()` で保存
- SQL は PDO プリペアドステートメントを使用
- 出力は HTML エスケープ
- フォームは CSRF トークンを検証
- セッション ID はログイン時に再生成
- 15分間に5回以上失敗したログインを制限

## 開発メモ

API 追加時は `App\Services\Affiliate\AffiliateServiceInterface` を実装したサービスクラスを追加し、コントローラーのサービス解決に追加してください。

## ライセンス

MIT
