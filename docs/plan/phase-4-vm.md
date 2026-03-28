# Phase 4: VM 管理

## 概要

Proxmox VE 上の VM を管理する機能を実装する。  
VM 一覧・詳細はリアルタイムで Proxmox API から取得。作成は Cloud-init テンプレートをクローンして自動設定。

---

## チェックポイント

### 4-1. Cloud-init ビルダー

- [ ] `App\Services\CloudInit\CloudInitBuilder` 作成
  - `buildUserData(array $opts): string`（cloud-config YAML 生成）
  - `buildNetworkConfig(Tenant $tenant, string $ip, string $gateway): string`
- [ ] `App\Services\CloudInit\Templates\BaseTemplate` 抽象クラス
- [ ] `App\Services\CloudInit\Templates\MysqlTemplate`
- [ ] `App\Services\CloudInit\Templates\PostgresTemplate`
- [ ] `App\Services\CloudInit\Templates\RedisTemplate`

### 4-2. VmService

- [ ] `App\Services\VmService` 作成
  - `listAllVms(): array`（全アクティブノードの VM を集約）
  - `getVmWithMeta(int $vmid): array`（Proxmox VM + `vm_metas` を結合）
  - `provisionVm(Tenant $tenant, array $params): VmMeta`
    1. `VmMeta` レコード INSERT（status=`pending`）
    2. テンプレート VM をクローン（`ProxmoxApi::vm()->cloneVm()`）
    3. Cloud-init 設定 (`updateVmConfig`)
    4. スニペット API 経由で cloud-config をアップロード（Phase 9 と連携）
    5. VM 起動 → タスク完了待機
    6. `VmMeta` を `ready` に更新
  - `terminateVm(VmMeta $vmMeta): void`

### 4-3. Form Request

- [ ] `App\Http\Requests\Vm\CreateVmRequest`
  - `tenant_id`, `label`, `cpu`, `memory_mb`, `disk_gb`, `template_vmid`, `node`, `purpose`
- [ ] `App\Http\Requests\Vm\ResizeVmRequest`
  - `disk`, `size`（例: `+10G`）

### 4-4. コントローラ

- [ ] `Vm\Index` — 全 VM 一覧（Proxmox API + `vm_metas` JOIN）
- [ ] `Vm\Create` — VM 作成画面（テンプレート VM 選択、テナント選択）
- [ ] `Vm\Store` — VM 作成処理（`VmService::provisionVm`）
- [ ] `Vm\Show` — VM 詳細（リアルタイム CPU/メモリ、操作ボタン）
- [ ] `Vm\Start` — VM 起動
- [ ] `Vm\Stop` — VM 停止
- [ ] `Vm\Reboot` — VM 再起動
- [ ] `Vm\ForceStop` — 強制停止（admin のみ）
- [ ] `Vm\Destroy` — VM 削除（admin のみ）
- [ ] `Vm\Console` — noVNC コンソール（`getVncProxy` + WebSocket プロキシ）
- [ ] `Vm\Snapshot` — スナップショット作成
- [ ] `Vm\Resize` — ディスクリサイズ

### 4-5. 非同期ステータス API（内部用）

- [ ] `Api\VmStatus` — `GET /api/vms/{vmid}/status`（AJAX ポーリング用）
- [ ] `Api\NodeStatus` — `GET /api/nodes/status`（ダッシュボード用）

### 4-6. View

- [ ] `resources/views/vms/index.blade.php`（Flux テーブル、ステータスバッジ、フィルタ）
- [ ] `resources/views/vms/create.blade.php`（作成フォーム）
- [ ] `resources/views/vms/show.blade.php`（VM 詳細、操作ボタン、スナップショット一覧）
- [ ] `resources/views/vms/console.blade.php`（noVNC 埋め込み）
- [ ] Blade コンポーネント `vm-status-badge.blade.php`
- [ ] Blade コンポーネント `resource-meter.blade.php`

### 4-7. ルーティング

- [ ] VM CRUD + アクション系ルートを `routes/web.php` に追加
- [ ] 内部 API ルートを `routes/web.php` 内 `/api` プレフィックスで追加

### 4-8. テスト

- [ ] Feature: VM 一覧に Proxmox API レスポンスが反映されること（Http::fake）
- [ ] Feature: VM 作成フロー（クローン → Cloud-init → 起動 の各 API コール確認）
- [ ] Feature: VM 起動/停止 API コール
- [ ] Feature: テナントメンバーが他テナントの VM にアクセスできないこと
- [ ] Unit: `CloudInitBuilder` の出力 YAML が正しい構造を持つこと
- [ ] Unit: `VmService::listAllVms()` が複数ノードのレスポンスを正しく集約すること

---

## 完了条件

- 管理画面から VM を作成でき、Proxmox にクローン・Cloud-init 適用のリクエストが送ること（Http::fake で確認）
- VNC コンソール画面が `vncproxy` API レスポンスを使って描画できること
- `php artisan test --compact` で全テストがパス
- `vendor/bin/pint --dirty --format agent` でスタイル違反なし
