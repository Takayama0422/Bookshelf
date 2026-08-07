# BookShelf

BookShelfは、書籍の登録、レビュー、お気に入り、レビューへのいいね、ジャンル分類、ランキング表示、ISBN検索、読書レポート、APIトークン認証、読書計画、通知を扱う書籍レビューアプリです。

このREADMEは、実装済み機能の利用方法、データ構造、環境構築手順、APIエンドポイント、最終品質確認の観点を整理するための品質文書です。

## プロジェクトの目的

- 利用者が書籍情報を管理し、レビューを投稿できるようにする
- 気になる書籍をお気に入りとして保存できるようにする
- レビューへのいいねやジャンル分類により、書籍を見つけやすくする
- ISBN検索、読書レポート、読書計画、通知により読書管理を支援する
- APIトークン認証により、外部クライアントから書籍データを安全に操作できるようにする
- 開発・検証に必要なデータ構造と初期データを再現できるようにする

## 基本機能の概要

| 機能 | 概要 |
| --- | --- |
| ユーザー | 会員登録、ログイン、ログアウト、書籍・レビュー・お気に入り・読書計画の主体を管理する |
| 書籍 | タイトル、著者、ISBN、出版日、説明、画像URL、ジャンルを管理する |
| 検索・絞り込み・ソート | 書籍一覧でキーワード、ジャンル、並び順を指定できる |
| ISBN検索 | Google Books APIからISBNに紐づく書籍情報を取得し、登録フォームに反映する |
| ジャンル | 書籍を複数のジャンルに分類する |
| レビュー | ユーザーが書籍に評価とコメントを投稿する。1ユーザー1書籍につき1件まで登録できる |
| お気に入り | ユーザーが書籍をお気に入り登録・解除し、お気に入り一覧を確認する |
| レビューいいね | ユーザーがレビューにいいねを付ける |
| ランキング | レビュー評価をもとに書籍をTOP10で並べる |
| マイ読書レポート | 自分の総レビュー数、読了冊数、平均評価、評価分布、高評価書籍、ジャンル別評価傾向を確認する |
| 読書計画 | 認証ユーザーが書籍ごとに目標読了日を設定し、読了・削除・状態絞り込みを行う |
| 通知 | 読書計画の期限通知を一覧表示し、既読化する |
| 読書計画バッチ | 日次コマンドで3日前、当日、期限後通知を作成し、期限切れ計画を失効する |
| 公開API | 書籍一覧・詳細を公開し、Bearerトークン認証で書籍登録・更新・削除を行う |

## ER図

実装済みの主要テーブルは以下のとおりです。Laravel標準の `failed_jobs`、`password_reset_tokens` は省略しています。

```mermaid
erDiagram
    users ||--o{ books : owns
    users ||--o{ reviews : writes
    users ||--o{ favorites : favorites
    users ||--o{ review_likes : likes
    users ||--o{ reading_plans : plans
    users ||--o{ notifications : receives
    users ||--o{ personal_access_tokens : owns

    books ||--o{ reviews : has
    books ||--o{ favorites : favorited
    books ||--o{ book_genre : categorized
    books ||--o{ reading_plans : planned

    genres ||--o{ book_genre : categorizes

    reviews ||--o{ review_likes : liked

    users {
        bigint id PK
        string name
        string email UK
        timestamp email_verified_at
        string password
        string remember_token
        timestamp created_at
        timestamp updated_at
    }

    books {
        bigint id PK
        bigint user_id FK
        string title
        string author
        string isbn UK
        date published_date
        text description
        string image_url
        timestamp created_at
        timestamp updated_at
    }

    genres {
        bigint id PK
        string name UK
        timestamp created_at
        timestamp updated_at
    }

    book_genre {
        bigint book_id PK, FK
        bigint genre_id PK, FK
    }

    reviews {
        bigint id PK
        bigint user_id FK
        bigint book_id FK
        tinyint rating
        text comment
        timestamp created_at
        timestamp updated_at
    }

    favorites {
        bigint id PK
        bigint user_id FK
        bigint book_id FK
        timestamp created_at
    }

    review_likes {
        bigint id PK
        bigint user_id FK
        bigint review_id FK
        timestamp created_at
    }

    reading_plans {
        bigint id PK
        bigint user_id FK
        bigint book_id FK
        date target_date
        string status
        timestamp completed_at
        timestamp expired_at
        timestamp reminded_three_days_at
        timestamp reminded_due_at
        timestamp reminded_overdue_at
        timestamp created_at
        timestamp updated_at
    }

    notifications {
        uuid id PK
        string type
        string notifiable_type
        bigint notifiable_id
        text data
        timestamp read_at
        timestamp created_at
        timestamp updated_at
    }

    personal_access_tokens {
        bigint id PK
        string tokenable_type
        bigint tokenable_id
        string name
        string token UK
        text abilities
        timestamp last_used_at
        timestamp expires_at
        timestamp created_at
        timestamp updated_at
    }
```

主な制約は以下です。

- `books.user_id`、`reviews.user_id`、`reviews.book_id`、`book_genre.book_id`、`book_genre.genre_id`、`favorites.user_id`、`favorites.book_id`、`review_likes.user_id`、`review_likes.review_id`、`reading_plans.user_id`、`reading_plans.book_id` は外部キーです。
- 関連する親データが削除された場合、migrationに従って関連データはcascade deleteされます。
- `books.isbn`、`genres.name`、`users.email`、`personal_access_tokens.token` は一意です。
- `reviews` は `user_id` と `book_id` の組み合わせが一意です。
- `book_genre` は複合主キー、`favorites` と `review_likes` はid主キーと複合uniqueで重複を防止します。
- `reading_plans` は `user_id`、`book_id`、`status` の検索用インデックスを持ち、進行中計画の重複はFormRequestと保存処理で防止します。
- `notifications` はLaravel Database Notificationのpolymorphic recipientを使用します。

## 環境構築手順

完成済みリポジトリを取得して起動します。Dockerが利用できる環境で実行してください。

### 1. リポジトリ取得と.env作成

```bash
git clone https://github.com/Takayama0422/Bookshelf.git
cd Bookshelf
cp .env.example .env
```

.env のDB接続先とGoogle Books APIキーを、SailのMySQLサービス名に合わせて設定します。

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
GOOGLE_BOOKS_API_KEY=
```

### 2. Composer依存関係のインストール

```bash
composer install
```

### 3. Laravel Sailの起動

```bash
./vendor/bin/sail up -d
```

### 4. アプリケーションキー生成

```bash
./vendor/bin/sail artisan key:generate
```

### 5. マイグレーションとSeeder

```bash
./vendor/bin/sail artisan migrate --seed
```

既存DBを再構築する場合は、開発用データが破棄されることを確認してから実行します。

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

初期ユーザーのパスワードは password です。

### 6. フロントエンド依存関係とビルド

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

開発中にViteを起動する場合は、別ターミナルで以下を実行します。

```bash
./vendor/bin/sail npm run dev
```

### 7. アプリ・API・テストの実行確認

アプリケーションは http://localhost、APIは http://localhost/api/v1/books で確認できます。

```bash
./vendor/bin/sail artisan route:list
./vendor/bin/sail artisan test
./vendor/bin/sail pint --test
```

カバレッジを取得する場合は、XdebugまたはPCOVが有効な環境で実行します。

```bash
./vendor/bin/sail artisan test --coverage
```

## 使用技術

| 分類 | 技術 |
| --- | --- |
| バックエンド | PHP 8.2、Laravel 10 |
| フロントエンド | Blade、Vite、Tailwind CSS、Alpine.js |
| データベース | MySQL 8.4 |
| 開発環境 | Laravel Sail、Docker、phpMyAdmin |
| 認証 | Laravel Fortify、Laravel Sanctum |
| 外部API | Google Books API |
| テスト | PHPUnit |
| コード整形 | Laravel Pint |

## 利用方法

### Web画面

| 画面 | パス | 認証 | 概要 |
| --- | --- | --- | --- |
| 書籍一覧 | `/`、`/books` | 不要 | 書籍一覧、検索、ジャンル絞り込み、並び替え |
| 書籍詳細 | `/books/{book}` | 不要 | 書籍詳細、ジャンル、レビュー、レビューいいね数 |
| 書籍登録 | `/books/create` | 必要 | 書籍を登録する。ISBN検索結果をフォームに反映できる |
| ISBN検索 | `GET /books/isbn/{isbn}` | 必要 | Google Books APIから書籍情報を取得する |
| 書籍編集 | `/books/{book}/edit` | 所有者のみ | 所有書籍を編集する |
| お気に入り一覧 | `/favorites` | 必要 | 自分のお気に入り書籍を表示する |
| ジャンル一覧・詳細 | `/genres`、`/genres/{genre}` | 必要 | ジャンル管理とジャンル別書籍一覧 |
| ランキング | `/ranking` | 不要 | 平均評価順のTOP10を表示する |
| マイ読書レポート | `/reports` | 必要 | 自分の読書データを集計表示する |
| 読書計画一覧 | `/reading-plans` | 必要 | 自分の読書計画を状態別に確認する |
| 通知一覧 | `/notifications` | 必要 | 自分宛ての通知を確認し、既読化する |

### 読書計画バッチ

以下のコマンドで、進行中の読書計画を処理します。

```bash
./vendor/bin/sail artisan reading-plans:run-daily
```

処理内容は以下です。

- 目標読了日の3日前の計画に3日前通知を作成する
- 目標読了日当日の計画に当日通知を作成する
- 目標読了日を過ぎた計画に期限後通知を作成し、状態を `expired` にする
- `reminded_three_days_at`、`reminded_due_at`、`reminded_overdue_at` により重複通知を防止する

スケジューラでは `reading-plans:run-daily` を毎日20:00に実行します。

## APIエンドポイント一覧

| HTTPメソッド | パス | 認証 | 概要 |
| --- | --- | --- | --- |
| GET | `/api/user` | `auth:sanctum` | 認証済みユーザー情報を返す |
| POST | `/api/tokens` | 不要 | `email`、`password`、`token_name`を受け付け、SanctumのBearerトークンを発行する |
| GET | `/api/v1/books` | 不要 | 書籍一覧を取得する。`keyword`、`genre_id`、`sort`、`page`、`per_page`に対応する |
| GET | `/api/v1/books/{book}` | 不要 | 書籍詳細、ジャンル、レビュー投稿者名、評価、コメント、投稿日時を取得する |
| POST | `/api/v1/books` | `auth:sanctum` | 認証ユーザーを登録者として書籍を新規登録する |
| PUT/PATCH | `/api/v1/books/{book}` | `auth:sanctum` + `BookPolicy` | 所有者のみ書籍を更新できる |
| DELETE | `/api/v1/books/{book}` | `auth:sanctum` + `BookPolicy` | 所有者のみ書籍を削除できる |

### API認証

`POST /api/tokens` に認証情報を送信してBearerトークンを取得します。

```bash
curl -X POST http://localhost/api/tokens \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"yamada@example.com","password":"password","token_name":"local-client"}'
```

レスポンスの `token` を `Authorization` ヘッダーに指定して、書き込み系APIを呼び出します。

```bash
curl -X POST http://localhost/api/v1/books \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"title":"API登録書籍","author":"著者名","isbn":"9784000000999","published_date":"2020-01-01","description":"説明文","image_url":"https://example.com/cover.jpg","genres":[1]}'
```

### API共通仕様

- 一覧APIはデフォルト20件、最大100件のページネーションに対応する。
- 一覧APIのレスポンスは `data`、`meta`、`links` 形式で返す。
- `sort` は `latest`、`oldest`、`title`、`rating` に対応する。
- 存在しない書籍は404、未認証の書き込みは401、所有者以外の更新・削除は403、入力エラーは422を返す。
- エラーは `message` と `errors` を含むJSON形式で返し、バリデーションメッセージは日本語で統一する。
- レスポンス整形には `BookResource`、`BookCollection`、`ReviewResource`、`GenreResource` を使用する。

## 最終品質確認

Issue #20では以下を確認対象とします。

- 全Feature Test、全Unit Test
- `migrate:fresh --seed` によるDB再構築
- Seederの件数、整合性、再現性
- ルート一覧、Blade主要画面、APIエンドポイント
- API認証、認証・認可、401、403、404、422
- Google Books APIの成功、0件、通信失敗、タイムアウト、異常レスポンス
- 読書計画の日付境界、通知重複防止、Scheduler、Command
- Pint
- 可能な場合のテストカバレッジ

## 開発環境URL

| 用途 | URL |
| --- | --- |
| アプリケーション | `http://localhost` |
| Vite開発サーバー | `http://localhost:5173` |
| phpMyAdmin | `http://localhost:8080` |

## 作成者

- 高山雄生
