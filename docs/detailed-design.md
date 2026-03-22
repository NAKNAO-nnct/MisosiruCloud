# 詳細設計書

## 1. システム全体アーキテクチャ

### 1.1 コンポーネント構成

```
┌─────────────────────────────────────────────────────────────────┐
│               mgmt-docker VM (Docker Compose)                    │
│                                                                  │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                 管理パネル (Laravel)                      │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────┐    │    │
│  │  │ テナント  │ │ VM管理   │ │ DBaaS    │ │ CaaS   │    │    │
│  │  │ 管理     │ │          │ │ 管理     │ │ 管理   │    │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └───┬────┘    │    │
│  │       │             │             │             │        │    │
│  │  ┌────┴─────────────┴─────────────┴─────────────┴────┐  │    │
│  │  │              Lib/Proxmox (自作ライブラリ)          │  │    │
│  │  │              Lib/Nomad (自作ライブラリ)            │  │    │
│  │  │              Lib/S3Proxy (認証情報管理)            │  │    │
│  │  └───────────────────┬───────────────────────────────┘  │    │
│  └──────────────────────┼──────────────────────────────────┘    │
│                          │                                       │
│  ┌──────────┐  ┌────────┼───┐  ┌──────────┐  ┌──────────┐     │
│  │ mgmt-db  │  │ s3-proxy   │  │   dns    │  │ registry │     │
│  │ (MySQL)  │  │   (Go)     │  │(CoreDNS) │  │ (Harbor) │     │
│  └──────────┘  └────────────┘  └──────────┘  └──────────┘     │
└──────────────────┼──────────────────────────────────────────────┘
                   │
         ┌─────────┼─────────────────┐
         │         │                 │
         ▼         ▼                 ▼
  [Proxmox API]  [Snippet API]    [Nomad API]
  (各ノード)     (各ノード/Docker)  (Nomad Server)
```

### 1.2 技術スタック

| レイヤー | 技術 | バージョン | 備考 |
|---------|------|----------|------|
| フロントエンド | Blade + TailwindCSS | TailwindCSS v4 | JS/TSフレームワーク不使用 |
| バックエンド | Laravel | 12.x | PHP 8.4+ |
| 管理DB | MySQL | 8.x | 最小限のメタデータのみ |
| スニペットAPI | Python + FastAPI | Python 3.12+ | 各Proxmoxノードに Docker コンテナで配置 |
| S3 プロキシ | Go (カスタム) | Go 1.22+ | S3互換、独自認証情報発行 |
| 内部 DNS | CoreDNS | 最新 | キャッシュ/フォワーダ |
| コンテナ基盤 | Nomad + Consul | Nomad 1.9+ | マルチテナント |
| レジストリ | Harbor | 2.x | 全テナント共有 |
| オブジェクトストレージ | 外部 S3 (AWS S3 / Wasabi) | - | S3プロキシ経由でアクセス |
| 監視 | OTel Collector + Grafana Cloud | - | メトリクス/ログ/トレース |
| コンテナ管理 | Docker Compose | v2 | インフラサービスのオーケストレーション (compose.yaml / compose.prod.yaml) |

---

## 2. 管理パネル (Laravel) 設計

### 2.1 ディレクトリ構成

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   ├── ShowLoginForm.php
│   │   │   ├── Login.php
│   │   │   ├── Logout.php
│   │   │   └── TwoFactor/          # 2FA (任意)
│   │   │       ├── Show.php
│   │   │       ├── Verify.php
│   │   │       ├── Setup.php
│   │   │       ├── Confirm.php
│   │   │       └── Disable.php
│   │   ├── Dashboard/
│   │   │   └── Index.php
│   │   ├── Tenant/
│   │   │   ├── Index.php
│   │   │   ├── Create.php
│   │   │   ├── Store.php
│   │   │   ├── Show.php
│   │   │   ├── Edit.php
│   │   │   ├── Update.php
│   │   │   └── Destroy.php
│   │   ├── Vm/
│   │   │   ├── Index.php
│   │   │   ├── Create.php
│   │   │   ├── Store.php
│   │   │   ├── Show.php
│   │   │   ├── Start.php
│   │   │   ├── Stop.php
│   │   │   ├── Reboot.php
│   │   │   ├── ForceStop.php
│   │   │   ├── Destroy.php
│   │   │   ├── Console.php
│   │   │   ├── Snapshot.php
│   │   │   └── Resize.php
│   │   ├── Dbaas/
│   │   │   ├── Index.php
│   │   │   ├── Create.php
│   │   │   ├── Store.php
│   │   │   ├── Show.php
│   │   │   ├── Start.php
│   │   │   ├── Stop.php
│   │   │   ├── Destroy.php
│   │   │   ├── Backup.php
│   │   │   ├── Backups.php
│   │   │   ├── Restore.php
│   │   │   ├── Upgrade.php
│   │   │   └── Credentials.php
│   │   ├── Container/
│   │   │   ├── Index.php
│   │   │   ├── Create.php
│   │   │   ├── Store.php
│   │   │   ├── Show.php
│   │   │   ├── Restart.php
│   │   │   ├── Scale.php
│   │   │   ├── Destroy.php
│   │   │   └── Logs.php
│   │   ├── Network/
│   │   │   ├── Index.php
│   │   │   ├── Create.php
│   │   │   ├── Store.php
│   │   │   ├── Show.php
│   │   │   └── Destroy.php
│   │   ├── Monitoring/
│   │   │   ├── Index.php
│   │   │   └── GrafanaUrl.php
│   │   ├── S3Credential/
│   │   │   ├── Index.php
│   │   │   ├── Store.php
│   │   │   ├── Show.php
│   │   │   ├── Destroy.php
│   │   │   └── Rotate.php
│   │   ├── Api/
│   │   │   ├── VmStatus.php
│   │   │   ├── NodeStatus.php
│   │   │   ├── DbaasStatus.php
│   │   │   └── ContainerStatus.php
│   │   └── Admin/
│   │       ├── Node/
│   │       │   ├── Index.php
│   │       │   ├── Store.php
│   │       │   └── Update.php
│   │       ├── User/
│   │       │   ├── Index.php
│   │       │   ├── Store.php
│   │       │   └── Update.php
│   │       ├── Dns/                 # DNS プロバイダ API 経由
│   │       │   ├── Index.php
│   │       │   ├── Store.php
│   │       │   ├── Update.php
│   │       │   └── Destroy.php
│   │       └── Vps/
│   │           ├── Index.php
│   │           ├── Store.php
│   │           ├── Show.php
│   │           ├── Update.php
│   │           ├── Destroy.php
│   │           └── Sync.php
│   ├── Middleware/
│   │   ├── EnsureTenantAccess.php
│   │   └── EnsureAdminAccess.php
│   └── Requests/
│       ├── Vm/
│       │   ├── CreateVmRequest.php
│       │   └── UpdateVmRequest.php
│       ├── Dbaas/
│       │   ├── CreateDatabaseRequest.php
│       │   └── UpdateDatabaseRequest.php
│       └── Container/
│           └── DeployContainerRequest.php
├── Models/
│   ├── User.php
│   ├── Tenant.php
│   ├── VmMeta.php
│   ├── DatabaseInstance.php
│   ├── ContainerJob.php
│   ├── BackupSchedule.php
│   ├── S3Credential.php
│   ├── VpsGateway.php
├── Services/
│   ├── VmService.php
│   ├── DbaasService.php
│   ├── ContainerService.php
│   ├── BackupService.php
│   ├── S3CredentialService.php
│   ├── MonitoringService.php
│   ├── VpsGatewayService.php
│   └── CloudInit/
│       ├── CloudInitBuilder.php
│       └── Templates/
│           ├── BaseTemplate.php
│           ├── MysqlTemplate.php
│           ├── PostgresTemplate.php
│           └── RedisTemplate.php
├── Lib/
│   ├── Proxmox/
│   │   ├── Client.php          # HTTP クライアント基盤
│   │   ├── ProxmoxApi.php      # 統合エントリポイント
│   │   ├── Resources/
│   │   │   ├── Node.php        # ノード操作
│   │   │   ├── Vm.php          # VM (QEMU) 操作
│   │   │   ├── Storage.php     # ストレージ操作
│   │   │   ├── Network.php     # ネットワーク/SDN操作
│   │   │   └── Cluster.php     # クラスタ操作
│   │   ├── DataObjects/
│   │   │   ├── VmConfig.php
│   │   │   ├── VmStatus.php
│   │   │   ├── NodeStatus.php
│   │   │   └── StorageInfo.php
│   │   └── Exceptions/
│   │       ├── ProxmoxApiException.php
│   │       └── ProxmoxAuthException.php
│   ├── Nomad/
│   │   ├── Client.php
│   │   ├── NomadApi.php
│   │   ├── Resources/
│   │   │   ├── Job.php
│   │   │   ├── Allocation.php
│   │   │   ├── Node.php
│   │   │   └── Namespace.php
│   │   └── DataObjects/
│   │       ├── JobSpec.php
│   │       └── AllocationStatus.php
│   ├── Snippet/
│   │   └── SnippetClient.php   # スニペットAPI クライアント
│   └── S3Proxy/
│       └── CredentialManager.php  # S3認証情報の発行・管理
└── Enums/
    ├── VmStatus.php
    ├── DatabaseType.php        # mysql, postgres, redis
    └── TenantStatus.php

resources/
├── views/
│   ├── layouts/
│   │   └── app.blade.php
│   ├── dashboard/
│   │   └── index.blade.php
│   ├── tenants/
│   │   ├── index.blade.php
│   │   ├── show.blade.php
│   │   └── create.blade.php
│   ├── vms/
│   │   ├── index.blade.php
│   │   ├── show.blade.php
│   │   ├── create.blade.php
│   │   └── console.blade.php
│   ├── dbaas/
│   │   ├── index.blade.php
│   │   ├── show.blade.php
│   │   └── create.blade.php
│   ├── containers/
│   │   ├── index.blade.php
│   │   ├── show.blade.php
│   │   └── deploy.blade.php
│   ├── monitoring/
│   │   └── index.blade.php     # Grafana埋め込み
│   ├── admin/
│   │   └── vps/
│   │       ├── index.blade.php
│   │       └── show.blade.php
│   └── components/
│       ├── vm-status-badge.blade.php
│       ├── resource-meter.blade.php
│       └── action-button.blade.php
```

### 2.2 Lib/Proxmox 設計

Proxmox VE の REST API を直接呼び出す自作ライブラリ。

**Client.php - HTTP基盤:**

```php
namespace App\Lib\Proxmox;

class Client
{
    private string $baseUrl;
    private string $tokenId;
    private string $tokenSecret;

    public function __construct(string $host, string $tokenId, string $tokenSecret)
    {
        $this->baseUrl = "https://{$host}:8006/api2/json";
        $this->tokenId = $tokenId;
        $this->tokenSecret = $tokenSecret;
    }

    public function get(string $path, array $params = []): array { /* ... */ }
    public function post(string $path, array $data = []): array { /* ... */ }
    public function put(string $path, array $data = []): array { /* ... */ }
    public function delete(string $path): array { /* ... */ }
}
```

**主要メソッド一覧:**

| リソース | メソッド | Proxmox API エンドポイント |
|---------|--------|--------------------------|
| Cluster | getClusterStatus() | GET /cluster/status |
| Cluster | getResources() | GET /cluster/resources |
| Node | listNodes() | GET /nodes |
| Node | getNodeStatus(node) | GET /nodes/{node}/status |
| Storage | listStorage(node) | GET /nodes/{node}/storage |
| Storage | listStorageContent(node, storage) | GET /nodes/{node}/storage/{storage}/content |
| Network | listNetworks(node) | GET /nodes/{node}/network |
| Vm | listVms(node) | GET /nodes/{node}/qemu |
| Vm | getVmConfig(node, vmid) | GET /nodes/{node}/qemu/{vmid}/config |
| Vm | updateVmConfig(node, vmid, params) | PUT /nodes/{node}/qemu/{vmid}/config |
| Vm | createVm(node, params) | POST /nodes/{node}/qemu |
| Vm | deleteVm(node, vmid) | DELETE /nodes/{node}/qemu/{vmid} |
| Vm | getVmStatus(node, vmid) | GET /nodes/{node}/qemu/{vmid}/status/current |
| Vm | startVm(node, vmid) | POST /nodes/{node}/qemu/{vmid}/status/start |
| Vm | stopVm(node, vmid) | POST /nodes/{node}/qemu/{vmid}/status/stop |
| Vm | rebootVm(node, vmid) | POST /nodes/{node}/qemu/{vmid}/status/reboot |
| Vm | cloneVm(node, vmid, params) | POST /nodes/{node}/qemu/{vmid}/clone |
| Vm | resizeVm(node, vmid, disk, size) | PUT /nodes/{node}/qemu/{vmid}/resize |
| Vm | listSnapshots(node, vmid) | GET /nodes/{node}/qemu/{vmid}/snapshot |
| Vm | createSnapshot(node, vmid, name) | POST /nodes/{node}/qemu/{vmid}/snapshot |
| Vm | getVncProxy(node, vmid) | POST /nodes/{node}/qemu/{vmid}/vncproxy |
| Vm | regenerateCloudinit(node, vmid) | PUT /nodes/{node}/qemu/{vmid}/cloudinit |
| Task | getTaskStatus(node, upid) | GET /nodes/{node}/tasks/{upid}/status |
| Task | waitForTask(node, upid, timeout) | ※ getTaskStatus をポーリングして完了を待つヘルパー |
| Sdn | listVnets() | GET /cluster/sdn/vnets |
| Sdn | createVnet(params) | POST /cluster/sdn/vnets |
| Sdn | deleteVnet(vnet) | DELETE /cluster/sdn/vnets/{vnet} |
| Sdn | createSubnet(vnet, params) | POST /cluster/sdn/vnets/{vnet}/subnets |
| Sdn | listZones() | GET /cluster/sdn/zones |
| Sdn | applySdn() | PUT /cluster/sdn |

### 2.3 画面一覧

| 画面 | URL | 機能 |
|------|-----|------|
| ログイン | /login | メール + パスワード (+ 2FA 任意) |
| ダッシュボード | / | ノード状態概要、テナント数、VM数 |
| テナント一覧 | /tenants | テナント検索・一覧 |
| テナント詳細 | /tenants/{id} | テナント情報・所有リソース一覧 |
| テナント作成 | /tenants/create | テナント作成フォーム |
| VM一覧 | /vms | 全VM一覧 (Proxmoxから取得) |
| VM詳細 | /vms/{vmid} | VM設定・状態・操作 |
| VM作成 | /vms/create | Cloud-init VMデプロイフォーム |
| VMコンソール | /vms/{vmid}/console | noVNC埋め込みコンソール |
| DBaaS一覧 | /dbaas | データベースインスタンス一覧 |
| DBaaS詳細 | /dbaas/{id} | DB接続情報・バックアップ・操作 |
| DBaaS作成 | /dbaas/create | DBインスタンス作成フォーム |
| コンテナ一覧 | /containers | Nomadジョブ一覧 |
| コンテナ詳細 | /containers/{id} | ジョブ詳細・アロケーション |
| コンテナデプロイ | /containers/deploy | コンテナデプロイフォーム |
| 監視 | /monitoring | Grafanaダッシュボード埋め込み |
| ネットワーク | /networks | テナントネットワーク管理 |
| VPSゲートウェイ一覧 | /admin/vps | VPSゲートウェイ管理 (admin) |
| VPSゲートウェイ詳細 | /admin/vps/{id} | VPS接続状態・設定詳細 |

### 2.4 主要処理フロー図

#### 2.4.1 テナント作成フロー

```mermaid
sequenceDiagram
    actor User as 管理者
    participant UI as テナント作成画面
    participant Store as Tenant\Store
    participant DB as 管理DB (MySQL)
    participant PVE as Proxmox SDN API
    participant S3Svc as S3CredentialService
    participant Nomad as Nomad API

    User->>UI: テナント情報入力<br/>(名前, slug)
    UI->>Store: POST /tenants

    Note over Store: バリデーション

    Store->>DB: BEGIN TRANSACTION

    rect rgb(230, 245, 255)
        Note over Store,DB: 1. テナントDB登録
        Store->>DB: INSERT tenants<br/>(uuid, name, slug, status='active')
        DB-->>Store: tenant_id 返却
        Store->>Store: VNI 算出<br/>VNI = 10000 + tenant_id<br/>vnet_name = tenant-{id}<br/>network_cidr = 10.{id}.0.0/24
        Store->>DB: UPDATE tenants<br/>SET vnet_name, vni, network_cidr
    end

    rect rgb(255, 245, 230)
        Note over Store,PVE: 2. Proxmox SDN VNet 作成
        Store->>PVE: POST /cluster/sdn/vnets<br/>{vnet: "tenant-{id}",<br/> zone: "evpn-zone",<br/> tag: VNI}
        PVE-->>Store: 200 OK
        Store->>PVE: POST /cluster/sdn/vnets/{vnet}/subnets<br/>{subnet: "10.{id}.0.0/24",<br/> gateway: "10.{id}.0.1"}
        PVE-->>Store: 200 OK
        Store->>PVE: PUT /cluster/sdn<br/>(設定適用)
        PVE-->>Store: 200 OK
    end

    Note over PVE: Proxmox が自動実行:<br/>- 各ノードに VNet ブリッジ作成<br/>- FRR (EVPN) にサブネット注入<br/>- VXLAN トンネル確立

    rect rgb(230, 255, 230)
        Note over Store,S3Svc: 3. S3 認証情報 自動発行
        Store->>S3Svc: createDefaultCredential(tenant)
        S3Svc->>S3Svc: Access Key 生成 (20文字)<br/>Secret Key 生成 (40文字)
        S3Svc->>DB: INSERT s3_credentials<br/>(tenant_id, access_key,<br/> secret_key_encrypted,<br/> allowed_bucket='dbaas-backups',<br/> allowed_prefix='tenant-{id}/')
        DB-->>S3Svc: OK
    end

    rect rgb(245, 230, 255)
        Note over Store,Nomad: 4. Nomad Namespace 作成
        Store->>Nomad: PUT /v1/namespace/tenant-{id}
        Nomad-->>Store: 200 OK
        Store->>DB: UPDATE tenants<br/>SET nomad_namespace='tenant-{id}'
    end

    Store->>DB: COMMIT

    Store-->>UI: リダイレクト → テナント詳細

    UI-->>User: 作成完了表示<br/>(VNet, S3認証情報, Namespace)
```

#### 2.4.2 VM 作成フロー

> **非同期ジョブ方式:** POST リクエストではバリデーションと DB 登録のみ行い、
> Proxmox 操作は `ProvisionVmJob` で非同期実行する。
> クローン (clone) は UPID を返す非同期タスクであり、完了まで数十秒〜数分かかるため。

```mermaid
sequenceDiagram
    actor User as 管理者/テナント管理者
    participant UI as VM作成画面
    participant Store as Vm\Store
    participant DB as 管理DB (MySQL)
    participant Queue as Laravel Queue
    participant Job as ProvisionVmJob
    participant CIB as CloudInitBuilder
    participant Snippet as SnippetClient
    participant SnipAPI as スニペットAPI<br/>(FastAPI)
    participant PVE as Proxmox API

    User->>UI: VM情報入力<br/>(テナント, ノード, スペック,<br/> IP, shared_ip_address等)
    UI->>Store: POST /vms

    Note over Store: バリデーション<br/>(CreateVmRequest)

    rect rgb(245, 230, 255)
        Note over Store,DB: 1. メタデータ保存 (同期)
        Store->>DB: nextVmId() で VMID 採番
        Store->>DB: INSERT vm_metas<br/>(tenant_id, proxmox_vmid,<br/> proxmox_node, purpose='general',<br/> label, shared_ip_address,<br/> provisioning_status='pending')
        DB-->>Store: vm_meta_id
    end

    rect rgb(230, 245, 255)
        Note over Store,Queue: 2. ジョブディスパッチ (同期)
        Store->>Queue: ProvisionVmJob::dispatch(vm_meta_id, params)
    end

    Store-->>UI: リダイレクト → VM詳細<br/>(ステータス: プロビジョニング中)
    UI-->>User: 「プロビジョニング中」表示

    Note over Queue,Job: === 以降は非同期 (Queue Worker) ===

    Queue->>Job: handle()

    rect rgb(230, 245, 255)
        Note over Job,CIB: 3. Cloud-init YAML 生成
        Job->>DB: UPDATE provisioning_status='cloning'
        Job->>CIB: build(params)
        CIB->>CIB: user-data.yaml 生成<br/>(パッケージ, ユーザ, SSH鍵,<br/> DNS設定, S3プロキシ設定)
        CIB->>CIB: network-config.yaml 生成<br/>eth0: 10.{tenant_id}.0.x/24<br/>GW: 10.{tenant_id}.0.1
        alt shared_ip_address 指定あり
            CIB->>CIB: eth1 追加: 172.26.27.x/24<br/>(GWなし, ルーティングのみ)
        end
        CIB->>CIB: meta-data.yaml 生成<br/>(hostname, instance-id)
        CIB-->>Job: YAML ファイル群
    end

    rect rgb(255, 245, 230)
        Note over Job,SnipAPI: 4. スニペットファイル保存
        Job->>Snippet: saveSnippets(node, vmid, yamls)
        Snippet->>SnipAPI: POST /snippets/{vm_id}<br/>{user_data, network_config,<br/> meta_data}
        SnipAPI->>SnipAPI: ファイル書き込み<br/>/var/lib/vz/snippets/<br/>{vm_id}-user.yaml<br/>{vm_id}-network.yaml
        SnipAPI-->>Snippet: 200 OK (ファイルパス)
    end

    rect rgb(230, 255, 230)
        Note over Job,PVE: 5. テンプレートからクローン (非同期タスク)
        Job->>PVE: POST /nodes/{node}/qemu/{template_vmid}/clone
        Note right of PVE: newid, name, target: node<br/>full: 1, storage: ceph-pool
        PVE-->>Job: UPID
        Job->>PVE: waitForTask(node, upid, timeout=300)
        Note right of PVE: ポーリング間隔 5秒<br/>完了まで待機
        PVE-->>Job: タスク完了
    end

    rect rgb(240, 255, 240)
        Note over Job,PVE: 6. VM 設定変更 + ディスクリサイズ
        Job->>DB: UPDATE provisioning_status='configuring'
        Job->>PVE: PUT /nodes/{node}/qemu/{vmid}/config
        Note right of PVE: cores, memory<br/>net0: virtio,bridge=vnet_{tenant}<br/>net1: virtio,bridge=vmbr1<br/>   (shared_ip指定時のみ)<br/>cicustom: local:snippets/...
        PVE-->>Job: 200 OK
        Job->>PVE: PUT /nodes/{node}/qemu/{vmid}/resize
        Note right of PVE: disk: scsi0, size: {disk_size}
        PVE-->>Job: 200 OK
    end

    rect rgb(245, 245, 230)
        Note over Job,PVE: 7. VM 起動
        Job->>DB: UPDATE provisioning_status='starting'
        Job->>PVE: POST /nodes/{node}/qemu/{vmid}/status/start
        PVE-->>Job: 200 OK
    end

    Job->>DB: UPDATE provisioning_status='ready'
    Note over Job: ジョブ完了

    Note over User,UI: ユーザは VM詳細画面で<br/>ポーリング or リロードで<br/>ステータス確認
```

#### 2.4.3 DBaaS 作成フロー

> **非同期ジョブ方式:** VM 作成と同様、POST では DB 登録のみ行い、
> Proxmox 操作は `ProvisionDbaasJob` で非同期実行する。

```mermaid
sequenceDiagram
    actor User as 管理者/テナント管理者
    participant UI as DBaaS作成画面
    participant Store as Dbaas\Store
    participant DSvc as DbaasService
    participant DB as 管理DB (MySQL)
    participant Queue as Laravel Queue
    participant Job as ProvisionDbaasJob
    participant CIB as CloudInitBuilder
    participant Snippet as SnippetClient
    participant SnipAPI as スニペットAPI<br/>(FastAPI)
    participant PVE as Proxmox API
    participant BSvc as BackupService

    User->>UI: DB情報入力<br/>(テナント, DB種別, バージョン,<br/> スペック, ノード)
    UI->>Store: POST /dbaas

    Note over Store: バリデーション<br/>(CreateDatabaseRequest)

    rect rgb(230, 245, 255)
        Note over Store,DSvc: 1. DB固有設定生成 (同期)
        Store->>DSvc: buildDbConfig(params)
        DSvc->>DSvc: DB種別テンプレート選択<br/>(MysqlTemplate / PostgresTemplate<br/> / RedisTemplate)
        DSvc->>DSvc: ランダムパスワード生成<br/>・管理ユーザ用<br/>・テナントユーザ用
        DSvc->>DSvc: DB設定ファイル生成<br/>(my.cnf / postgresql.conf<br/> / redis.conf)
        DSvc->>DSvc: バックアップ cron 設定生成<br/>(S3プロキシ宛アップロード)
        DSvc-->>Store: DB設定一式
    end

    rect rgb(245, 230, 255)
        Note over Store,DB: 2. メタデータ保存 (同期)
        Store->>DB: nextVmId() で VMID 採番
        Store->>DB: INSERT vm_metas<br/>(tenant_id, vmid, node,<br/> purpose='dbaas',<br/> provisioning_status='pending')
        DB-->>Store: vm_meta_id

        Store->>DB: INSERT database_instances<br/>(tenant_id, vm_meta_id,<br/> db_type, db_version, port,<br/> admin_user,<br/> admin_password_encrypted,<br/> tenant_user,<br/> tenant_password_encrypted,<br/> status='provisioning')
        DB-->>Store: database_instance_id
    end

    rect rgb(255, 230, 240)
        Note over Store,BSvc: 3. バックアップスケジュール登録 (同期)
        Store->>BSvc: createSchedule(dbInstance)
        BSvc->>DB: INSERT backup_schedules<br/>(database_instance_id,<br/> cron='0 3 * * *',<br/> retention_daily=7,<br/> retention_weekly=4,<br/> retention_monthly=3)
        DB-->>BSvc: OK
    end

    rect rgb(230, 255, 230)
        Note over Store,Queue: 4. ジョブディスパッチ (同期)
        Store->>Queue: ProvisionDbaasJob::dispatch(<br/>  vm_meta_id, database_instance_id,<br/>  params, dbConfig)
    end

    Store-->>UI: リダイレクト → DBaaS詳細<br/>(ステータス: プロビジョニング中)
    UI-->>User: 「プロビジョニング中」表示

    Note over Queue,Job: === 以降は非同期 (Queue Worker) ===

    Queue->>Job: handle()

    rect rgb(255, 245, 230)
        Note over Job,CIB: 5. Cloud-init YAML 生成
        Job->>DB: UPDATE vm_metas SET provisioning_status='cloning'
        Job->>CIB: build(params + dbConfig)
        CIB->>CIB: user-data.yaml 生成<br/>・DB パッケージインストール<br/>・DB 設定ファイル配置<br/>・初期DB・ユーザ作成<br/>・バックアップ cron 設定<br/>・S3プロキシ認証設定
        CIB->>CIB: network-config.yaml 生成<br/>eth0: 10.{tenant_id}.0.x/24
        CIB-->>Job: YAML ファイル群
    end

    rect rgb(230, 255, 230)
        Note over Job,SnipAPI: 6. スニペットファイル保存
        Job->>Snippet: saveSnippets(node, vmid, yamls)
        Snippet->>SnipAPI: POST /snippets/{vm_id}
        SnipAPI-->>Snippet: 200 OK
    end

    rect rgb(245, 245, 230)
        Note over Job,PVE: 7. テンプレートからクローン + ディスクリサイズ
        Job->>PVE: POST /nodes/{node}/qemu/{template_vmid}/clone
        Note right of PVE: newid, name, full: 1,<br/>storage: ceph-pool
        PVE-->>Job: UPID
        Job->>PVE: waitForTask(node, upid, timeout=300)
        PVE-->>Job: タスク完了

        Job->>DB: UPDATE vm_metas SET provisioning_status='configuring'
        Job->>PVE: PUT /nodes/{node}/qemu/{vmid}/config
        Note right of PVE: cores, memory<br/>net0: vnet_{tenant}<br/>cicustom: snippets
        PVE-->>Job: 200 OK
        Job->>PVE: PUT /nodes/{node}/qemu/{vmid}/resize
        Note right of PVE: disk: scsi0, size: {disk_size}
        PVE-->>Job: 200 OK

        Job->>DB: UPDATE vm_metas SET provisioning_status='starting'
        Job->>PVE: POST /nodes/{node}/qemu/{vmid}/status/start
        PVE-->>Job: 200 OK
    end

    Job->>DB: UPDATE vm_metas SET provisioning_status='ready'
    Job->>DB: UPDATE database_instances SET status='running'
    Note over Job: ジョブ完了

    Note over User,UI: ユーザは DBaaS詳細画面で<br/>ポーリング or リロードで<br/>ステータス確認

    Note over PVE: VM 起動後 Cloud-init が実行:<br/>1. DB パッケージインストール<br/>2. 設定ファイル配置<br/>3. DB 初期化<br/>4. ユーザ・権限設定<br/>5. バックアップ cron 有効化
```

---

## 3. 非同期ジョブ設計 (Laravel Queue)

### 3.1 概要

Proxmox API のクローン操作は非同期タスク (UPID) を返し、完了まで数十秒〜数分かかる。
HTTP リクエスト内で同期的に全ステップ (clone → wait → config → resize → start) を実行すると:

- **タイムアウト:** PHP/nginx のタイムアウト (通常 30-60 秒) を超える可能性
- **進捗不明:** ユーザにプロビジョニング進捗を伝えられない
- **エラーリカバリ困難:** 途中失敗時に中間状態が残る

これらを解決するため、**Laravel Queue + Jobs** で非同期処理する。

### 3.2 アーキテクチャ

```
[HTTP リクエスト (POST /vms)]
    │
    ├── バリデーション (同期)
    ├── DB レコード作成 (同期: vm_metas, provisioning_status='pending')
    ├── Job ディスパッチ (同期: ProvisionVmJob::dispatch())
    └── リダイレクト返却 (即座)
    
[Queue Worker (php artisan queue:work)]
    │
    └── ProvisionVmJob::handle()
        ├── Cloud-init YAML 生成
        ├── スニペットAPI にファイル保存
        ├── Proxmox clone → waitForTask(UPID)
        ├── VM 設定変更 + ディスクリサイズ
        ├── VM 起動
        └── provisioning_status='ready' に更新

[フロントエンド]
    └── VM詳細画面で provisioning_status をポーリング (リロード or Alpine.js)
```

### 3.3 Job 一覧

| Job クラス | トリガー | 処理内容 |
|-----------|---------|---------|
| ProvisionVmJob | POST /vms | clone → config → resize → start |
| ProvisionDbaasJob | POST /dbaas | Cloud-init(DB) → clone → config → resize → start |
| DestroyVmJob | DELETE /vms/{vmid} | stop → スニペット削除 → destroy → DB レコード削除 |

### 3.4 Queue 設定

| 項目 | 値 | 理由 |
|------|-----|------|
| ドライバ | `database` | Redis コンテナを増やさずに済む。管理 DB (MySQL) の `jobs` テーブルを使用 |
| キュー名 | `provisioning` | デフォルト `default` と分離して優先度制御可能に |
| タイムアウト | `600` (秒) | Ceph full clone は大きなディスクで数分かかりうる |
| リトライ回数 | `0` | VM 作成は冪等でないため自動リトライしない。手動リトライ (管理画面) を提供 |
| ワーカー数 | `1` (Phase 1) | VMID 採番の競合を避ける。将来的に並列化可能 |

### 3.5 provisioning_status 遷移

```
pending → cloning → configuring → starting → ready
    │         │           │            │
    └─────────┴───────────┴────────────┘
                      ↓
                    error
```

| ステータス | 意味 | 設定タイミング |
|-----------|------|-------------|
| pending | ジョブがキューに投入済み | POST リクエスト内 (同期) |
| cloning | Proxmox clone タスク実行中 | Job: clone 前 |
| configuring | VM 設定変更 + ディスクリサイズ中 | Job: clone 完了後 |
| starting | VM 起動中 | Job: config/resize 完了後 |
| ready | プロビジョニング完了 | Job: start 完了後 |
| error | エラー発生 | Job: 例外キャッチ時 |

### 3.6 エラーハンドリング

```php
// ProvisionVmJob::handle() の概要
public function handle(): void
{
    try {
        $this->updateStatus('cloning');
        // ... clone + waitForTask ...
        $this->updateStatus('configuring');
        // ... config + resize ...
        $this->updateStatus('starting');
        // ... start ...
        $this->updateStatus('ready');
    } catch (\Throwable $e) {
        $this->vmMeta->update([
            'provisioning_status' => 'error',
            'provisioning_error' => Str::limit($e->getMessage(), 500),
        ]);
        // ※ 自動リトライはしない (tries=1)
        // ※ 管理画面から手動リトライ可能
    }
}
```

**エラー時のリカバリ:**
- 管理画面に「リトライ」ボタンを表示 (provisioning_status='error' の場合)
- リトライ時: 既存 VM があれば削除してから再実行
- 中間リソースのクリーンアップ: スニペットファイル、半作成 VM

---

## 4. スニペット保存API (Python/FastAPI) 設計

### 4.1 概要

Proxmox API では Cloud-init 用のスニペットファイルを直接保存できないため、各 Proxmox ノードでスニペットファイルの読み書きを行う軽量 API サーバ。

### 4.2 ディレクトリ構成

```
snippet-api/
├── main.py
├── config.py
├── requirements.txt
├── models/
│   ├── __init__.py
│   └── snippet.py
├── routes/
│   ├── __init__.py
│   └── snippets.py
└── services/
    ├── __init__.py
    └── file_service.py
```

### 4.3 API エンドポイント

| メソッド | パス | 説明 | 認証 |
|---------|------|------|------|
| GET | /health | ヘルスチェック | なし |
| POST | /snippets/{vm_id} | スニペットファイル作成・更新 | Bearer Token |
| GET | /snippets/{vm_id} | スニペットファイル取得 | Bearer Token |
| DELETE | /snippets/{vm_id} | スニペットファイル削除 | Bearer Token |
| GET | /snippets | スニペット一覧取得 | Bearer Token |

### 4.4 リクエスト/レスポンス例

**POST /snippets/{vm_id}:**

```json
// Request
{
  "user_data": "#cloud-config\npackage_update: true\npackages:\n  - mysql-server\n...",
  "network_config": "version: 2\nethernets:\n  eth0:\n    dhcp4: false\n    addresses: [10.1.0.100/24]\n    gateway4: 10.1.0.1\n    nameservers:\n      addresses: [172.26.26.10]\n      search: [internal]\n  eth1:\n    dhcp4: false\n    addresses: [172.26.27.100/24]\n..."
}

// Response
{
  "status": "ok",
  "vm_id": "100",
  "files": {
    "user_data": "/var/lib/vz/snippets/100-user.yaml",
    "network_config": "/var/lib/vz/snippets/100-network.yaml"
  }
}
```

### 4.5 セキュリティ

- Bearer Token による認証（管理パネルとの共有シークレット）
- ファイルパスのバリデーション（パストラバーサル防止）
  - vm_id は数値のみ許可
  - 書き込み先は `/var/lib/vz/snippets/` 配下に限定
- リクエストサイズ上限: 1MB
- バインドアドレス: 管理ネットワークのみ

---

## 5. VM管理機能 設計

### 5.1 VM作成フロー

```
[ユーザ: VM作成フォーム入力]
    │
    ▼
[Laravel: バリデーション (CreateVmRequest)]
    │
    ▼
[管理DB: VMID 採番 + vm_metas INSERT (provisioning_status='pending')]
    │
    ▼
[Laravel Queue: ProvisionVmJob::dispatch(vm_meta_id, params)]
    │
    ▼
[リダイレクト → VM詳細 (ステータス: プロビジョニング中)]

=== 以降は Queue Worker で非同期実行 ===

[ProvisionVmJob: handle()]
    │
    ▼
[CloudInitBuilder: cloud-init yaml生成]
    │
    ├── user-data.yaml (パッケージ、ユーザ設定)
    ├── network-config.yaml (IP設定)
    └── meta-data.yaml (ホスト名など)
    │
    ▼
[SnippetClient: スニペットAPIにyaml送信]
    │  POST /snippets/{vm_id}
    ▼
[スニペットAPI: ファイル書き込み]
    │  /var/lib/vz/snippets/{vm_id}-user.yaml
    │  /var/lib/vz/snippets/{vm_id}-network.yaml
    ▼
[UPDATE provisioning_status='cloning']
    ▼
[Lib/Proxmox: テンプレートVMクローン]
    │  POST /nodes/{node}/qemu/{template_vmid}/clone → UPID
    │  waitForTask(node, upid, timeout=300)
    ▼
[UPDATE provisioning_status='configuring']
    ▼
[Lib/Proxmox: VM設定変更]
    │  PUT /nodes/{node}/qemu/{vmid}/config
    │  cores, memory
    │  net0: virtio,bridge=vnet_{tenant}
    │  net1: virtio,bridge=vmbr1 (shared_ip_address指定時のみ)
    │  cicustom: local:snippets/{vm_id}-user.yaml,...
    ▼
[Lib/Proxmox: ディスクリサイズ]
    │  PUT /nodes/{node}/qemu/{vmid}/resize
    │  disk: scsi0, size: {disk_size} (例: "20G")
    ▼
[UPDATE provisioning_status='starting']
    ▼
[Lib/Proxmox: VM起動]
    │  POST /nodes/{node}/qemu/{vmid}/status/start
    ▼
[UPDATE provisioning_status='ready']
    ▼
[ジョブ完了: VM一覧でステータス反映]

※ エラー時: provisioning_status='error', provisioning_error にメッセージ保存
```

### 5.2 VM作成パラメータ

| パラメータ | 型 | 必須 | 説明 |
|----------|------|------|------|
| tenant_id | int | ○ | テナントID |
| node | string | ○ | 配置先ノード (pve1/pve2/pve3) |
| name | string | ○ | VM名 |
| template | string | ○ | ベーステンプレート名 |
| cores | int | ○ | vCPU数 (1-16) |
| memory | int | ○ | メモリ MB (512-32768) |
| disk_size | string | ○ | ディスクサイズ (例: "20G") |
| ip_address | string | ○ | テナントVNet内の固定IP (10.{tenant_id}.0.x/24) |
| gateway | string | ○ | デフォルトゲートウェイ (10.{tenant_id}.0.1) |
| ssh_keys | text | △ | SSH公開鍵 (複数行可) |
| vnet_name | string | ○ | テナントVNet名 (SDN) |
| shared_ip_address | string | ― | VM Network (172.26.27.0/24) のIP (任意) |

**Dual NIC 構成 (任意):**

`shared_ip_address` を指定した場合、VM に 2つの NIC を割り当てる:

| NIC | Proxmox パラメータ | ブリッジ | 用途 |
|-----|---------|---------|------|
| net0 (eth0) | `virtio,bridge=vnet_{tenant}` | テナントVNet | テナント内通信 (10.{tenant_id}.0.x) |
| net1 (eth1) | `virtio,bridge=vmbr1` | VM Network | 共有ネットワーク接続 (172.26.27.x) |

`shared_ip_address` が未指定の場合、net0 のみ (テナントVNetのみ) で作成する。

**ユースケース例:**
- テナントVM から管理系共有サービス (DNS, レジストリ等) へ直接アクセス
- Transit VM 経由ではなく自宅ルータ経由でインターネットに出る必要がある VM
- 監視エージェントのメトリクス送信等

### 5.3 VM操作一覧

| 操作 | Proxmox API | 管理DB更新 |
|------|------------|-----------|
| 起動 | POST /status/start | - |
| 停止 | POST /status/stop | - |
| 再起動 | POST /status/reboot | - |
| 強制停止 | POST /status/stop?forceStop=1 | - |
| 削除 | DELETE /qemu/{vmid} | VmMeta削除、スニペット削除 |
| スナップショット | POST /snapshot | - |
| リサイズ | PUT /resize | - |
| コンソール | POST /vncproxy → noVNC | - |

---

## 6. DBaaS 設計

### 6.1 対応データベース

| DB種別 | 対応バージョン | デフォルトポート | 備考 |
|--------|-------------|---------------|------|
| MySQL | 8.0, 8.4, 9.x | 3306 | InnoDB, utf8mb4 |
| PostgreSQL | 15, 16, 17 | 5432 | UTF-8 |
| Redis | 7.x, 8.x | 6379 | 認証有効化 |

### 6.2 DBaaS 作成フロー

```
[ユーザ: DB種別・バージョン・リソース選択]
    │
    ▼
[Laravel: バリデーション (CreateDatabaseRequest)]
    │
    ▼
[DbaasService: DB固有設定生成]
    │  ├── DB種別に応じたテンプレート選択
    │  ├── ランダムパスワード生成 (管理用 + テナント用)
    │  ├── my.cnf / postgresql.conf / redis.conf 生成
    │  └── バックアップ cron 設定追加
    ▼
[管理DB: VMID 採番 + vm_metas INSERT (provisioning_status='pending')]
    │
    ▼
[管理DB: database_instances INSERT (status='provisioning')]
    │
    ▼
[BackupService: バックアップスケジュール登録]
    │
    ▼
[Laravel Queue: ProvisionDbaasJob::dispatch(vm_meta_id, db_instance_id, params, dbConfig)]
    │
    ▼
[リダイレクト → DBaaS詳細 (ステータス: プロビジョニング中)]

=== 以降は Queue Worker で非同期実行 ===

[ProvisionDbaasJob: handle()]
    │
    ▼
[VmService: VM作成 (上記 5.1 の非同期フローと同じ手順)]
    │  Cloud-init生成 → スニペット保存 → clone → waitForTask
    │  → config → resize → start
    ▼
[管理DB: vm_metas SET provisioning_status='ready']
    │
    ▼
[管理DB: database_instances SET status='running']
    │
    ▼
[ジョブ完了: DBaaS一覧に接続情報表示]

※ エラー時: provisioning_status='error', status='error'
```

### 6.3 バックアップ方式

| DB種別 | バックアップ方法 | スケジュール | 保持期間 |
|--------|---------------|------------|---------|
| MySQL | mysqldump --single-transaction | 毎日 03:00 | 7日間(日次) + 4週間(週次) + 3ヶ月(月次) |
| PostgreSQL | pg_dump -Fc | 毎日 03:00 | 同上 |
| Redis | BGSAVE → RDB コピー | 毎日 03:00 | 同上 |

**バックアップ処理フロー (S3 プロキシ経由):**

テナント VM は内部 S3 プロキシ (`s3.infra.example.com:9000`) 経由でバックアップをアップロードする。
外部 S3 の認証情報はテナント VM には配布しない。

```
1. [DBaaS VM 内 cron]
    │
    ▼
   [dump実行] → [gzip圧縮] → [gpg暗号化 (AES-256)]
    │
    ▼
   [aws cli / mc で S3 プロキシにアップロード]
    │  エンドポイント: http://s3.infra.example.com:9000
    │  認証: テナント別の内部 Access Key / Secret Key
    │  パス: s3://dbaas-backups/{date}.sql.gz.gpg
    ▼
   [S3 プロキシ]
    │  テナントプレフィックス付与 → 外部 S3 に転送
    │  s3://dbaas-backups/{tenant_id}/{db_id}/{date}.sql.gz.gpg
    ▼
   [管理パネル: backup_schedules テーブル更新]
    │  last_backup_at / last_backup_status を更新
    ▼
   [古いバックアップの削除 (保持ポリシーに従う)]
```

**メリット:**
- 外部 S3 認証情報は s3-proxy コンテナのみが保持
- テナント VM が侵害されても外部 S3 に直接アクセス不可 (内部認証情報は失効可能)
- バックアップの並列アップロードが可能 (mgmt-app の SSH ボトルネック解消)
- S3 プロキシがテナント別にバケット/プレフィックスを分離

### 6.4 バージョンアップデート

```
[管理パネル: アップデート開始ボタン]
    │
    ▼
[バックアップ実行 (即時)]
    │
    ▼
[VM内でDB停止]
    │
    ▼
[パッケージアップデート実行]
    │  apt update && apt upgrade mysql-server (or equivalent)
    ▼
[DB起動 + マイグレーション実行]
    │  mysql_upgrade etc.
    ▼
[ヘルスチェック]
    │
    ▼
[管理DB: バージョン情報更新]
```

---

## 7. CaaS (コンテナ基盤) 設計

### 7.1 Nomad クラスタ構成

```
┌──────────────────────────────────────────────┐
│  Nomad Server クラスタ (3台)                  │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐     │
│  │ Server 1 │ │ Server 2 │ │ Server 3 │     │
│  │ (pve1)   │ │ (pve2)   │ │ (pve3)   │     │
│  └──────────┘ └──────────┘ └──────────┘     │
└──────────────────┬───────────────────────────┘
                   │
     ┌─────────────┼─────────────┐
     ▼             ▼             ▼
┌─────────┐  ┌─────────┐  ┌─────────┐
│Worker VM│  │Worker VM│  │Worker VM│
│ Docker  │  │ Docker  │  │ Docker  │
│ Traefik │  │ Traefik │  │ Traefik │
│ cAdvisor│  │ cAdvisor│  │ cAdvisor│
│ OTel    │  │ OTel    │  │ OTel    │
└─────────┘  └─────────┘  └─────────┘
```

### 7.2 テナント隔離

| 隔離レベル | 方式 | 説明 |
|-----------|------|------|
| ネットワーク | Nomad CNI (bridge) + EVPN/VXLAN | テナントごとにVNetで分離 |
| ネームスペース | Nomad Namespace | テナントごとにNamespace作成 |
| リソース | Nomad Quota | テナントごとにCPU/メモリ上限設定 |
| レジストリ | Harbor プロジェクト | テナントごとのプロジェクト (push権限) + 共有プロジェクト (pull権限) |

### 7.3 コンテナデプロイフロー

```
[ユーザ: イメージ・ポート・環境変数入力]
    │
    ▼
[Laravel: バリデーション]
    │
    ▼
[ContainerService: Nomad Job Spec 生成]
    │  ├── namespace: tenant-{tenant_id}
    │  ├── image: registry.infra.example.com/{image}
    │  ├── resources: CPU/Memory 制限
    │  ├── network: bridge (テナントVNet)
    │  └── service: Consul サービス登録
    ▼
[Lib/Nomad: Job 登録]
    │  PUT /v1/jobs
    ▼
[管理DB: ContainerJob レコード作成]
    │
    ▼
[完了: コンテナ一覧に表示]
```

### 7.4 Nomad Job テンプレート例

```hcl
job "tenant-${tenant_id}-${app_name}" {
  namespace = "tenant-${tenant_id}"
  datacenters = ["dc1"]
  type = "service"

  group "${app_name}" {
    count = ${replicas}

    network {
      mode = "bridge"
      port "app" {
        to = ${container_port}
      }
    }

    task "${app_name}" {
      driver = "docker"

      config {
        image = "registry.infra.example.com/${image}"
        ports = ["app"]
      }

      resources {
        cpu    = ${cpu_mhz}
        memory = ${memory_mb}
      }

      env {
        // テナント環境変数
      }
    }

    service {
      name = "${app_name}"
      port = "app"
      provider = "consul"

      tags = [
        "traefik.enable=true",
        "traefik.http.routers.${app_name}.rule=Host(`${domain}`)",
        "traefik.http.routers.${app_name}.entrypoints=web",
      ]
    }
  }
}
```

> **ポイント:** Consul サービスの `tags` に Traefik のルーティングルールを指定する。
> Traefik が Consul Catalog を watch し、サービスの追加・削除・ポート変更を自動検知してルーティングを更新する。
> テナントがポート番号を意識する必要はない。

### 7.5 Traefik (Ingress Proxy)

Nomad ワーカー上のコンテナへの HTTP/HTTPS アクセスを Consul Catalog ベースで自動ルーティングする。
Nomad system job として全ワーカーにデプロイする。

| 項目 | 値 |
|------|---|
| デプロイ方式 | Nomad system job (全 Worker に 1 インスタンス) |
| ルーティングソース | Consul Catalog (tags ベース) |
| エントリポイント | web (80), websecure (443) |
| ダッシュボード | :8080 (Traefik Dashboard, 内部のみ) |

**Nomad Job Spec (Traefik system job):**

```hcl
job "traefik" {
  datacenters = ["dc1"]
  type = "system"

  group "traefik" {
    network {
      mode = "host"
      port "http" { static = 80 }
      port "https" { static = 443 }
      port "dashboard" { static = 8080 }
    }

    task "traefik" {
      driver = "docker"

      config {
        image = "traefik:v3"
        network_mode = "host"
        volumes = ["/opt/traefik:/etc/traefik"]
      }

      template {
        data = <<-EOF
          [entryPoints]
            [entryPoints.web]
              address = ":80"
            [entryPoints.websecure]
              address = ":443"

          [api]
            dashboard = true
            insecure = true

          [providers.consulCatalog]
            prefix = "traefik"
            exposedByDefault = false
            [providers.consulCatalog.endpoint]
              address = "127.0.0.1:8500"
        EOF
        destination = "local/traefik.toml"
      }

      resources {
        cpu    = 200
        memory = 128
      }
    }
  }
}
```

**トラフィックフロー:**

```
[外部ユーザ] → [VPS nginx (domain routing)] → WireGuard
    → [Transit VM] → [Nomad Worker VM]
        → [Traefik :80/443] → Consul Catalog 参照
            → [コンテナ (dynamic port)]
```

### 7.6 コンテナレジストリ (Harbor)

| 項目 | 値 |
|------|---|
| デプロイ先 | mgmt-docker VM (Compose サービス) |
| アクセスURL | https://registry.infra.example.com (内部 DNS で解決) |
| ストレージバックエンド | 外部 S3 (s3://registry-storage) - S3プロキシ経由 |
| 認証方式 | DB認証 (Harbor内蔵) |
| テナント構成 | プロジェクト: tenant-{id} (push/pull) + library (pull-only) |

> **DNS 活用:** `registry.infra.example.com` は内部インフラゾーン (`infra.example.com`) の A レコードで
> 172.26.26.10 に解決される。クラスタ内 VM は CoreDNS 経由で直接ローカルの Harbor にアクセスする。
> Let's Encrypt (DNS-01、さくら DNS API) で取得した `*.infra.example.com` 証明書を使用。

---

## 8. ユーザ認証・認可設計

### 8.1 ロール定義

| ロール | 説明 | 権限 |
|--------|------|------|
| admin | システム管理者 | 全操作 |
| tenant_admin | テナント管理者 | 自テナントの全操作 |
| tenant_member | テナントメンバー | 自テナントの参照 + 制限付き操作 |

### 8.2 認証フロー

```
[ログイン画面]
    │  メールアドレス + パスワード
    ▼
[Laravel Auth: 認証チェック]
    │
    ▼
[2FA チェック (TOTP) ※有効化済みの場合のみ]
    │  Google Authenticator 等 (任意設定)
    ▼
[セッション作成]
    │
    ▼
[ダッシュボードへリダイレクト]
```

### 8.3 テナントアクセス制御

```php
// EnsureTenantAccess Middleware
// リクエスト内のテナントIDと認証ユーザの所属テナントを照合
// admin ロールの場合は全テナントにアクセス可
```

---

## 9. S3 プロキシ (Go) 設計

### 9.1 概要

Go で実装するカスタム S3 互換プロキシサーバ。独自の認証情報 (Access Key / Secret Key) を発行し、
テナント VM やサービスが外部 S3 の認証情報を直接持つ必要をなくす。

### 9.2 ディレクトリ構成

```
s3-proxy/
├── main.go
├── go.mod
├── go.sum
├── Dockerfile
├── config/
│   └── config.go           # 環境変数からの設定読み込み
├── auth/
│   ├── verifier.go          # AWS Signature V4 検証
│   └── credential_store.go  # 認証情報キャッシュ・DB参照
├── proxy/
│   ├── handler.go           # S3 API リクエストハンドラ
│   ├── rewriter.go          # リクエストパス書き換え (テナントプレフィックス付与)
│   └── signer.go            # 外部 S3 向け再署名
├── middleware/
│   ├── logging.go           # リクエストログ
│   └── ratelimit.go         # レートリミット
└── store/
    └── mysql.go             # MySQL (管理DB) からの認証情報読み込み
```

### 9.3 リクエスト処理フロー

```
[クライアント (テナント VM)]
    │  PUT /dbaas-backups/2026-03-22.sql.gz.gpg
    │  Authorization: AWS4-HMAC-SHA256 Credential=INTERNAL_ACCESS_KEY/...
    ▼
[S3 プロキシ]
    │
    ├── 1. AWS Signature V4 の解析・検証
    │      → credential_store から access_key に対応する secret_key を取得
    │      → 署名を検証
    │
    ├── 2. 認可チェック
    │      → 認証情報に紐づく allowed_bucket, allowed_prefix を確認
    │      → リクエストのバケット・パスがポリシーに合致するか検証
    │
    ├── 3. リクエスト書き換え
    │      → パス: /dbaas-backups/2026-03-22.sql.gz.gpg
    │         → /dbaas-backups/tenant-1/2026-03-22.sql.gz.gpg
    │
    ├── 4. 外部 S3 向け再署名
    │      → 外部 S3 の認証情報で AWS Signature V4 を生成
    │
    └── 5. 外部 S3 に転送 → レスポンスをクライアントに返却
```

### 9.4 認証情報キャッシュ

| 項目 | 値 |
|------|---|
| キャッシュ方式 | インメモリ (sync.Map) |
| 初期ロード | 起動時に全認証情報をDBからロード |
| リフレッシュ | 5分間隔で DB をポーリング |
| 無効化 | 管理パネルが認証情報を削除/無効化 → 次回リフレッシュで反映 |

### 9.5 設定 (環境変数)

```env
# 外部 S3 バックエンド
S3_BACKEND_ENDPOINT=https://s3.wasabisys.com
S3_BACKEND_REGION=ap-northeast-1
S3_BACKEND_ACCESS_KEY=<外部S3アクセスキー>
S3_BACKEND_SECRET_KEY=<外部S3シークレットキー>

# 管理 DB 接続
DB_HOST=mgmt-db
DB_PORT=3306
DB_USER=s3proxy
DB_PASSWORD=<password>
DB_NAME=misosiru_cloud

# プロキシ設定
LISTEN_ADDR=:9000
CACHE_REFRESH_INTERVAL=5m
LOG_LEVEL=info
```

---

## 10. DNS 設計

### 10.1 概要

`.internal` 等の非公開 TLD は **Let's Encrypt 証明書を取得できない** ため使用しない。
すべてのサービスに実ドメインを割り当て、DNS-01 チャレンジで証明書を発行する。

DNS を **グローバル用** (`example.com` — Cloudflare) と **内部インフラ用** (`infra.example.com` — さくらのクラウド DNS 等) に分離する。
CoreDNS はキャッシュ/フォワーダとして動作し、独自ゾーンは持たない。

### 10.2 ユースケース

| ユースケース | ドメイン例 | 解決先 | DNS プロバイダ |
|------------|----------|--------|--------------|
| 内部サービス参照 | `s3.infra.example.com` | 172.26.26.10 | さくら DNS |
| レジストリアクセス | `registry.infra.example.com` | 172.26.26.10 | さくら DNS |
| CaaS コンテナ (ワイルドカード) | `myapp.containers.example.com` | VPS Global IP | Cloudflare |
| スニペット API | `snippet-pve1.infra.example.com` | 172.26.26.11 | さくら DNS |
| 外部ドメイン | `google.com` | 8.8.8.8 へ転送 | (パブリック) |

### 10.3 レコード管理

内部インフラゾーン (`infra.example.com`) のレコードは **外部 DNS プロバイダの API** で管理する。
mgmt-app の管理画面からレコードを編集する場合、DNS プロバイダ API を呼び出す。

```
# レコード変更フロー
[管理画面: DNS 管理画面]
    │  レコード追加/変更/削除
    ▼
[mgmt-app: DNS プロバイダ API 呼び出し]
    │  さくら DNS API / Cloudflare API
    ▼
[DNS プロバイダ: レコード反映]
    │  TTL 経過後にグローバルに伝播
    ▼
[CoreDNS: キャッシュ TTL 経過後に新しいレコードを取得]
```

### 10.4 SSL/TLS 証明書 (Let's Encrypt)

DNS-01 チャレンジを使用し、すべての証明書を Let's Encrypt で取得する。

| 対象 | ドメイン | DNS-01 プロバイダ | 備考 |
|------|--------|------------------|------|
| 内部サービス全般 | `*.infra.example.com` | さくら DNS API | ワイルドカード1枚で全カバー |
| CaaS コンテナ | `*.containers.example.com` | Cloudflare API | VPS で取得 |
| 公開サイト | `example.com`, `*.example.com` | Cloudflare API | VPS で取得 |

---

## 11. 非機能要件

### 11.1 可用性

| 項目 | 目標 | 方式 |
|------|------|------|
| Proxmox クラスタ | ノード障害時に他ノードへVM移行可 | Proxmox HA |
| Nomad | Server 1台障害でもクラスタ継続 | Raft (3台構成) |
| 管理パネル | 単一VM障害時に手動復旧 | バックアップからリストア |
| DBaaS | データロスなし | 定期バックアップ + レプリケーション(将来) |

### 11.2 パフォーマンス

| 項目 | 目標 |
|------|------|
| 管理パネル応答時間 | 1秒以内 (一般画面)、5秒以内 (VM操作) |
| VM作成時間 | 3分以内 (Cloud-init完了まで) |
| バックアップ実行時間 | 10分以内 (10GB以下のDB) |

### 11.3 キャパシティプランニング

| リソース | 現在 | 上限目安 |
|---------|------|---------|
| 物理ノード | 3台 | 拡張可 |
| テナント数 | - | 50程度 |
| VM数 (全体) | - | 100程度 |
| Nomadコンテナ数 | - | 200程度 |
