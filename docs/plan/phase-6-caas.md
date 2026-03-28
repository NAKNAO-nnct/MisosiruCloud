# Phase 6: CaaS — コンテナ as a Service (Nomad 連携)

## 概要

Nomad + CNI を使ったマルチテナント対応コンテナ管理機能を実装する。  
Lib/Nomad ライブラリの自作、コンテナデプロイ・Traefik 自動ルーティング連携。

---

## チェックポイント

### 6-1. Lib/Nomad — 自作 Nomad API クライアント

- [ ] `App\Lib\Nomad\Client` 作成（Nomad API HTTP クライアント）
  - ベース URL: `http://{nomad_addr}:4646/v1`
  - Bearer トークン認証
- [ ] `DataObjects\JobSpec` — Nomad Job HCL をマッピングするデータ構造
- [ ] `DataObjects\AllocationStatus`
- [ ] `Resources\Job`
  - `listJobs(string $namespace = ''): array`
  - `getJob(string $jobId, string $namespace = ''): array`
  - `registerJob(array $jobSpec): array`
  - `stopJob(string $jobId, string $namespace = '', bool $purge = false): array`
  - `getJobAllocations(string $jobId): array`
  - `scaleJob(string $jobId, int $count, string $namespace = ''): array`
  - `getJobLogs(string $allocId, string $taskName, string $logType = 'stdout'): string`
- [ ] `Resources\Namespace_` (Nomad 名前空間)
  - `listNamespaces(): array`
  - `createNamespace(string $name, string $description = ''): array`
  - `deleteNamespace(string $name): array`
- [ ] `NomadApi` 統合エントリポイント（`job()`, `namespace()`）

### 6-2. ContainerService

- [ ] `App\Services\ContainerService` 作成
  - `deployContainer(Tenant $tenant, array $params): ContainerJob`
    1. Nomad Namespace がなければ作成
    2. `ContainerJob` レコード INSERT
    3. Traefik ルーティング用 Consul タグを Job Spec に埋め込む
    4. `NomadApi::job()->registerJob()` で登録
  - `restartContainer(ContainerJob $job): void`
  - `scaleContainer(ContainerJob $job, int $replicas): void`
  - `terminateContainer(ContainerJob $job): void`
  - `getLogs(ContainerJob $job, string $taskName): string`

### 6-3. Form Request

- [ ] `App\Http\Requests\Container\DeployContainerRequest`
  - `tenant_id`, `name`, `image`, `replicas`, `cpu_mhz`, `memory_mb`, `domain`, `port_mappings`, `env_vars`

### 6-4. コントローラ

- [ ] `Container\Index` — コンテナ一覧（Nomad ジョブ + `container_jobs`）
- [ ] `Container\Create` — デプロイ画面
- [ ] `Container\Store` — デプロイ処理
- [ ] `Container\Show` — コンテナ詳細（アロケーション状態、レプリカ数）
- [ ] `Container\Restart` — 再起動
- [ ] `Container\Scale` — レプリカスケール変更
- [ ] `Container\Destroy` — コンテナ停止・削除
- [ ] `Container\Logs` — ログ表示（ページング）
- [ ] `Api\ContainerStatus` — ステータス（AJAX ポーリング用）

### 6-5. View

- [ ] `resources/views/containers/index.blade.php`（一覧・状態バッジ）
- [ ] `resources/views/containers/deploy.blade.php`（デプロイフォーム）
- [ ] `resources/views/containers/show.blade.php`（詳細・アロケーション・ログリンク）

### 6-6. ルーティング

- [ ] コンテナ CRUD + アクションルートを `routes/web.php` に追加

### 6-7. テスト

- [ ] Feature: コンテナデプロイ（Nomad API コールを Http::fake で確認）
- [ ] Feature: Traefik タグが domain 設定から正しく生成されること
- [ ] Feature: コンテナスケール変更
- [ ] Feature: 他テナントのコンテナにアクセスできないこと
- [ ] Unit: `ContainerService` の Nomad Job Spec 生成ロジック
- [ ] Unit: `DeployContainerRequest` の env_vars 暗号化保存

---

## 完了条件

- コンテナをデプロイすると Nomad API `PUT /v1/jobs` が正しいジョブ Spec で呼ばれること
- Traefik ルーティングタグが `container_jobs.domain` から自動生成されること
- `php artisan test --compact` で全テストがパス
- `vendor/bin/pint --dirty --format agent` でスタイル違反なし
