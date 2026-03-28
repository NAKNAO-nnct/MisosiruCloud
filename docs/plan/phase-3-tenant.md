# Phase 3: テナント管理

## 概要

マルチテナントの基本単位となるテナントを管理する機能を実装する。  
テナント作成時に Proxmox SDN VNet・サブネットを自動作成し、S3 デフォルト認証情報を発行する。

## 現在の判定（2026-03-28）

✅ 完了

Tenant/S3 の Service・Controller・Request・View と Feature テストまで実装済み。

---

## チェックポイント

### 3-1. サービス層

- [ ] `App\Services\S3CredentialService` 作成
  - `generateAccessKey(): string`（20文字英数字、プレフィックス `MSIR`）
  - `generateSecretKey(): string`（40文字英数字）
  - `createForTenant(Tenant $tenant, string $bucket, string $prefix, string $description): S3Credential`
  - `rotate(S3Credential $credential): S3Credential`
  - `deactivate(S3Credential $credential): void`

### 3-2. Form Request

- [ ] `App\Http\Requests\Tenant\CreateTenantRequest`
  - `name`: required, string, max:255
  - `slug`: required, string, max:100, unique:tenants, regex:/^[a-z0-9\-]+$/
- [ ] `App\Http\Requests\Tenant\UpdateTenantRequest`

### 3-3. コントローラ（Single Action Controller）

- [ ] `Tenant\Index` — テナント一覧（ページネーション付き）
- [ ] `Tenant\Create` — テナント作成画面表示
- [ ] `Tenant\Store` — テナント作成処理
  1. DB INSERT (uuid, name, slug)
  2. VNI 算出 (`10000 + tenant_id`)、`vnet_name = tenant-{id}`、`network_cidr = 10.{id}.0.0/24`
  3. Proxmox SDN VNet 作成 → サブネット作成 → `applySdn()`
  4. S3 デフォルト認証情報自動発行 (dbaas-backups バケット)
  5. 失敗時はトランザクション rollback → エラーメッセージ返却
- [ ] `Tenant\Show` — テナント詳細（所有リソース一覧）
- [ ] `Tenant\Edit` — テナント編集画面表示
- [ ] `Tenant\Update` — テナント更新処理
- [ ] `Tenant\Destroy` — テナント削除（状態を `deleted` に変更。SDN VNet 削除含む）

### 3-4. S3 認証情報コントローラ

- [ ] `S3Credential\Index` — テナントの S3 認証情報一覧
- [ ] `S3Credential\Store` — S3 認証情報追加作成
- [ ] `S3Credential\Show` — 認証情報詳細（Secret Key 復号表示）
- [ ] `S3Credential\Destroy` — 無効化
- [ ] `S3Credential\Rotate` — Secret Key ローテーション

### 3-5. View

- [ ] `resources/views/tenants/index.blade.php`（Flux テーブル、検索、ページネーション）
- [ ] `resources/views/tenants/create.blade.php`（作成フォーム）
- [ ] `resources/views/tenants/show.blade.php`（テナント詳細 + リソース概要）
- [ ] `resources/views/tenants/edit.blade.php`（編集フォーム）
- [ ] `resources/views/s3_credentials/index.blade.php`
- [ ] `resources/views/s3_credentials/show.blade.php`（パスワードマスク + 表示切替）

### 3-6. ルーティング

- [ ] `routes/web.php` にテナント CRUD ルートを追加（`admin` ミドルウェア）
- [ ] S3 認証情報ルートを追加（テナントネスト）

### 3-7. テスト

- [ ] Feature: テナント作成（SDN API をモック）→ DB, VNI, S3Credential が正しく作られること
- [ ] Feature: テナント slug 一意性バリデーション
- [ ] Feature: テナント削除時の状態変更確認
- [ ] Feature: S3 認証情報ローテーション
- [ ] Feature: admin 以外のユーザがアクセスできないことの確認

---

## 完了条件

- テナントを作成すると `tenants` レコード・`s3_credentials` レコードが作られること
- Proxmox SDN API コールが正しいエンドポイント・パラメータで呼ばれること（Http::fake で確認）
- `php artisan test --compact` で全テストがパス
- `vendor/bin/pint --dirty --format agent` でスタイル違反なし
