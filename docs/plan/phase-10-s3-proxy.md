# Phase 10: S3 プロキシサーバ (Go)

## 概要

テナント VM が外部 S3 (AWS S3 / Wasabi) の認証情報を直接持つ必要がないよう、  
**Go 製のカスタム S3 互換プロキシ**を実装する。  
内部認証情報で AWS Signature V4 を検証し、テナントプレフィックス付きのパスに書き換えて外部 S3 へ再署名・転送する。

**配置:** mgmt-docker VM 上の Docker Compose サービス（ポート 9000）  
**エンドポイント:** `http://s3.infra.example.com:9000`

---

## アーキテクチャ

```
[テナント VM / DBaaS]
    │  S3互換リクエスト (Authorization: AWS4-HMAC-SHA256 内部credentials)
    ▼
[S3 プロキシ (:9000)]
    │  1. AWS Signature V4 検証 (内部 Access Key / Secret Key)
    │  2. 認証情報からテナント ID 特定
    │  3. リクエストパスにテナントプレフィックス付与
    │  4. 外部 S3 認証情報で再署名
    │  5. 外部 S3 に転送
    ▼
[外部 S3 (AWS S3 / Wasabi)]
    → s3://dbaas-backups/tenant-{id}/{db_id}/{date}.sql.gz.gpg
```

---

## チェックポイント

### 10-1. プロジェクト初期化

- [ ] `s3-proxy/` ディレクトリ作成
- [ ] `go mod init` (`Go 1.22+`)
- [ ] `s3-proxy/main.go` — エントリポイント (設定読み込み → サーバ起動)
- [ ] `s3-proxy/Dockerfile` — マルチステージビルド（ビルド: golang:1.22-alpine → 実行: alpine）
- [ ] `s3-proxy/.env.example` — 全環境変数定義

### 10-2. 設定管理 (`config/`)

- [ ] `config/config.go`
  - 環境変数からの設定読み込み
  - `S3_BACKEND_ENDPOINT`, `S3_BACKEND_REGION`, `S3_BACKEND_ACCESS_KEY`, `S3_BACKEND_SECRET_KEY`
  - `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`
  - `LISTEN_ADDR` (デフォルト `:9000`)
  - `CACHE_REFRESH_INTERVAL` (デフォルト `5m`)
  - `LOG_LEVEL` (debug/info/warn/error)
- [ ] バリデーション：必須項目が未設定なら起動時にエラー

### 10-3. 認証情報ストア (`store/`)

- [ ] `store/mysql.go`
  - 管理 DB (`s3_credentials` テーブル) への接続
  - `LoadActiveCredentials() ([]Credential, error)` — `is_active=true` の全レコード取得
  - `UpdateLastUsedAt(accessKey string) error` — 最終使用日時を更新
  - **注意:** `secret_key_encrypted` は Laravel `encrypt()` で暗号化されている
    - Go 側での復号方式を決定する必要あり
    - **方式 A:** Laravel の暗号化フォーマット (AES-256-CBC, base64 JSON) を Go で再現
    - **方式 B:** S3 プロキシ専用の平文カラム `secret_key_plain` を追加し、認証情報作成時に両方保存
    - → **推奨: 方式 B**（Laravel 暗号化の内部フォーマットに依存しない）

### 10-4. 認証情報キャッシュ (`auth/`)

- [ ] `auth/credential_store.go`
  - インメモリキャッシュ (`sync.Map`)
  - 起動時に `store.LoadActiveCredentials()` で一括ロード
  - `CACHE_REFRESH_INTERVAL` 間隔でバックグラウンドリフレッシュ
  - `Get(accessKey string) (*Credential, bool)`
- [ ] `auth/verifier.go`
  - AWS Signature V4 の検証
    - `Authorization` ヘッダのパース
    - `access_key` で `credential_store` から `secret_key` を取得
    - 署名再計算 → 一致確認
  - 失敗時：`403 Forbidden` (XML エラーレスポンス、S3 互換フォーマット)

### 10-5. リクエスト処理 (`proxy/`)

- [ ] `proxy/handler.go` — メイン HTTP ハンドラ
  - 対応 S3 操作：
    - `PutObject` (`PUT /{bucket}/{key}`)
    - `GetObject` (`GET /{bucket}/{key}`)
    - `DeleteObject` (`DELETE /{bucket}/{key}`)
    - `ListObjectsV2` (`GET /{bucket}?list-type=2`)
    - `HeadObject` (`HEAD /{bucket}/{key}`)
    - `CreateMultipartUpload` (`POST /{bucket}/{key}?uploads`)
    - `UploadPart`, `CompleteMultipartUpload`, `AbortMultipartUpload`
  - エラーレスポンスは S3 XML フォーマット
- [ ] `proxy/rewriter.go` — リクエストパス書き換え
  - `allowed_bucket` のチェック → 不一致なら `403 AccessDenied`
  - `allowed_prefix` をパスに挿入
  - 例: `/dbaas-backups/2026-03-22.sql.gz` → `/dbaas-backups/tenant-1/2026-03-22.sql.gz`
  - `ListObjectsV2` の `prefix` パラメータにもテナントプレフィックスを挿入
- [ ] `proxy/signer.go` — 外部 S3 向け再署名
  - 外部 S3 の認証情報で AWS Signature V4 を生成
  - `Authorization` ヘッダ置き換え
  - ストリーミング署名対応 (大容量ファイル)

### 10-6. ミドルウェア (`middleware/`)

- [ ] `middleware/logging.go` — リクエストログ（method, path, status, duration, tenant_id）
- [ ] `middleware/ratelimit.go` — テナント単位のレートリミット（access_key ベース）

### 10-7. ヘルスチェック

- [ ] `GET /health` — 認証不要、DB 接続確認、`200 OK`

### 10-8. Docker Compose 統合

- [ ] `compose.yaml` に `s3-proxy` サービスを追加
  ```yaml
  s3-proxy:
    build: ./s3-proxy
    ports:
      - "9000:9000"
    environment:
      - S3_BACKEND_ENDPOINT
      - DB_HOST=mgmt-db
      # ...
    depends_on:
      - mgmt-db
  ```

### 10-9. Laravel 側統合 (`Lib/S3Proxy`)

- [ ] `App\Lib\S3Proxy\CredentialManager` 作成
  - `createDefaultCredential(Tenant $tenant): S3Credential`
  - `rotateCredential(S3Credential $credential): S3Credential`
  - `getCredentialsForTenant(Tenant $tenant): Collection`
  - **方式 B の場合:** 認証情報作成時に `secret_key_plain` カラムにも平文保存
- [ ] `s3_credentials` マイグレーションに `secret_key_plain` カラムを追加（方式 B 採用時）

### 10-10. テスト

#### Go 側テスト
- [ ] `auth/verifier_test.go` — AWS Signature V4 検証の正常系・異常系
- [ ] `auth/credential_store_test.go` — キャッシュのロード・リフレッシュ動作
- [ ] `proxy/rewriter_test.go` — パス書き換えロジック（テナントプレフィックス挿入）
- [ ] `proxy/rewriter_test.go` — `allowed_bucket` / `allowed_prefix` の認可チェック
- [ ] `proxy/handler_test.go` — 統合テスト（mock S3 バックエンドに対するリクエスト転送）
- [ ] セキュリティ: パストラバーサル (`/../`) を含むキーが拒否されること

#### Laravel 側テスト
- [ ] Feature: `CredentialManager::createDefaultCredential()` が `secret_key_plain` を含むレコードを作成すること
- [ ] Feature: `CredentialManager::rotateCredential()` が旧キーを無効化し新キーを発行すること

---

## 完了条件

- `docker compose up s3-proxy` でサーバがポート 9000 で起動すること
- `GET /health` に 200 が返ること
- 内部認証情報で `PutObject` / `GetObject` リクエストを送ると外部 S3 にプロキシされること
- `allowed_bucket` / `allowed_prefix` に違反するリクエストが `403` で拒否されること
- パストラバーサル攻撃パターンが拒否されること
- `go test ./...` で全テストがパス
- `php artisan test --compact` で Laravel 側テストがパス
- `vendor/bin/pint --dirty --format agent` でスタイル違反なし
