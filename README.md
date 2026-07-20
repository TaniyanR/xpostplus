# XPostPlus

XPostPlus は、FANZA・ソクミル・DUGAの商品情報をAPIから取得し、Xへ手動投稿するための投稿文を作成・管理するサーバー設置型ツールです。X APIによる自動投稿は行いません。

## 主な機能

- WordPress風のレスポンシブ管理画面
- PC・タブレット・スマートフォン対応
- ログイン、ログアウト、パスワード変更、ログイン試行制限
- CSRF、XSS、SQL Injection、セッション固定攻撃への対策
- FANZA・ソクミル・DUGA用の分離されたAPIサービスクラス
- API認証情報の暗号化保存
- 商品検索、保存、投稿文生成
- 置換タグ式テンプレート
- タイトル・女優名・ジャンルからのハッシュタグ生成
- NGワード除外
- 複数商品の一括投稿文生成
- サンプル画像・サンプル動画・テキストのみの投稿準備
- 複数サイトを追加できるDB構成
- 初期サイトとして PinkClub FANZA を登録

## 必要環境

- PHP 8.3以上推奨
- PHP Sodium拡張
- PDO SQLiteまたはPDO MySQL
- MySQL 8以上、またはSQLite
- ApacheまたはNginx
- HTTPS必須を推奨

## 設置前の重要設定

`.env.example`を参考に、サーバーの環境変数を設定してください。

```text
APP_ENV=production
APP_KEY=十分に長いランダム文字列
XPOSTPLUS_ALLOW_INSTALL=1
DB_DRIVER=sqlite
```

`APP_KEY`はAPI認証情報の暗号化に使います。運用開始後に変更すると、保存済みAPI設定を復号できなくなります。

## 初回インストール

1. ファイルをサーバーへアップロードします。
2. Webサーバーのドキュメントルートを`public/`に設定します。
3. `storage/`へPHPから書き込みできる権限を付けます。
4. `APP_KEY`を設定します。
5. 初回管理者作成時だけ`XPOSTPLUS_ALLOW_INSTALL=1`を設定します。
6. ブラウザでログイン画面を開きます。
7. メールアドレスと12文字以上のパスワードを入力して管理者を作成します。
8. 作成後、必ず`XPOSTPLUS_ALLOW_INSTALL=0`へ戻します。

第三者による管理者の先取りを防ぐため、初期設定を有効にしたまま放置しないでください。

## SQLiteで使う場合

`DB_DRIVER=sqlite`を設定します。初回アクセス時に`storage/database.sqlite`が作成されます。

## MySQLで使う場合

```text
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=xpostplus
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

## API設定

管理画面の「設定」から保存します。

- FANZA: `api_id`、`affiliate_id`
- ソクミル: `affiliate_id`、`endpoint`
- DUGA: `appid`

保存内容は`APP_KEY`を使って暗号化されます。旧バージョンの平文JSON設定は読み込みできますが、次回保存時に暗号化形式へ移行します。

## 基本的な使い方

1. API設定とNGワードを保存します。
2. 商品管理でサービスとキーワードを選択します。
3. 検索結果から必要な商品を保存します。
4. 投稿テンプレートを作成します。
5. 投稿作成画面で商品とテンプレートを選びます。
6. 生成した投稿文をコピーし、Xへ手動投稿します。

投稿先として想定しているXアカウントは`@yofukashinavi`です。

## テンプレートタグ

- `{title}`
- `{article_url}`
- `{affiliate_url}`
- `{sample_movie_url}`
- `{image_url}`
- `{hashtags}`
- `{service}`
- `{actress}`
- `{genre}`

## セキュリティ

- パスワードは`password_hash()`で保存
- API認証情報はSodiumで暗号化
- PDOプリペアドステートメントを使用
- POSTフォームでCSRFトークンを検証
- ログイン時とパスワード変更時にセッションIDを再生成
- 15分間に5回以上失敗したログインを制限
- 1時間操作がないログインセッションを失効
- CSP、X-Frame-Options、nosniffなどのセキュリティヘッダーを送信
- 管理画面のレスポンスをブラウザへ保存しない設定

## 本番運用前の確認

- HTTPSが有効になっている
- `APP_KEY`が推測困難な値になっている
- `XPOSTPLUS_ALLOW_INSTALL=0`になっている
- `storage/`がWebから直接閲覧できない
- `.env`がGit管理・Web公開されていない
- API接続テストを各サービスで実施した
- PCとスマートフォンの両方で操作確認した
- DBと`APP_KEY`を別々の安全な場所へバックアップした

## フォルダ構成

```text
app/
  Controllers/
  Core/
  Models/
  Services/
  Services/Affiliate/
  Views/
config/
database/migrations/
public/
storage/
```

## ライセンス

MIT
