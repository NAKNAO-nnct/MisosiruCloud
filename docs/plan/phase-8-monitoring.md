# Phase 8: 監視 & 可観測性

## 概要

Grafana Cloud へのメトリクス/ログ集約を管理パネルに組み込む。  
ダッシュボード埋め込み・テナント別フィルタリング URL 生成を実装する。  
OTel Collector の Nomad Job 定義ファイルもここで整備する。

---

## チェックポイント

### 8-1. MonitoringService

- [ ] `App\Services\MonitoringService` 作成
  - `getGrafanaDashboardUrl(string $dashboardUid, ?Tenant $tenant = null): string`
    - 環境変数 `GRAFANA_BASE_URL`, `GRAFANA_ORG_ID` を参照
    - テナント指定時は `var-tenant_id={tenant->uuid}` クエリパラメータを付与
  - `generateEmbedToken(string $dashboardUid, ?Tenant $tenant = null): array`
    - Grafana Service Account Token API を使い一時トークンを生成
    - token と有効期限を返す

### 8-2. コントローラ

- [ ] `Monitoring\Index` — 監視ダッシュボード画面
  - ノード状態概要（Proxmox API）
  - Grafana 埋め込み URL 取得
- [ ] `Monitoring\GrafanaUrl` — Grafana 埋め込み URL を JSON で返す（AJAX 用）

### 8-3. View

- [ ] `resources/views/monitoring/index.blade.php`
  - Grafana iframe 埋め込み
  - ノード CPU/メモリのリソースメーター
  - テナント絞り込みセレクタ（admin のみ全テナント選択可）

### 8-4. OTel Collector Nomad Job 定義（インフラ設定）

> この項目は Laravel コードではなく Nomad HCL ファイルとして管理する

- [ ] `infra/nomad/jobs/otel-agent.hcl` — OTel Agent (System Job)
  - Nomad Telemetry (`/metrics`) をスクレイピング
  - cAdvisor (`:8080/metrics`) をスクレイピング
  - Gateway Collector へ転送
- [ ] `infra/nomad/jobs/otel-gateway.hcl` — OTel Gateway Collector
  - Agent からの受信
  - Grafana Cloud (OTLP) へ転送
- [ ] `infra/nomad/jobs/cadvisor.hcl` — cAdvisor (System Job)
  - Docker ソケット・rootfs マウント設定

### 8-5. ダッシュボード設定

- [ ] プロビジョニング用 Grafana ダッシュボード JSON 定義（任意：`infra/grafana/` 配下）
  - VM リソース使用状況
  - Nomad コンテナメトリクス
  - cAdvisor 詳細メトリクス

### 8-6. ルーティング

- [ ] `GET /monitoring` → `Monitoring\Index`
- [ ] `GET /monitoring/grafana-url` → `Monitoring\GrafanaUrl`

### 8-7. テスト

- [ ] Feature: 監視画面が認証済みユーザに表示されること
- [ ] Feature: Grafana URL 生成にテナント ID が正しく付与されること
- [ ] Feature: 未認証ユーザはリダイレクトされること
- [ ] Unit: `MonitoringService::getGrafanaDashboardUrl()` のクエリパラメータ生成

---

## 完了条件

- テナントを選択すると Grafana 埋め込み URL にテナント UUID フィルタが含まれること
- OTel Agent / cAdvisor の Nomad Job HCL ファイルが `infra/nomad/` に配置されること
- `php artisan test --compact` で全テストがパス
- `vendor/bin/pint --dirty --format agent` でスタイル違反なし
