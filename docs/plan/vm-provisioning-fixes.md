# VM プロビジョニング 実装差異・修正計画

## 概要

ドキュメント（`detailed-design.md` セクション 5・`database-design.md` セクション 2・`api-design.md`）と
実装コードを照合した結果、以下の差異が確認された。
本ドキュメントは修正項目と対応方針をまとめる。

調査日: 2026-03-29  
実装完了日: 2026-03-29

---

## 差異一覧

| # | 分類 | 対象ファイル | 深刻度 | 状態 |
|---|------|------------|--------|------|
| 1 | リダイレクト先 | `app/Http/Controllers/Vm/StoreController.php` | 低 | ✅ 完了 |
| 2 | ジョブ引数の設計乖離 | `app/Jobs/ProvisionVmJob.php` / `app/Data/Vm/ProvisionVmCommand.php` | 低 | ✅ 完了 |
| 3 | `vm_metas` INSERT が非同期ジョブ内で実行される | `app/Services/VmService.php` | 中 | ✅ 完了 |
| 4 | network-config.yaml / meta-data.yaml 未生成 | `app/Services/VmService.php` | **高** | ✅ 完了 |
| 5 | `updateVmConfig` に NIC（net0/net1）設定なし | `app/Services/VmService.php` | **高** | ✅ 完了 |
| 6 | フォーム・Request・Command にネットワーク項目なし | `CreateVmRequest` / `ProvisionVmCommand` / `create.blade.php` | **高** | ✅ 完了 |
| 7 | プロビジョニングのステップ実行順序がドキュメントとずれている | `app/Services/VmService.php` | 中 | ✅ 完了 |
| 8 | `vm_metas` マイグレーション / Model にネットワークカラムがない | `database/migrations/` / `VmMeta.php` / `VmMetaData.php` | **高** | ✅ 完了 |
| 9 | Cloud-init user-data の内容がドキュメント対比で大幅に不足 | `app/Services/CloudInit/CloudInitBuilder.php` | **高** | ✅ 完了 |
| 10 | `VmStatus::Uploading` 追加に DB マイグレーション変更が必要 | `database/migrations/` / `VmStatus.php` | 中 | ✅ 完了 |
| 11 | `VmMetaData` / `VmDetailResponseData` / `VmMetaFactory` にネットワーク項目なし | `app/Data/Vm/` / `database/factories/` | 中 | ✅ 完了 |
| 12 | VM 詳細画面にネットワーク情報の表示がない | `resources/views/vms/show.blade.php` | 低 | ✅ 完了 |

---

## 差異詳細と修正方針

### 差異 1 — StoreController のリダイレクト先

#### 現行実装（`app/Http/Controllers/Vm/StoreController.php`）

```php
public function __invoke(CreateVmRequest $request): RedirectResponse
{
    $validated = $request->validated();
    $tenant = $this->tenantRepository->findByIdOrFail($request->integer('tenant_id'));

    $command = ProvisionVmCommand::make($validated);
    ProvisionVmJob::dispatch($command);

    return redirect()->route('vms.index')   // ← 問題箇所：一覧画面へ飛ぶ
        ->with('success', 'VMのプロビジョニングを開始しました。');
}
```

#### ドキュメント設計

> 作成完了後は VM 詳細画面 (`/vms/{vmid}`) へリダイレクトし、プロビジョニング進捗をポーリングで表示する。

#### 問題点

- 一覧画面にリダイレクトしても対象 VM が表示されるが、詳細画面でプロビジョニング進捗を確認できない。
- 差異3（`vm_metas` INSERT がジョブ内）と連動している。現行は Controller が `vmid` を持てないため `vms.index` に飛ばしているが、差異3を解消すれば Controller で `vmid` が取得でき、`vms.show` へのリダイレクトが可能になる。

#### 修正後のイメージ

```php
public function __invoke(CreateVmRequest $request): RedirectResponse
{
    $validated = $request->validated();
    $tenant = $this->tenantRepository->findByIdOrFail($request->integer('tenant_id'));

    // 同期で VmMeta を作成し、vmid を取得してからジョブをディスパッチ（差異3の修正後）
    $vmMeta = $this->vmService->createVmMeta($tenant, ProvisionVmCommand::make($validated));
    ProvisionVmJob::dispatch($vmMeta->getId());

    return redirect()->route('vms.show', $vmMeta->getProxmoxVmid())
        ->with('success', 'VMのプロビジョニングを開始しました。');
}
```

---

### 差異 2 — ジョブ引数の構造

#### 現行実装

`ProvisionVmJob` のコンストラクタが `ProvisionVmCommand` をそのまま受け取っている。

```php
// app/Jobs/ProvisionVmJob.php
final class ProvisionVmJob implements ShouldQueue
{
    public function __construct(
        private ProvisionVmCommand $command,   // ← DTO全体を渡している
    ) { ... }

    public function handle(TenantRepository $tenantRepository, VmService $vmService): void
    {
        $tenant = $tenantRepository->findByIdOrFail($this->command->getTenantId());
        $vmService->provisionVm($tenant, $this->command->toArray());
    }
}
```

#### ドキュメント設計

> `ProvisionVmJob` は `vm_meta_id` を引数に取り、ジョブ内で `VmMeta` レコードを取得して処理する。  
> これにより、ジョブのリトライ時にも最新の状態を参照できる。

#### 問題点

- `ProvisionVmCommand` はすべてのパラメータをシリアライズしてキューに書き込む。ジョブが大きくなる。
- より重要なのは差異3と複合した問題：ジョブ内で `vm_metas` を INSERT しているため、ジョブ投入直後に詳細画面へリダイレクトできない。

#### 修正後のイメージ

```php
final class ProvisionVmJob implements ShouldQueue
{
    public function __construct(
        private int $vmMetaId,   // ← VmMeta の ID だけ渡す
    ) { ... }

    public function handle(VmMetaRepository $vmMetaRepository, VmService $vmService): void
    {
        $vmMeta = $vmMetaRepository->findByIdOrFail($this->vmMetaId);
        $vmService->executeProvisioning($vmMeta);
    }
}
```

---

### 差異 3 — `vm_metas` INSERT タイミング

#### 現行実装（`app/Services/VmService.php` `provisionVm()` 内）

```php
// ジョブ内（非同期コンテキスト）で DB INSERT している
public function provisionVm(TenantData $tenant, array $params): VmMetaData
{
    $this->ensureProxmoxApiConfigured();

    $vmMetaData = DB::transaction(fn () => $this->vmMetaRepository->create([
        'tenant_id'            => $tenant->getId(),
        'proxmox_vmid'         => $params['new_vmid'],
        'proxmox_node'         => $params['node'],
        'purpose'              => $params['purpose'] ?? null,
        'label'                => $params['label'],
        'provisioning_status'  => VmStatus::Pending,
    ]));
    // ...以降 Proxmox 操作
```

このメソッドが `ProvisionVmJob::handle()` から呼ばれているため、Controller がジョブをディスパッチした時点では `vm_metas` レコードがまだ存在しない。

#### ドキュメント設計

> 1. `VmService::provisionVm()` がリクエストの同期処理として呼ばれ、`vm_metas` レコードを `status=pending` で作成する。
> 2. 作成した `vm_meta_id` を引数にした `ProvisionVmJob` をディスパッチする。
> 3. `StoreController` は `VmMeta` の `proxmox_vmid` を使って詳細画面へリダイレクトする。

#### 問題点

- Controller がジョブをディスパッチした直後、`vms.show` へリダイレクトしても対象 `vm_metas` レコードが存在していないため 404 になる。
- 現行は `vms.index` へのリダイレクト（差異1）で回避しているが、これは根本的な問題を隠している。

#### 修正方針

`VmService` を **同期的な前処理** と **非同期ジョブ用の処理** に分離する。

```php
// 同期処理（Controller から呼ぶ）
public function createVmMeta(TenantData $tenant, ProvisionVmCommand $command): VmMetaData
{
    return DB::transaction(fn () => $this->vmMetaRepository->create([
        'tenant_id'           => $tenant->getId(),
        'proxmox_vmid'        => $command->getNewVmid(),
        'proxmox_node'        => $command->getNode(),
        'label'               => $command->getLabel(),
        'purpose'             => $command->getPurpose(),
        'ip_address'          => $command->getIpAddress(),
        'gateway'             => $command->getGateway(),
        'vnet_name'           => $command->getVnetName(),
        'shared_ip_address'   => $command->getSharedIpAddress(),
        'ssh_keys'            => $command->getSshKeys(),
        'provisioning_status' => VmStatus::Pending,
    ]));
}

// 非同期ジョブの本処理（ProvisionVmJob から呼ぶ）
public function executeProvisioning(VmMetaData $vmMeta): void
{
    // Cloud-init 生成 → スニペット保存 → クローン → 設定 → 起動
}
```

---

### 差異 4 — network-config.yaml / meta-data.yaml 未生成 ⚠️ 高優先度

#### 現行実装（`app/Services/VmService.php` `buildCloudInitUserData()`）

```php
private function buildCloudInitUserData(TenantData $tenant, array $params): string
{
    $hostname = (string) ($params['label'] ?? ('vm-' . $params['new_vmid']));

    return implode("\n", [
        '#cloud-config',
        'hostname: ' . $hostname,
        'fqdn: ' . $hostname . '.' . $tenant->getSlug() . '.local',
        'manage_etc_hosts: true',
        '',
    ]);
}
```

生成するのは `user-data` のみ。しかも内容は hostname / fqdn だけで、SSH鍵・パッケージ等は入っていない。

また `uploadVmSnippet` の呼び出しも `$userData` だけ渡しており、`$networkConfig` / `$metaData` は `null` のまま：

```php
$this->uploadVmSnippet(
    $params['node'],
    $newVmid,
    $this->buildCloudInitUserData($tenant, $params)
    // networkConfig と metaData は渡していない（null）
);
```

`SnippetClient::upload()` は既に3引数を受け付けているが活用されていない：

```php
// app/Lib/Snippet/SnippetClient.php
public function upload(
    int $vmId,
    string $userData,
    ?string $networkConfig = null,   // ← null のまま渡されている
    ?string $metaData = null,        // ← null のまま渡されている
): void
```

#### ドキュメント設計

`detailed-design.md` セクション 5.2 より：

| ファイル名 | 役割 | 内容 |
|-----------|------|------|
| `vm-{vmid}-user-data.yaml` | cloud-config | パッケージ、SSH鍵、ユーザ設定 |
| `vm-{vmid}-network-config.yaml` | network-config | 静的 IP / ゲートウェイ / DNS |
| `vm-{vmid}-meta-data.yaml` | meta-data | instance-id, local-hostname |

network-config の設計：

```yaml
version: 2
ethernets:
  eth0:                                   # テナント内部ネット
    addresses: ["10.{tenant_id}.0.{host}/24"]
    gateway4: "10.{tenant_id}.0.1"
    nameservers:
      addresses: [1.1.1.1, 8.8.8.8]
  eth1:                                   # shared_ip がある場合のみ
    addresses: ["{shared_ip}/32"]
```

meta-data の設計：

```yaml
instance-id: vm-{vmid}
local-hostname: {hostname}
```

#### 修正方針

1. `app/Services/CloudInit/CloudInitBuilder.php` を新規作成する。

```php
namespace App\Services\CloudInit;

final class CloudInitBuilder
{
    /** cloud-config YAML（user-data）を生成する */
    public function buildUserData(string $hostname, string $fqdn, ?string $sshKeys = null): string

    /** network-config YAML を生成する */
    public function buildNetworkConfig(
        string $ipCidr,            // 例: "10.1.0.10/24"
        string $gateway,           // 例: "10.1.0.1"
        ?string $sharedIp = null,  // 例: "203.0.113.5"
    ): string

    /** meta-data YAML を生成する */
    public function buildMetaData(int $vmId, string $hostname): string
}
```

2. `VmService::buildCloudInitUserData()` を `CloudInitBuilder` を使う形に置き換え、3ファイルを同時に生成する。

3. `uploadVmSnippet()` に `$networkConfig` と `$metaData` を渡すよう変更する。

4. `cicustom` のパスに `network=` と `meta=` を追加する（差異5と合わせて対応）：

```php
'cicustom' => sprintf(
    'user=%1$s:snippets/vm-%2$d-user-data.yaml,network=%1$s:snippets/vm-%2$d-network-config.yaml,meta=%1$s:snippets/vm-%2$d-meta-data.yaml',
    config('services.proxmox.snippet_storage', 'local'),
    $vmid,
),
```

---

### 差異 5 — updateVmConfig に NIC 設定なし ⚠️ 高優先度

#### 現行実装（`app/Services/VmService.php` `provisionVm()` 内）

```php
$config = array_filter([
    'cores'    => $params['cpu'] ?? null,
    'memory'   => $params['memory_mb'] ?? null,
    'cicustom' => sprintf(
        'user=%s:snippets/%s',
        (string) config('services.proxmox.snippet_storage', 'local'),
        $snippetFilename,
    ),
]);

if (!empty($config)) {
    $this->api->vm()->updateVmConfig($params['node'], (int) $params['new_vmid'], $config);
}
```

`net0`（テナント VNet へのブリッジ）と `net1`（shared_ip 用）が含まれていない。
また `cicustom` が `user=` のみで `network=` と `meta=` が欠けている。

#### ドキュメント設計（`detailed-design.md` セクション 5.2）

```
cores:    {cpu}
memory:   {memory_mb}
net0:     virtio,bridge=vnet_{tenantId}
net1:     virtio,bridge=vmbr1               # shared_ip がある場合のみ
cicustom: user=local:snippets/vm-{vmid}-user-data.yaml,
          network=local:snippets/vm-{vmid}-network-config.yaml,
          meta=local:snippets/vm-{vmid}-meta-data.yaml
```

#### 問題点

- `net0` を設定しないと VM がテナントの VNet に接続されないため、テナント間の通信分離が成立しない。
- `net1` を設定しないと共有 IP アドレスの付与ができない。
- `cicustom` に `network=` と `meta=` がないと、差異4で生成したファイルが Cloud-init に読み込まれない。

#### 修正後のイメージ

```php
$storage = config('services.proxmox.snippet_storage', 'local');
$config = [
    'cores'    => $params['cpu'] ?? 2,
    'memory'   => $params['memory_mb'] ?? 2048,
    'net0'     => 'virtio,bridge=vnet_' . $params['vnet_name'],   // ← 必須追加
    'cicustom' => sprintf(
        'user=%1$s:snippets/vm-%2$d-user-data.yaml,network=%1$s:snippets/vm-%2$d-network-config.yaml,meta=%1$s:snippets/vm-%2$d-meta-data.yaml',
        $storage,
        $vmid,
    ),
];

// shared_ip がある場合のみ net1 を追加
if (!empty($params['shared_ip_address'])) {
    $config['net1'] = 'virtio,bridge=vmbr1';
}

$this->api->vm()->updateVmConfig($params['node'], $vmid, $config);
```

---

### 差異 6 — フォーム・Request・Command にネットワーク項目がない ⚠️ 高優先度

#### 現行実装

**`app/Http/Requests/Vm/CreateVmRequest.php`** — バリデーションルール：

```php
return [
    'tenant_id'    => ['required', 'integer', 'exists:tenants,id'],
    'label'        => ['required', 'string', 'max:255'],
    'cpu'          => ['nullable', 'integer', 'min:1', 'max:64'],
    'memory_mb'    => ['nullable', 'integer', 'min:512'],
    'disk_gb'      => ['nullable', 'integer', 'min:1'],
    'template_vmid'=> ['required', 'integer'],
    'node'         => ['required', 'string', 'max:255'],
    'new_vmid'     => ['required', 'integer'],
    'purpose'      => ['nullable', 'string', 'max:255'],
    // ip_address, gateway, vnet_name, shared_ip_address, ssh_keys がない
];
```

**`app/Data/Vm/ProvisionVmCommand.php`** — プロパティ一覧：

```php
final readonly class ProvisionVmCommand
{
    private function __construct(
        private int $tenantId,
        private string $label,
        private int $templateVmid,
        private string $node,
        private int $newVmid,
        private ?int $cpu,
        private ?int $memoryMb,
        private ?int $diskGb,
        private ?string $purpose,
        // ip_address, gateway, vnet_name, shared_ip_address, ssh_keys がない
    ) {}
```

**`resources/views/vms/create.blade.php`** — フォームフィールド：
`tenant_id` / `label` / `template_vmid` / `node` / `new_vmid` / `cpu` / `memory_mb` / `disk_gb` / `purpose` のみ。IP・ゲートウェイ・VNet・SSH鍵の入力欄がない。

#### ドキュメント設計（`detailed-design.md` セクション 5.2 VM 作成パラメータ）

> **注意:** 現行の `database-design.md` `vm_metas` テーブル定義には以下のカラムが含まれて **いない**。
> `shared_ip_address` のみ存在し、`ip_address` / `gateway` / `vnet_name` / `ssh_keys` は未定義。
> 差異 8 で `database-design.md` の更新とマイグレーション追加が必要。

`detailed-design.md` セクション 5.2 では以下がVM作成パラメータとして定義されている：

| パラメータ | 型 | 必須 | 説明 |
|----------|------|------|------|
| `ip_address` | string | ○ | テナント VNet 内の固定 IP（例: `10.1.0.10`） |
| `gateway` | string | ○ | デフォルトゲートウェイ（例: `10.1.0.1`） |
| `vnet_name` | string | ○ | テナント VNet 名（SDN） |
| `shared_ip_address` | string | ― | VM Network IP（172.26.27.x, Dual NIC 時） |
| `ssh_keys` | text | △ | SSH 公開鍵（複数行可） |

#### 修正方針

**`CreateVmRequest.php`** にルール追加：

```php
'ip_address'        => ['required', 'ip'],
'gateway'           => ['required', 'ip'],
'vnet_name'         => ['required', 'string', 'max:64'],
'shared_ip_address' => ['nullable', 'ip'],
'ssh_keys'          => ['nullable', 'string'],
```

**`ProvisionVmCommand.php`** にプロパティとゲッター追加：

```php
private function __construct(
    // ...既存プロパティ...
    private string $ipAddress,
    private string $gateway,
    private string $vnetName,
    private ?string $sharedIpAddress,
    private ?string $sshKeys,
) {}
```

`make()` と `toArray()` も対応するフィールドで更新する。

**`create.blade.php`** にフォームフィールド追加（ネットワーク設定セクションとして分離）：

```blade
<flux:heading size="lg">ネットワーク設定</flux:heading>

<div class="grid grid-cols-2 gap-4">
    <flux:field>
        <flux:label>IP アドレス</flux:label>
        <flux:input name="ip_address" value="{{ old('ip_address', $prefill['ip_address'] ?? '') }}" placeholder="10.1.0.10" />
        <flux:error name="ip_address" />
    </flux:field>

    <flux:field>
        <flux:label>ゲートウェイ</flux:label>
        <flux:input name="gateway" value="{{ old('gateway', $prefill['gateway'] ?? '') }}" placeholder="10.1.0.1" />
        <flux:error name="gateway" />
    </flux:field>
</div>

<flux:field>
    <flux:label>VNet 名</flux:label>
    <flux:input name="vnet_name" value="{{ old('vnet_name', $prefill['vnet_name'] ?? '') }}" placeholder="vnet_1" />
    <flux:error name="vnet_name" />
</flux:field>

<flux:field>
    <flux:label>共有 IP アドレス（オプション）</flux:label>
    <flux:input name="shared_ip_address" value="{{ old('shared_ip_address', $prefill['shared_ip_address'] ?? '') }}" placeholder="203.0.113.5" />
    <flux:error name="shared_ip_address" />
</flux:field>

<flux:field>
    <flux:label>SSH 公開鍵（オプション）</flux:label>
    <flux:textarea name="ssh_keys" placeholder="ssh-rsa AAAA...">{{ old('ssh_keys', $prefill['ssh_keys'] ?? '') }}</flux:textarea>
    <flux:error name="ssh_keys" />
</flux:field>
```

また `$prefill` 配列にも上記フィールドを追加する（`@php` ブロック内）。

---

### 差異 7 — プロビジョニングのステップ実行順序

#### 現行実装の順序（`app/Services/VmService.php` `provisionVm()`）

```
1.  DB::transaction → vm_metas INSERT (status=pending)
2.  UPDATE status=cloning
3.  cloneVm()            ← ★スニペット保存より前にクローン
4.  waitForTask()
5.  UPDATE status=configuring
6.  buildCloudInitUserData() + uploadVmSnippet()   ← ★クローン後にスニペット
7.  updateVmConfig() (cores / memory / cicustom のみ、NIC なし)
8.  resizeVm() (disk_gb が指定された場合)
9.  UPDATE status=starting
10. startVm()
11. waitForTask()
12. UPDATE status=ready
```

#### ドキュメント設計の順序

```
1.  CloudInit 生成 (user-data / network-config / meta-data)
2.  uploadVmSnippet()    ← ★クローン前にスニペット保存
3.  cloneVm()
4.  waitForTask()
5.  updateVmConfig() (NIC + cicustom 完全版)
6.  resizeVm()
7.  startVm()
8.  waitForTask()
```

#### 問題点

- スニペット保存をクローン後に行うと、VM の Cloud-init が初回起動時に参照できないリスクがある（スニペット保存が失敗した場合、すでにクローンされた不完全な VM が残る）。
- ドキュメントの設計意図は「スニペットが確実に存在する状態でクローンし、VM 起動時に Cloud-init が確実に読み込まれる」こと。

#### 修正後の順序（`executeProvisioning()` として再実装）

```
1.  UPDATE status=uploading   ← 新しい status
2.  CloudInitBuilder で3ファイル生成
3.  uploadVmSnippet (user-data + network-config + meta-data)
4.  UPDATE status=cloning
5.  cloneVm()
6.  waitForTask()
7.  UPDATE status=configuring
8.  updateVmConfig() (NIC + cicustom 完全版)
9.  resizeVm()
10. UPDATE status=starting
11. startVm()
12. waitForTask()
13. UPDATE status=ready
```

`VmStatus` enum に `Uploading` ケースを追加し、フロントエンドの進捗表示（`vms.show` ポーリング）にも反映させる。

---

### 差異 8 — `vm_metas` マイグレーション / Model にネットワークカラムがない ⚠️ 高優先度

#### 現行実装（`database/migrations/2026_03_28_000006_create_vm_metas_table.php`）

```php
Schema::create('vm_metas', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('proxmox_vmid')->unique();
    $table->string('proxmox_node', 50);
    $table->string('purpose', 50)->nullable();
    $table->string('label')->nullable();
    $table->string('shared_ip_address', 45)->nullable();
    $table->enum('provisioning_status', [...]);
    $table->text('provisioning_error')->nullable();
    // ip_address, gateway, vnet_name, ssh_keys が存在しない
});
```

`VmMeta` Model の `#[Fillable]` にも `ip_address` / `gateway` / `vnet_name` / `ssh_keys` が含まれていない。

#### ドキュメント設計

- `detailed-design.md` セクション 5.2: `ip_address`, `gateway`, `vnet_name`, `ssh_keys` は VM 作成の必須/任意パラメータとして定義
- `database-design.md`: **これらのカラムは未記載** — `shared_ip_address` のみ定義
- 差異 3 の修正方針（`createVmMeta()` で同期 INSERT + `executeProvisioning()` で ID のみ受け取る）は、これらのカラムが `vm_metas` テーブルに存在することを前提としている

#### 問題点

- 差異 3/6/4/5 の修正方針がすべて `vm_metas` にネットワーク情報が保存されることを前提としるため、マイグレーションが欠落していると他の差異修正が成立しない。
- `ProvisionVmJob` が `int $vmMetaId` のみを引数に取る設計では、ジョブ実行時に `ip_address` / `gateway` / `vnet_name` を DB から復元できないと Cloud-init や NIC 設定が行えない。

#### 修正方針

1. **`database-design.md` の `vm_metas` テーブル定義を更新**し、以下のカラムを追加する：

| カラム | 型 | NULL | デフォルト | 説明 |
|--------|------|------|----------|------|
| `ip_address` | VARCHAR(45) | YES | NULL | テナント VNet 内 IP |
| `gateway` | VARCHAR(45) | YES | NULL | デフォルトゲートウェイ |
| `vnet_name` | VARCHAR(64) | YES | NULL | Proxmox SDN VNet 名 |
| `ssh_keys` | TEXT | YES | NULL | SSH 公開鍵 |

> 注: `detailed-design.md` では `ip_address` / `gateway` / `vnet_name` は NOT NULL だが、
> 既存の `vm_metas` レコード（DBaaS 用など）との互換性を考慮し、マイグレーションでは `nullable` とする。
> バリデーションで必須チェックを行い、DB 制約ではなくアプリケーション層で NOT NULL を保証する。

2. **新規マイグレーション** を作成する：

```php
// database/migrations/xxxx_xx_xx_xxxxxx_add_network_columns_to_vm_metas_table.php
return new class() extends Migration {
    public function up(): void
    {
        Schema::table('vm_metas', function (Blueprint $table): void {
            $table->string('ip_address', 45)->nullable()->after('shared_ip_address');
            $table->string('gateway', 45)->nullable()->after('ip_address');
            $table->string('vnet_name', 64)->nullable()->after('gateway');
            $table->text('ssh_keys')->nullable()->after('vnet_name');
        });
    }

    public function down(): void
    {
        Schema::table('vm_metas', function (Blueprint $table): void {
            $table->dropColumn(['ip_address', 'gateway', 'vnet_name', 'ssh_keys']);
        });
    }
};
```

3. **`VmMeta` Model** の `#[Fillable]` に追加：

```php
#[Fillable(['tenant_id', 'proxmox_vmid', 'proxmox_node', 'purpose', 'label',
            'shared_ip_address', 'ip_address', 'gateway', 'vnet_name', 'ssh_keys',
            'provisioning_status', 'provisioning_error'])]
```

---

### 差異 9 — Cloud-init user-data の内容がドキュメント対比で大幅に不足 ⚠️ 高優先度

#### 現行実装（`app/Services/VmService.php` `buildCloudInitUserData()`）

```php
private function buildCloudInitUserData(TenantData $tenant, array $params): string
{
    $hostname = (string) ($params['label'] ?? ('vm-' . $params['new_vmid']));
    return implode("\n", [
        '#cloud-config',
        'hostname: ' . $hostname,
        'fqdn: ' . $hostname . '.' . $tenant->getSlug() . '.local',
        'manage_etc_hosts: true',
        '',
    ]);
}
```

生成されるのは hostname / fqdn / manage_etc_hosts の 3 行のみ。

#### 差異 4 の修正方針で記載した CloudInitBuilder

差異 4 では `CloudInitBuilder::buildUserData(string $hostname, string $fqdn, ?string $sshKeys)` としているが、
ドキュメントではさらに多くの項目が user-data に含まれる。

#### ドキュメント設計（`detailed-design.md` セクション 5.1-5.2）

VM の cloud-config (user-data) には以下が含まれるべき：

| 項目 | ドキュメント記載 | 現行実装 |
|------|----------------|----------|
| hostname / fqdn | ○ | ○（実装済み） |
| manage_etc_hosts | ○ | ○（実装済み） |
| SSH 公開鍵の注入 | ○（`ssh_authorized_keys`） | ✗ |
| パッケージインストール | ○（`qemu-guest-agent` 等） | ✗ |
| 運用ユーザ作成 | ○（`users` セクション） | ✗ |
| DNS リゾルバ設定 | ○（`resolv_conf` → CoreDNS 参照） | ✗ |
| タイムゾーン設定 | ○（`timezone: Asia/Tokyo`） | ✗ |

#### 修正方針

差異 4 の `CloudInitBuilder::buildUserData()` のシグネチャと内容を拡充する：

```php
final class CloudInitBuilder
{
    /**
     * cloud-config YAML（user-data）を生成する。
     *
     * @param array{hostname: string, fqdn: string, ssh_keys: ?string,
     *              dns_servers: string[], packages: string[],
     *              timezone: string} $config
     */
    public function buildUserData(array $config): string
    {
        $yaml = [
            '#cloud-config',
            'hostname: ' . $config['hostname'],
            'fqdn: ' . $config['fqdn'],
            'manage_etc_hosts: true',
            'timezone: ' . ($config['timezone'] ?? 'Asia/Tokyo'),
        ];

        // パッケージ
        if (!empty($config['packages'])) {
            $yaml[] = 'packages:';
            foreach ($config['packages'] as $pkg) {
                $yaml[] = '  - ' . $pkg;
            }
        }

        // SSH 鍵
        if (!empty($config['ssh_keys'])) {
            $yaml[] = 'ssh_authorized_keys:';
            foreach (explode("\n", trim($config['ssh_keys'])) as $key) {
                $key = trim($key);
                if ($key !== '') {
                    $yaml[] = '  - ' . $key;
                }
            }
        }

        // DNS リゾルバ
        if (!empty($config['dns_servers'])) {
            $yaml[] = 'manage_resolv_conf: true';
            $yaml[] = 'resolv_conf:';
            $yaml[] = '  nameservers:';
            foreach ($config['dns_servers'] as $ns) {
                $yaml[] = '    - ' . $ns;
            }
        }

        $yaml[] = '';
        return implode("\n", $yaml);
    }

    // buildNetworkConfig() / buildMetaData() は差異4に記載済み
}
```

デフォルトで注入するパッケージ（例）：

```php
$defaultPackages = ['qemu-guest-agent'];
```

DNS サーバは設定で管理し、`config('services.dns.resolvers', ['1.1.1.1', '8.8.8.8'])` から取得する。

---

### 差異 10 — `VmStatus::Uploading` 追加に DB マイグレーション変更が必要

#### 問題点

差異 7 の修正方針で `VmStatus::Uploading` を追加するとしているが、`vm_metas` テーブルの `provisioning_status` カラムは `ENUM` 型で定義されており、新しい値を追加するにはマイグレーションが必要。

```php
// 現行マイグレーション
$table->enum('provisioning_status', ['pending', 'cloning', 'configuring', 'starting', 'ready', 'error'])
    ->default('pending');
```

#### 修正方針

差異 8 のマイグレーション（ネットワークカラム追加）と同じマイグレーションファイル内で ENUM カラムも更新する：

```php
public function up(): void
{
    Schema::table('vm_metas', function (Blueprint $table): void {
        // ネットワークカラム追加（差異 8）
        $table->string('ip_address', 45)->nullable()->after('shared_ip_address');
        $table->string('gateway', 45)->nullable()->after('ip_address');
        $table->string('vnet_name', 64)->nullable()->after('gateway');
        $table->text('ssh_keys')->nullable()->after('vnet_name');
    });

    // provisioning_status ENUM に 'uploading' を追加
    DB::statement("ALTER TABLE vm_metas MODIFY COLUMN provisioning_status ENUM('pending','uploading','cloning','configuring','starting','ready','error') DEFAULT 'pending'");
}
```

`VmStatus` enum の更新：

```php
enum VmStatus: string
{
    case Pending = 'pending';
    case Uploading = 'uploading';   // ← 新規追加
    case Cloning = 'cloning';
    case Configuring = 'configuring';
    case Starting = 'starting';
    case Ready = 'ready';
    case Error = 'error';
}
```

`vms/show.blade.php` のポーリング判定にも `'uploading'` を追加：

```php
$isProvisioning = in_array($provisioningStatus?->value, [
    'pending', 'uploading', 'cloning', 'configuring', 'starting'
], true);
```

---

### 差異 11 — `VmMetaData` / `VmDetailResponseData` / `VmMetaFactory` にネットワーク項目がない

#### 問題点

差異 8 でカラムを追加しても、以下のクラスを更新しなければ値がアプリケーション層に伝搬しない：

- `VmMetaData`: プロパティ、`of()` マッピング、`make()` マッピング、ゲッター、`toArray()`
- `VmDetailResponseData`: `VmMetaData` 経由で間接的に参照可能なため変更不要だが、show 画面で表示するために `VmMetaData` の更新が必須
- `VmMetaFactory`: テストで使用するため、新カラムのデフォルト値が必要

#### 修正方針

**`VmMetaData`** に以下を追加：

```php
final readonly class VmMetaData
{
    private function __construct(
        // ...既存プロパティ...
        private ?string $ipAddress,
        private ?string $gateway,
        private ?string $vnetName,
        private ?string $sshKeys,
    ) {}

    // of() に追加
    public static function of(VmMeta $model): self
    {
        return new self(
            // ...既存...
            ipAddress: $model->ip_address,
            gateway: $model->gateway,
            vnetName: $model->vnet_name,
            sshKeys: $model->ssh_keys,
        );
    }

    // ゲッター追加
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function getGateway(): ?string { return $this->gateway; }
    public function getVnetName(): ?string { return $this->vnetName; }
    public function getSshKeys(): ?string { return $this->sshKeys; }
}
```

**`VmMetaFactory`** に追加：

```php
public function definition(): array
{
    return [
        // ...既存...
        'ip_address' => fake()->ipv4(),
        'gateway' => '10.1.0.1',
        'vnet_name' => 'vnet_' . fake()->numberBetween(1, 100),
        'ssh_keys' => null,
    ];
}
```

---

### 差異 12 — VM 詳細画面にネットワーク情報の表示がない

#### 現行実装（`resources/views/vms/show.blade.php`）

```blade
<dl class="space-y-2 text-sm">
    <div class="flex justify-between"><dt class="text-zinc-500">テナント</dt><dd>{{ $meta->getTenantName() ?? '-' }}</dd></div>
    <div class="flex justify-between"><dt class="text-zinc-500">ノード</dt><dd>{{ $meta->getProxmoxNode() }}</dd></div>
    <div class="flex justify-between"><dt class="text-zinc-500">VMID</dt><dd>{{ $meta->getProxmoxVmid() }}</dd></div>
    <div class="flex justify-between"><dt class="text-zinc-500">用途</dt><dd>{{ $meta->getPurpose() ?? '-' }}</dd></div>
    <div class="flex justify-between"><dt class="text-zinc-500">IP アドレス</dt><dd>{{ $meta->getSharedIpAddress() ?? '-' }}</dd></div>
</dl>
```

「IP アドレス」として `shared_ip_address`（外部共有 IP）のみ表示され、テナント内部 IP / ゲートウェイ / VNet 名が表示されない。

#### 修正方針

ネットワーク情報の表示を追加する：

```blade
<div class="flex justify-between"><dt class="text-zinc-500">VNet</dt><dd>{{ $meta->getVnetName() ?? '-' }}</dd></div>
<div class="flex justify-between"><dt class="text-zinc-500">内部 IP</dt><dd>{{ $meta->getIpAddress() ?? '-' }}</dd></div>
<div class="flex justify-between"><dt class="text-zinc-500">ゲートウェイ</dt><dd>{{ $meta->getGateway() ?? '-' }}</dd></div>
<div class="flex justify-between"><dt class="text-zinc-500">共有 IP</dt><dd>{{ $meta->getSharedIpAddress() ?? '-' }}</dd></div>
```

---

## 修正作業の推奨順序

差異 8・10 はマイグレーション作業で、全修正の前提となるため最初に実施する。
差異 4・5・6・9・11 は密接に関連しているため一括対応を推奨する。差異 1・2・3 はセットで対応する。

**✅ 全ステップ完了（2026-03-29）** — コミット: `implement vm-provisioning-fixes: steps 0-8`

### ✅ ステップ 0: 設計書の更新（差異 8）

対象ファイル:
- `docs/database-design.md` — `vm_metas` テーブル定義に `ip_address` / `gateway` / `vnet_name` / `ssh_keys` カラムを追記

### ✅ ステップ 1: マイグレーション作成（差異 8・10）

対象ファイル:
- `database/migrations/2026_03_29_144245_add_network_columns_to_vm_metas_table.php` — 作成・適用済み
  - `ip_address` / `gateway` / `vnet_name` / `ssh_keys` カラム追加
  - `provisioning_status` ENUM に `'uploading'` を追加
- `app/Enums/VmStatus.php` — `Uploading` ケース追加済み
- `app/Models/VmMeta.php` — `#[Fillable]` にネットワークカラム追加済み

### ✅ ステップ 2: Data / Factory の更新（差異 11）

対象ファイル:
- `app/Data/Vm/VmMetaData.php` — プロパティ・`of()`・`make()`・ゲッター・`toArray()` にネットワーク項目追加済み
- `database/factories/VmMetaFactory.php` — `definition()` にネットワーク項目のデフォルト値追加済み

### ✅ ステップ 3: ネットワーク入力項目の追加（差異 6）

対象ファイル:
- `app/Http/Requests/Vm/CreateVmRequest.php` — バリデーションルール追加済み
- `app/Data/Vm/ProvisionVmCommand.php` — プロパティ・ゲッター・`make()`・`toArray()` 追加済み
- `resources/views/vms/create.blade.php` — フォームフィールド追加・`$prefill` 更新済み

### ✅ ステップ 4: CloudInitBuilder の実装（差異 4・9）

対象ファイル:
- `app/Services/CloudInit/CloudInitBuilder.php` — 完全書き換え済み
  - `buildUserData(array $config)` — hostname / fqdn / SSH 鍵 / パッケージ / DNS リゾルバ / タイムゾーン / runcmd
  - `buildNetworkConfig(string $ipCidr, string $gateway, ?string $sharedIp)` — Netplan v2 YAML
  - `buildMetaData(int $vmId, string $hostname)` — instance-id / local-hostname
  - コンストラクタに `$dnsResolvers` 注入（Unit テスト対応、`AppServiceProvider` で config から DI）
- `tests/Unit/Phase4/CloudInitBuilderTest.php` — 新 API に合わせて書き換え済み（10件パス）

### ✅ ステップ 5: NIC 設定・cicustom 完全版・スニペット3ファイル対応（差異 5・7）

対象ファイル:
- `app/Services/VmService.php`
  - `uploadVmSnippet()` へ `$networkConfig` / `$metaData` を渡すよう修正済み
  - `updateVmConfig` に `net0` / `net1` / `cicustom` 完全版（3ファイル）を設定済み
  - スニペット保存を `cloneVm` より前（`Uploading` ステータス）に移動済み

### ✅ ステップ 6: DB INSERT 同期化・責務分離（差異 3）

対象ファイル:
- `app/Services/VmService.php` — `createVmMeta()` 同期メソッド追加、`provisionVm(VmMetaData, array)` としてジョブ向け処理を再実装済み
- `app/Jobs/ProvisionVmJob.php` — 引数を `int $vmMetaId` + `array $templateParams` に変更済み

### ✅ ステップ 7: リダイレクト先修正・ジョブ引数整合（差異 1・2）

対象ファイル:
- `app/Http/Controllers/Vm/StoreController.php` — `vms.show` へのリダイレクト、`createVmMeta()` 呼び出しに変更済み

### ✅ ステップ 8: VM 詳細画面のネットワーク情報表示（差異 12）

対象ファイル:
- `resources/views/vms/show.blade.php` — VNet / 内部 IP / ゲートウェイ / 共有 IP を表示済み
- ポーリング判定に `'uploading'` ステータスを追加済み

---

## テスト結果

| テストファイル | 対象差異 | 結果 |
|--------------|---------|------|
| `tests/Unit/Phase4/CloudInitBuilderTest.php` | 4・9 | ✅ 10件パス |
| `tests/Unit/Phase5/DbaasCloudInitTemplatesTest.php` | 4・9 | ✅ 3件パス（runcmd 対応含む） |
| `tests/Feature/Phase4/VmManagementTest.php` | 1・3・6 | ✅ パス |
| `tests/Feature/Phase9/VmProvisioningSnippetUploadTest.php` | 4・5・7 | ✅ パス |
| `tests/Feature/Phase11/VmProvisioningAndDestroyFlowTest.php` | 2・3・6・8・10・11・12 | ✅ パス |
| `tests/Unit/Phase11/ProvisionJobsTest.php` | 2・3 | ✅ 4件パス |
| `tests/Unit/Phase11/ProvisioningQueueConfigTest.php` | 2 | ✅ パス |

> **備考:** `tests/Unit/Phase6/ContainerServiceTest` / `tests/Feature/Phase6/ContainerManagementTest` の 3 件失敗は
> 今回の変更と無関係な既存の問題（Nomad API フェイク未設定、`env_vars` 型不一致）であることを `git stash` で確認済み。

```bash
php artisan test --compact --filter="VmManagement|VmProvisioning|CloudInitBuilder|DbaasCloudInit|ProvisionJobs|ProvisioningQueue"
# → 30件パス、警告3件（deprecation）、失敗0件
```
