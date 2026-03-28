# Phase 2: Lib/Proxmox — 自作 Proxmox API クライアント

## 概要

Proxmox VE の REST API を直接呼び出す自作ライブラリ (`App\Lib\Proxmox`) を実装する。  
外部ライブラリに依存せず、HTTP レベルで API トークン認証を行う。

## 現在の判定（2026-03-28）

✅ 完了

`Client` / `Resources` / `DataObjects` / 例外 / `ProxmoxApi` / DI バインディング / Phase2 テストまで実装済み。

---

## チェックポイント

### 2-1. HTTP クライアント基盤 (`Client.php`)

- [ ] `App\Lib\Proxmox\Client` クラス作成
  - コンストラクタ：`string $host, string $tokenId, string $tokenSecret`
  - `baseUrl` = `https://{host}:8006/api2/json`
  - リクエスト共通ヘッダ：`Authorization: PVEAPIToken={tokenId}={tokenSecret}`
  - TLS 証明書検証はオフ切替可能 (dev/prod 対応)
- [ ] `get(string $path, array $params = []): array` 実装
- [ ] `post(string $path, array $data = []): array` 実装
- [ ] `put(string $path, array $data = []): array` 実装
- [ ] `delete(string $path): array` 実装
- [ ] `App\Lib\Proxmox\Exceptions\ProxmoxApiException` 作成（HTTP 4xx/5xx）
- [ ] `App\Lib\Proxmox\Exceptions\ProxmoxAuthException` 作成（HTTP 401/403）

### 2-2. DataObject クラス

- [ ] `VmConfig` DataObject（`vmid`, `name`, `cores`, `memory`, `disks`, `nets` 等）
- [ ] `VmStatus` DataObject（`status`, `cpus`, `maxcpu`, `mem`, `maxmem`, `uptime`, `pid`）
- [ ] `NodeStatus` DataObject（`node`, `status`, `cpu`, `mem`, `maxmem`, `disk`, `maxdisk`）
- [ ] `StorageInfo` DataObject（`storage`, `type`, `avail`, `total`, `used`）

### 2-3. Cluster リソース (`Resources/Cluster.php`)

- [ ] `getClusterStatus(): array`
- [ ] `getResources(string $type = ''): array`
- [ ] `listVnets(): array`
- [ ] `createVnet(array $params): array`
- [ ] `deleteVnet(string $vnet): array`
- [ ] `createSubnet(string $vnet, array $params): array`
- [ ] `listZones(): array`
- [ ] `applySdn(): array`

### 2-4. Node リソース (`Resources/Node.php`)

- [ ] `listNodes(): array`
- [ ] `getNodeStatus(string $node): NodeStatus`

### 2-5. Storage リソース (`Resources/Storage.php`)

- [ ] `listStorage(string $node): array`
- [ ] `listStorageContent(string $node, string $storage): array`

### 2-6. Network リソース (`Resources/Network.php`)

- [ ] `listNetworks(string $node): array`

### 2-7. VM リソース (`Resources/Vm.php`)

- [ ] `listVms(string $node): array`
- [ ] `getVmConfig(string $node, int $vmid): VmConfig`
- [ ] `updateVmConfig(string $node, int $vmid, array $params): array`
- [ ] `createVm(string $node, array $params): array`
- [ ] `deleteVm(string $node, int $vmid): array`
- [ ] `getVmStatus(string $node, int $vmid): VmStatus`
- [ ] `startVm(string $node, int $vmid): string` (UPID 返却)
- [ ] `stopVm(string $node, int $vmid): string`
- [ ] `rebootVm(string $node, int $vmid): string`
- [ ] `forceStopVm(string $node, int $vmid): string`
- [ ] `cloneVm(string $node, int $vmid, array $params): string`
- [ ] `resizeVm(string $node, int $vmid, string $disk, string $size): array`
- [ ] `listSnapshots(string $node, int $vmid): array`
- [ ] `createSnapshot(string $node, int $vmid, string $name): string`
- [ ] `getVncProxy(string $node, int $vmid): array`
- [ ] `regenerateCloudinit(string $node, int $vmid): array`
- [ ] タスクポーリングヘルパー `waitForTask(string $node, string $upid, int $timeout = 60): bool`

### 2-8. 統合エントリポイント (`ProxmoxApi.php`)

- [ ] `ProxmoxApi` クラス作成（`Client` を受け取りリソースを束ねる）
  - `cluster(): Cluster`
  - `node(): Node`
  - `storage(): Storage`
  - `network(): Network`
  - `vm(): Vm`

### 2-9. サービスプロバイダ / ファサード的な統合

- [ ] `AppServiceProvider` に Proxmox クライアントのバインディング追加
  - `ProxmoxNode` モデルの `is_active` なノード設定を読み込み
  - シングルトンまたはリクエストスコープで解決

### 2-10. テスト

- [ ] `Client` の HTTP リクエスト構造の単体テスト（Http::fake を使用）
- [ ] Proxmox API エラー (401, 500) 時の例外送出テスト
- [ ] `Vm::getVmStatus()` マッピングの単体テスト
- [ ] `waitForTask()` ポーリング挙動の単体テスト

---

## 完了条件

- `php artisan tinker` で `ProxmoxApi` が解決でき、`listNodes()` が呼び出せること（テスト環境 mock で確認）
- `php artisan test --compact` で全テストがパス
- `vendor/bin/pint --dirty --format agent` でスタイル違反なし
