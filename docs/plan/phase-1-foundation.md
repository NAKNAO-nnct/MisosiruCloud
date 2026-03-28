# Phase 1: 基盤構築

## 概要

Laravel の基本骨格を整備する。DB スキーマ・Eloquent モデル・認証フロー・ルーティング・共通 UI レイアウトを実装し、後続フェーズが乗れる土台を作る。

---

## チェックポイント

### 1-1. DB マイグレーション

- [ ] `tenants` テーブル作成マイグレーション
  - `uuid`, `name`, `slug`, `status`, `vnet_name`, `vni`, `network_cidr`, `nomad_namespace`, `metadata`
- [ ] `tenant_users` (中間テーブル) マイグレーション
  - `tenant_id`, `user_id`, `role`
- [ ] `vm_metas` テーブルマイグレーション
  - `tenant_id`, `proxmox_vmid`, `proxmox_node`, `purpose`, `label`, `shared_ip_address`, `provisioning_status`, `provisioning_error`, `deleted_at`
- [ ] `database_instances` テーブルマイグレーション
  - `tenant_id`, `vm_meta_id`, `db_type`, `db_version`, `port`, `admin_user`, `admin_password_encrypted` 等
- [ ] `backup_schedules` テーブルマイグレーション
- [ ] `container_jobs` テーブルマイグレーション
- [ ] `s3_credentials` テーブルマイグレーション
- [ ] `proxmox_nodes` テーブルマイグレーション
- [ ] `vps_gateways` テーブルマイグレーション
- [ ] `users` テーブルに `role` カラム追加マイグレーション
  - `ENUM('admin','tenant_admin','tenant_member')` デフォルト `tenant_member`
- [ ] 全マイグレーション `php artisan migrate:fresh` で正常実行確認

### 1-2. Eloquent モデル

- [ ] `User` モデル更新：`role` 属性追加、`tenants()` リレーション
- [ ] `Tenant` モデル作成（ファクトリ・シーダー含む）
- [ ] `VmMeta` モデル作成（ファクトリ含む、SoftDeletes）
- [ ] `DatabaseInstance` モデル作成（暗号化カラムの cast 設定）
- [ ] `BackupSchedule` モデル作成
- [ ] `ContainerJob` モデル作成（`env_vars_encrypted` の cast 設定）
- [ ] `S3Credential` モデル作成（`secret_key_encrypted` の cast 設定）
- [ ] `ProxmoxNode` モデル作成（`api_token_secret_encrypted`, `snippet_api_token_encrypted` の cast 設定）
- [ ] `VpsGateway` モデル作成

### 1-3. Enum 定義

- [ ] `App\Enums\VmStatus` (`Pending`, `Cloning`, `Configuring`, `Starting`, `Ready`, `Error`)
- [ ] `App\Enums\DatabaseType` (`Mysql`, `Postgres`, `Redis`)
- [ ] `App\Enums\TenantStatus` (`Active`, `Suspended`, `Deleted`)
- [ ] `App\Enums\UserRole` (`Admin`, `TenantAdmin`, `TenantMember`)

### 1-4. 認証フロー

> Fortify によりログイン・ログアウトは既存だが、2FA 画面・ルーティングが未整備

- [ ] ログイン画面 View 作成 (`resources/views/auth/login.blade.php`)
- [ ] 2FA 入力画面 View 作成
- [ ] 2FA セットアップ画面 View 作成
- [ ] `Auth\ShowLoginForm`, `Auth\Login`, `Auth\Logout` コントローラ作成
- [ ] `Auth\TwoFactor\{Show, Verify, Setup, Confirm, Disable}` コントローラ作成
- [ ] `routes/web.php` に認証ルート追加

### 1-5. 認可（ミドルウェア・ポリシー）

- [ ] `EnsureAdminAccess` ミドルウェア作成（role=admin のみ通過）
- [ ] `EnsureTenantAccess` ミドルウェア作成（テナントメンバー確認）
- [ ] `bootstrap/app.php` へミドルウェアエイリアス登録

### 1-6. 共通 UI レイアウト

- [ ] `resources/views/layouts/app.blade.php` 作成（Flux UI ナビゲーション含む）
- [ ] ダッシュボード空ページ (`resources/views/dashboard/index.blade.php`)
- [ ] `Dashboard\Index` コントローラ作成
- [ ] ダッシュボードルート登録 (`GET /`)

### 1-7. テスト

- [ ] `UserFactory` に `role` 対応を追加
- [ ] `TenantFactory` 作成
- [ ] Feature テスト：ログイン成功・失敗
- [ ] Feature テスト：ダッシュボード（未認証はリダイレクト）
- [ ] Feature テスト：管理者専用ページへのアクセス制御

---

## 完了条件

- `php artisan migrate:fresh --seed` が正常終了
- `php artisan route:list` で認証・ダッシュボードルートが確認できる
- `php artisan test --compact` で全テストがパス
- `vendor/bin/pint --dirty --format agent` でスタイル違反なし
