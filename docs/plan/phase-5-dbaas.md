# Phase 5: DBaaS（データベース as a Service）

## 概要

MySQL・PostgreSQL・Redis の DB インスタンスをテナント向けに提供する機能を実装する。  
VM は Cloud-init で DB を自動セットアップ。バックアップは S3 に暗号化転送する。

## 現在の判定（2026-03-28）

⚠️ 部分実装

`DbaasService` / `BackupService` と関連 Data/Repository は実装済み。  
ただし、DBaaS Controller・Request・View・Route・専用テストは未実装。

---

## チェックポイント

### 5-1. DbaasService

- [ ] `App\Services\DbaasService` 作成
  - `provision(Tenant $tenant, array $params): DatabaseInstance`
    1. `VmService::provisionVm()` で DB 専用 VM を起動
    2. `DatabaseInstance` レコード INSERT（status=`provisioning`）
    3. Cloud-init 完了後 status を `running` に更新
  - `start(DatabaseInstance $db): void`
  - `stop(DatabaseInstance $db): void`
  - `terminate(DatabaseInstance $db): void`
  - `getConnectionDetails(DatabaseInstance $db): array`（暗号化された認証情報を復号して返す）

### 5-2. BackupService

- [ ] `App\Services\BackupService` 作成
  - `executeBackup(DatabaseInstance $db): void`
    - DB 種別に応じて `mysqldump` or `pg_dump` or RDB `SAVE` を SSH/Cloud-init 経由で実行
    - AES-256 で暗号化
    - S3 プロキシ経由でアップロード
  - `listBackups(DatabaseInstance $db): array`（S3 バケット内のオブジェクト一覧）
  - `restore(DatabaseInstance $db, string $s3Key): void`
  - `pruneOldBackups(DatabaseInstance $db): void`（保持ポリシーに従い古いバックアップ削除）

### 5-3. バックアップスケジューラ

- [ ] `App\Console\Commands\RunScheduledBackups` コマンド作成
- [ ] `routes/console.php` でスケジュール登録（毎時チェック、cron 式評価）

### 5-4. Form Request

- [ ] `App\Http\Requests\Dbaas\CreateDatabaseRequest`
  - `tenant_id`, `db_type` (mysql/postgres/redis), `db_version`, `cpu`, `memory_mb`, `disk_gb`
- [ ] `App\Http\Requests\Dbaas\UpdateDatabaseRequest`

### 5-5. コントローラ

- [ ] `Dbaas\Index` — DB インスタンス一覧
- [ ] `Dbaas\Create` — DB 作成画面（種別・バージョン選択）
- [ ] `Dbaas\Store` — DB 作成処理
- [ ] `Dbaas\Show` — DB 詳細（接続情報、バックアップ一覧、操作）
- [ ] `Dbaas\Start` — DB 起動
- [ ] `Dbaas\Stop` — DB 停止
- [ ] `Dbaas\Destroy` — DB 削除
- [ ] `Dbaas\Backup` — 即時バックアップ実行
- [ ] `Dbaas\Backups` — バックアップ一覧（S3 から取得）
- [ ] `Dbaas\Restore` — バックアップリストア（admin のみ）
- [ ] `Dbaas\Upgrade` — バージョンアップグレード（admin のみ）
- [ ] `Dbaas\Credentials` — 接続情報表示（認証情報を復号して表示）
- [ ] `Api\DbaasStatus` — DB ステータス（AJAX ポーリング用）

### 5-6. View

- [ ] `resources/views/dbaas/index.blade.php`（一覧・種別バッジ）
- [ ] `resources/views/dbaas/create.blade.php`（作成フォーム）
- [ ] `resources/views/dbaas/show.blade.php`（詳細・接続情報・バックアップ一覧）

### 5-7. ルーティング

- [ ] DBaaS CRUD + アクションルートを `routes/web.php` に追加

### 5-8. テスト

- [ ] Feature: DB 作成フロー（VM プロビジョニングまで含む、Http::fake）
- [ ] Feature: 接続情報が正しく復号されて表示されること
- [ ] Feature: バックアップ実行→ S3 アップロード API コール確認
- [ ] Feature: バックアップリストア（admin のみ許可）
- [ ] Unit: `BackupService::pruneOldBackups()` の保持日数ロジック
- [ ] Unit: DBaaS Cloud-init テンプレート出力が DB 種別ごとに正しいこと

---

## 完了条件

- DB インスタンスを作成すると VM がプロビジョニングされ `database_instances` レコードが作られること
- バックアップ実行時に S3 プロキシへの PUT リクエストが送られること（Http::fake で確認）
- `php artisan test --compact` で全テストがパス
- `vendor/bin/pint --dirty --format agent` でスタイル違反なし
