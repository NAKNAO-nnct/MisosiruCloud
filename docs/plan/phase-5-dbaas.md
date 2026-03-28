# Phase 5: DBaaS（データベース as a Service）

## 概要

MySQL・PostgreSQL・Redis の DB インスタンスをテナント向けに提供する機能を実装する。  
VM は Cloud-init で DB を自動セットアップ。バックアップは S3 に暗号化転送する。

## 現在の判定（2026-03-28）

⚠️ ほぼ実装済み（残りは一部テスト）

`DbaasService` / `BackupService` / DBaaS Controller・Request・View・Route は実装済み。  
バックアップスケジューラ（`backups:run-scheduled`）と定期実行登録（毎時）も実装済み。

---

## チェックポイント

### 5-1. DbaasService

- [x] `App\Services\DbaasService` 作成
  - `provision(Tenant $tenant, array $params): DatabaseInstance`
    1. `VmService::provisionVm()` で DB 専用 VM を起動
    2. `DatabaseInstance` レコード INSERT（status=`provisioning`）
    3. Cloud-init 完了後 status を `running` に更新
  - `start(DatabaseInstance $db): void`
  - `stop(DatabaseInstance $db): void`
  - `terminate(DatabaseInstance $db): void`
  - `getConnectionDetails(DatabaseInstance $db): array`（暗号化された認証情報を復号して返す）

### 5-2. BackupService

- [x] `App\Services\BackupService` 作成
  - `executeBackup(DatabaseInstance $db): void`
    - DB 種別に応じて `mysqldump` or `pg_dump` or RDB `SAVE` を SSH/Cloud-init 経由で実行
    - AES-256 で暗号化
    - S3 プロキシ経由でアップロード
  - `listBackups(DatabaseInstance $db): array`（S3 バケット内のオブジェクト一覧）
  - `restore(DatabaseInstance $db, string $s3Key): void`
  - `pruneOldBackups(DatabaseInstance $db): void`（保持ポリシーに従い古いバックアップ削除）

### 5-3. バックアップスケジューラ

- [x] `App\Console\Commands\RunScheduledBackups` コマンド作成
- [x] `routes/console.php` でスケジュール登録（毎時チェック、cron 式評価）

### 5-4. Form Request

- [x] `App\Http\Requests\Dbaas\CreateDatabaseRequest`
  - `tenant_id`, `db_type` (mysql/postgres/redis), `db_version`, `cpu`, `memory_mb`, `disk_gb`
- [x] `App\Http\Requests\Dbaas\UpdateDatabaseRequest`

### 5-5. コントローラ

- [x] `Dbaas\Index` — DB インスタンス一覧
- [x] `Dbaas\Create` — DB 作成画面（種別・バージョン選択）
- [x] `Dbaas\Store` — DB 作成処理
- [x] `Dbaas\Show` — DB 詳細（接続情報、バックアップ一覧、操作）
- [x] `Dbaas\Start` — DB 起動
- [x] `Dbaas\Stop` — DB 停止
- [x] `Dbaas\Destroy` — DB 削除
- [x] `Dbaas\Backup` — 即時バックアップ実行
- [x] `Dbaas\Backups` — バックアップ一覧（S3 から取得）
- [x] `Dbaas\Restore` — バックアップリストア（admin のみ）
- [x] `Dbaas\Upgrade` — バージョンアップグレード（admin のみ）
- [x] `Dbaas\Credentials` — 接続情報表示（認証情報を復号して表示）
- [x] `Api\DbaasStatus` — DB ステータス（AJAX ポーリング用）

### 5-6. View

- [x] `resources/views/dbaas/index.blade.php`（一覧・種別バッジ）
- [x] `resources/views/dbaas/create.blade.php`（作成フォーム）
- [x] `resources/views/dbaas/show.blade.php`（詳細・接続情報・バックアップ一覧）

### 5-7. ルーティング

- [x] DBaaS CRUD + アクションルートを `routes/web.php` に追加

### 5-8. テスト

- [x] Feature: DB 作成フロー（VM プロビジョニングまで含む、Http::fake）
- [x] Feature: 接続情報が正しく復号されて表示されること
- [x] Feature: バックアップ実行→ S3 アップロード API コール確認
- [x] Feature: バックアップリストア（admin のみ許可）
- [x] Unit: `BackupService::pruneOldBackups()` の保持日数ロジック
- [x] Unit: DBaaS Cloud-init テンプレート出力が DB 種別ごとに正しいこと

---

## 完了条件

- DB インスタンスを作成すると VM がプロビジョニングされ `database_instances` レコードが作られること
- バックアップ実行時に S3 プロキシへの PUT リクエストが送られること（Http::fake で確認）
- `php artisan test --compact` で全テストがパス
- `vendor/bin/pint --dirty --format agent` でスタイル違反なし
