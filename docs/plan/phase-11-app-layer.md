# Phase 11: アプリケーションレイヤー基盤 (Data / Repository / Queue)

## 概要

detailed-design.md セクション 2.5 で定義されている **Data クラス / Repository レイヤーアーキテクチャ** と、
セクション 3 で定義されている **非同期ジョブ設計 (Laravel Queue)** を実装する。

これは全フェーズ横断の設計原則であり、既存コードのリファクタリングおよび新規基盤の整備を含む。

## 現在の判定

⚠️ 部分実装（Data クラス・Repository・Controller/Service 統合は大部分が完了。Queue/非同期ジョブは未着手）

---

## 出典（設計ドキュメント）

- [detailed-design.md セクション 2.5](../detailed-design.md) — Data/Repository レイヤー設計
- [detailed-design.md セクション 3](../detailed-design.md) — 非同期ジョブ設計 (Laravel Queue)
- [infrastructure-design.md](../infrastructure-design.md) — `mgmt-queue` Docker サービス

---

## チェックポイント

### 11-1. Data クラス（EntityData）

> `app/Data/` 配下に配置。`final readonly class` + private constructor + `of`/`make` ファクトリメソッド。

- [x] `App\Data\Tenant\TenantData` — Tenant エンティティ ✅ 実装済み
- [x] `App\Data\Vm\VmMetaData` — VmMeta エンティティ ✅ 実装済み
- [ ] `App\Data\Vm\VmDetailResponseData` — VM + Proxmox 状態を合成する ResponseData
- [ ] `App\Data\Vm\ProvisionVmCommand` — 非同期ジョブ用 CommandData
- [x] `App\Data\Dbaas\DatabaseInstanceData` — DatabaseInstance エンティティ ✅ 実装済み
- [x] `App\Data\Dbaas\BackupScheduleData` — BackupSchedule エンティティ ✅ 実装済み（計画外で追加実装）
- [ ] `App\Data\Dbaas\DbaasDetailResponseData` — DBaaS 詳細 ResponseData
- [ ] `App\Data\Dbaas\ProvisionDbaasCommand` — 非同期ジョブ用 CommandData
- [x] `App\Data\Container\ContainerJobData` — ContainerJob エンティティ ✅ 実装済み
- [ ] `App\Data\Container\DeployContainerCommand` — 非同期ジョブ用 CommandData
- [ ] `App\Data\Network\NetworkData` — Network エンティティ
- [x] `App\Data\S3\S3CredentialData` — S3Credential エンティティ ✅ 実装済み
- [x] `App\Data\User\AuthUserData` — User エンティティ ✅ 実装済み（`UserData` ではなく `AuthUserData` として実装）
- [x] `App\Data\VpsGateway\VpsGatewayData` — VpsGateway エンティティ ✅ 実装済み（計画外で追加実装、ただし `toArray()` 未実装）
- [x] `App\Data\ProxmoxNode\ProxmoxNodeData` — ProxmoxNode エンティティ ✅ 実装済み（計画外で追加実装、ただし `make()` / `toArray()` 未実装）

### 11-2. Data クラス設計規約の適用

- [x] 実装済み Data クラスの大部分が規約に準拠 ✅
  - `final readonly class` ✅
  - `private function __construct()` ✅
  - `static of(Model $model): self` ✅
  - `static make(array $attributes): self` ✅（ProxmoxNodeData を除く）
  - `getXxx(): Type` getter ✅
  - `toArray(): array` ✅（VpsGatewayData, ProxmoxNodeData を除く）
- [ ] `VpsGatewayData` に `toArray()` メソッドを追加
- [ ] `ProxmoxNodeData` に `make()` / `toArray()` メソッドを追加（暗号化フィールドの扱いを要検討）

### 11-3. Repository クラス

> `app/Repositories/` 配下に配置。Eloquent Model はこの層内部でのみ使用。

- [x] `App\Repositories\TenantRepository` ✅ 実装済み（findById, paginate, all, create, update）
- [x] `App\Repositories\VmMetaRepository` ✅ 実装済み（findByVmid, allWithTenant, countByTenantId 等 11 メソッド）
- [x] `App\Repositories\DatabaseInstanceRepository` ✅ 実装済み（paginate, countByTenantId 等）
- [x] `App\Repositories\ContainerJobRepository` ✅ 実装済み（paginate, create, update, delete）
- [x] `App\Repositories\BackupScheduleRepository` ✅ 実装済み（findByDatabaseInstanceId, allEnabled 等）
- [x] `App\Repositories\S3CredentialRepository` ✅ 実装済み（findActiveByTenantId, paginateByTenantId 等）
- [ ] `App\Repositories\UserRepository` — 未実装
- [x] `App\Repositories\ProxmoxNodeRepository` ✅ 実装済み（activate/deactivate, DB::transaction 使用）
- [x] `App\Repositories\VpsGatewayRepository` ✅ 実装済み（計画外で追加実装、nextSequence 含む）

### 11-4. Repository 設計規約の適用

- [x] 全 Repository が規約に概ね準拠 ✅
  - 引数・返り値は Data クラス ✅
  - `Model::query()` 経由でクエリ ✅（ProxmoxNodeRepository の activate/deactivate のみ `DB::transaction` 使用）
  - リレーション取得は eager load ✅

### 11-5. 既存 Controller / Service のリファクタリング

- [x] Controller が Repository を inject し、Data クラス経由でデータ操作 ✅ 実装済み
  - Dashboard, Vm(6), Dbaas(8), Container(7), VpsGateway(6), Network(4), S3Credential 等の全 Controller が Repository 使用
- [x] Service が Repository 経由でデータアクセス ✅ 実装済み
  - VmService, DbaasService, ContainerService, S3CredentialService, BackupService, TenantService, VpsGatewayService が統合済み
- [x] View に渡すデータを Data クラスに統一 ✅ 実装済み
- [ ] NetworkService が生の配列を返す箇所 → NetworkData に統一する（NetworkData 作成後）

### 11-6. 非同期ジョブクラス

- [ ] `App\Jobs\ProvisionVmJob` 作成
  - `vm_meta_id`, `params` を受け取り非同期で VM プロビジョニング
  - Cloud-init YAML 生成 → スニペット保存 → clone → waitForTask → config → resize → start
  - `provisioning_status` を各ステップで更新 (`pending` → `cloning` → `configuring` → `starting` → `ready`)
  - 例外時: `provisioning_status='error'`, `provisioning_error` にメッセージ保存
- [ ] `App\Jobs\ProvisionDbaasJob` 作成
  - Cloud-init (DB設定込み) → clone → waitForTask → config → resize → start
  - `vm_metas.provisioning_status` と `database_instances.status` を両方更新
- [ ] `App\Jobs\DestroyVmJob` 作成
  - stop → スニペット削除 → Proxmox destroy → DB レコード削除
  - 中間リソースのクリーンアップ

### 11-7. Queue 設定

- [ ] Queue ドライバ: `database`（`jobs` テーブルマイグレーション確認）
- [ ] キュー名: `provisioning`（デフォルト `default` と分離）
- [ ] タイムアウト: `600` 秒（Ceph full clone 対応）
- [ ] リトライ回数: `0`（VM 作成は冪等でないため自動リトライしない）
- [ ] ワーカー数: `1`（Phase 1 段階、VMID 採番の競合防止）
- [ ] `config/queue.php` に `provisioning` キュー設定を追加

### 11-8. Docker Compose Queue Worker

- [ ] `compose.yaml` に `queue` サービスを追加
  ```yaml
  queue:
    build:
      context: .
      dockerfile: ./docker/php/Dockerfile
    command: php artisan queue:work --queue=provisioning --timeout=600 --tries=1
    volumes:
      - ./:/var/www
    depends_on:
      - db
      - redis
    env_file:
      - .env
  ```

### 11-9. Phase 4/5 の既存 Service との統合

- [ ] `VmService::provisionVm()` を `ProvisionVmJob::dispatch()` に変更
  - HTTP リクエスト内ではバリデーション + DB 登録 + ジョブディスパッチのみ
  - Proxmox 操作はジョブ内で実行
- [ ] `DbaasService::provision()` を `ProvisionDbaasJob::dispatch()` に変更
- [ ] VM 詳細画面に `provisioning_status` ポーリング対応（リロード or Alpine.js）
- [ ] 管理画面に「リトライ」ボタン追加（`provisioning_status='error'` の場合）

### 11-10. テスト

- [ ] Unit: Data クラスの `of()` / `make()` / `toArray()` 変換テスト
- [ ] Unit: Repository の `findById()` / `store()` / `update()` が Data クラスで入出力すること
- [ ] Feature: `ProvisionVmJob` の正常フロー（Http::fake + Queue::fake）
- [ ] Feature: `ProvisionVmJob` エラー時に `provisioning_status='error'` になること
- [ ] Feature: `ProvisionDbaasJob` の正常フロー
- [ ] Feature: `DestroyVmJob` が VM 削除 + スニペット削除を実行すること
- [ ] Unit: Queue 設定値（timeout, tries, queue name）が正しいこと

---

## 完了条件

- 全 Controller / Service が Eloquent Model を直接参照せず Data / Repository 経由でデータアクセスしていること
- `ProvisionVmJob` / `ProvisionDbaasJob` / `DestroyVmJob` が正常に動作すること（Queue::fake で確認）
- `provisioning_status` が各ステップで正しく遷移すること
- `php artisan test --compact` で全テストがパス
- `vendor/bin/pint --dirty --format agent` でスタイル違反なし
