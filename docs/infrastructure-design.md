# インフラ構成設計書

## 1. 全体構成概要

### 1.1 物理構成

```
[インターネット]
      |
[外部 VPS #1] ──┐
[外部 VPS #2] ──┼── WireGuard VPN ──→ [Transit VM (172.26.27.x)]
[外部 VPS #N] ──┘                                    |
      |                              [vmbr0 / VM Network]
      |                                    |
[自宅ルータ] ── [L2スイッチ] ── [Proxmox Node 1 (pve1)]
                      |          [Proxmox Node 2 (pve2)]
                      |          [Proxmox Node 3 (pve3)]
```

### 1.2 ノード一覧

| ホスト名 | 役割 | 管理IP | 備考 |
|---------|------|--------|------|
| pve1 | Proxmox VE 9 Node | 管理NW | クラスタメンバー |
| pve2 | Proxmox VE 9 Node | 管理NW | クラスタメンバー |
| pve3 | Proxmox VE 9 Node | 管理NW | クラスタメンバー |

---

## 2. ネットワーク設計

### 2.1 ネットワークセグメント

| セグメント | CIDR | 用途 | 備考 |
|-----------|------|------|------|
| 管理ネットワーク | 172.26.26.0/24 | Proxmox管理・クラスタ・Ceph通信 | グローバルアクセス不可 |
| VM ネットワーク | 172.26.27.0/24 | VM間通信・サービス提供 | グローバルアクセス可 |
| テナント用VNet | 10.{tenant_id}.0.0/24 | テナントごとの隔離NW | Proxmox SDN (EVPN/VXLAN) |
| WireGuard VPN | 10.255.{vps_id}.0/24 | VPS-自宅間VPNトンネル | VPSごとに個別サブネット |

### 2.2 Proxmox ブリッジ / SDN 構成

各 Proxmox ノードに以下のブリッジを構成する。

```
vmbr0  : 管理ネットワーク（172.26.26.0/24 - Proxmox管理 + 管理VM用）
vmbr1  : VM ネットワーク（172.26.27.0/24）
```

**Proxmox SDN (EVPN) 構成:**

テナントネットワークは Proxmox SDN の EVPN Zone で管理する。

| SDN コンポーネント | 設定 | 説明 |
|------------------|------|------|
| Zone | EVPN タイプ | VXLAN + BGP EVPN control plane |
| VNet | tenant-{id} | テナントごとに作成 |
| Subnet | 10.{id}.0.0/24 | 各 VNet に紐づくサブネット |
| Gateway | 10.{id}.0.1 | Proxmox SDN の anycast gateway（各ノードで同一IP） |

> **EVPN の動作:** 各 Proxmox ノード上の FRR が BGP EVPN ピアリングを行い、
> テナント VNet の MAC/IP 情報を全ノードで共有する。データプレーンは VXLAN で
> カプセル化され、物理ネットワーク上はノード間の UDP (port 4789) 通信として流れる。
> テナント VM は任意のノードに配置しても同一 L2 セグメントとして通信できる。

### 2.3 外部アクセス経路（VPS経由）

```
[ユーザ] → [外部VPS #N (Global IP)]
               |
               ├── HTTP/HTTPS (tcp/80,443) → WireGuard → [Transit VM] → [対象VM]
               └── Game Server (tcp/udp)   → WireGuard → [Transit VM] → [対象VM]
```

**Transit VM (Proxy VM) 仕様:**

> 詳細は [transit-vm-network-design.md](transit-vm-network-design.md) を参照

| 項目 | 値 |
|------|---|
| OS | Alpine Linux 3.20+ |
| 構成 | Active-Standby 2台 (VRRP冗長化) |
| 主要ソフトウェア | FRR (BGP), WireGuard, Keepalived, nftables |
| IP (Active) | 172.26.27.2, VIP: 172.26.27.1, wg0: 10.255.0.2 |
| IP (Standby) | 172.26.27.3, wg0: 10.255.0.3 |
| 役割 | テナントサブネットの L3 ゲートウェイ、VPS-WireGuard 接続、BGP 経路広告 |
| BGP | AS 65001 ↔ VPS (AS 65000)。テナント追加時に経路を自動広告 |

**VPS 側構成 (複数台):**

各 VPS は管理パネルの `vps_gateways` テーブルで管理され、固有の WireGuard サブネット `10.255.{vps_id}.0/24` を持つ。

| 項目 | 値 |
|------|---|
| 台数 | 複数 (管理パネルで動的管理) |
| WireGuard | Transit VM への常時接続 (各VPS固有のサブネット) |
| リバースプロキシ | nginx でポートベースまたはドメインベースのルーティング |
| ファイアウォール | 必要ポートのみ開放 (80, 443, ゲーム用ポート) |

### 2.4 テナントネットワーク隔離 (EVPN/VXLAN)

各テナントは Proxmox SDN の EVPN Zone 内に独立した VNet (VXLAN) を持つ。

```
テナントA: VNet tenant-1 / 10.1.0.0/24 (VNI 10001)
テナントB: VNet tenant-2 / 10.2.0.0/24 (VNI 10002)
テナントC: VNet tenant-3 / 10.3.0.0/24 (VNI 10003)
...
```

**EVPN による隔離の仕組み:**
- 各テナントの VNet は個別の VXLAN VNI を持ち、L2 レベルで完全に分離
- Proxmox SDN が各ノードに自動的にブリッジ (e.g., `vnet_tenant1`) を作成
- テナント VM は対応する VNet ブリッジに接続される
- 同一テナント内の VM は異なる物理ノードに配置されても VXLAN 経由で L2 通信可能
- テナント間の L2 通信は VNI が異なるため物理的に不可能

**L3 ゲートウェイ:**
- Proxmox SDN が各テナント VNet に anycast gateway (10.{id}.0.1) を設定
- 全ノードで同一 IP を持つため、テナント VM はどのノードにいても同じ GW を使用
- テナント VM → インターネットの通信は SDN gateway → VM Network (vmbr1) → 自宅ルータ or Transit VM

**Dual NIC 構成 (任意):**

テナント VM には任意で 2つ目の NIC (vmbr1 / 172.26.27.0/24) を割り当て可能:

| NIC | ブリッジ | ネットワーク | 用途 |
|-----|---------|---------|------|
| net0 (eth0) | `vnet_{tenant}` | 10.{tenant_id}.0.0/24 | テナント内通信 (必須) |
| net1 (eth1) | `vmbr1` | 172.26.27.0/24 | 共有ネットワーク (任意) |

Cloud-init の network-config v2 で両 NIC の IP を静的に設定し、eth0 のデフォルト GW はテナント VNet の anycast gateway (10.{id}.0.1) とする。

テナント間の通信は原則禁止し、共有サービス（コンテナレジストリなど）へのアクセスのみ管理NW経由で許可する。

---

## 3. VM / サービス配置設計

### 3.0 設計方針: Docker コンテナ前提

管理パネル・スニペットAPI・S3プロキシ・DNSサーバ等のインフラサービスは **すべて Docker コンテナとして起動する**。
専用 VM を個別に建てるのではなく、Docker ホスト VM 上で Docker Compose により一括管理する。

**Compose ファイル構成:**
- `compose.yaml` — ローカル開発用 (build, volume mount あり)
- `compose.prod.yaml` — 本番用 (ビルド済みイメージ参照、マウント不要)

**メリット:**
- デプロイ・再起動・ロールバックが容易 (`docker compose up -d`)
- 1台の VM 上で複数サービスを効率的に実行
- 開発環境と本番環境の差異を最小化
- リソースの無駄が少ない (VM 1台あたりの OS オーバーヘッドを削減)

### 3.1 インフラ VM 一覧

| VM名 | 配置ノード | OS | vCPU | RAM | ディスク | 用途 |
|------|----------|------|------|-----|---------|------|
| mgmt-docker | pve1 | Ubuntu 24.04 | 4 | 8GB | 100GB | 管理サービス Docker ホスト |
| transit-vm-1 | pve1 | Alpine Linux | 1 | 512MB | 5GB | Transit VM (Active) - BGP/WireGuard/VRRP |
| transit-vm-2 | pve2 | Alpine Linux | 1 | 512MB | 5GB | Transit VM (Standby) - BGP/WireGuard/VRRP |

> **注記:** Transit VM はネットワーク機器としての役割のため、Docker 化せず専用 VM として維持する。

### 3.1.1 mgmt-docker VM 上の Docker Compose サービス

mgmt-docker VM 上で Docker Compose により以下のサービスを実行する。

| サービス名 | イメージ/ビルド | ポート | 用途 |
|-----------|---------------|--------|------|
| mgmt-app | カスタムビルド (PHP 8.4 + Nginx) | 80, 443 | Laravel 管理パネル |
| mgmt-queue | mgmt-app と同一イメージ | - | Laravel Queue Worker (非同期ジョブ) |
| mgmt-db | mysql:8.4 | 3306 | 管理用 DB (MySQL) |
| s3-proxy | カスタムビルド (Go) | 9000 | S3 互換プロキシ (独自認証) |
| dns | coredns/coredns | 53 (TCP/UDP) | DNS キャッシュ/フォワーダ |
| registry | goharbor/harbor | 443 | コンテナレジストリ (Harbor) |
| otel-gateway | otel/opentelemetry-collector | 4317, 4318 | OTel Collector Gateway |

```yaml
# compose.yaml (ローカル開発用)
services:
  mgmt-app:
    build: ./mgmt-app
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./mgmt-app/src:/var/www/html        # ソースコードマウント (開発用)
    depends_on:
      - mgmt-db
      - s3-proxy
    env_file: .env
    networks:
      - mgmt-internal

  mgmt-queue:
    build: ./mgmt-app
    command: php artisan queue:work --queue=provisioning --timeout=600 --tries=1 --sleep=3
    volumes:
      - ./mgmt-app/src:/var/www/html
    depends_on:
      - mgmt-db
    env_file: .env
    restart: always
    networks:
      - mgmt-internal

  mgmt-db:
    image: mysql:8.4
    volumes:
      - db-data:/var/lib/mysql
    env_file: .env
    networks:
      - mgmt-internal

  s3-proxy:
    build: ./s3-proxy
    ports:
      - "9000:9000"
    depends_on:
      - mgmt-db
    env_file: .env
    networks:
      - mgmt-internal

  dns:
    image: coredns/coredns
    ports:
      - "53:53/tcp"
      - "53:53/udp"
    volumes:
      - ./coredns:/etc/coredns
    command: -conf /etc/coredns/Corefile
    networks:
      - mgmt-internal

  otel-gateway:
    image: otel/opentelemetry-collector-contrib
    ports:
      - "4317:4317"
      - "4318:4318"
    volumes:
      - ./otel-config.yaml:/etc/otelcol/config.yaml
    networks:
      - mgmt-internal

volumes:
  db-data:

networks:
  mgmt-internal:
    driver: bridge
```

```yaml
# compose.prod.yaml (本番用 — ビルド済みイメージ、マウント不要)
services:
  mgmt-app:
    image: ${REGISTRY}/mgmt-app:${TAG:-latest}
    ports:
      - "80:80"
      - "443:443"
    depends_on:
      - mgmt-db
      - s3-proxy
    env_file: .env
    restart: always
    networks:
      - mgmt-internal

  mgmt-queue:
    image: ${REGISTRY}/mgmt-app:${TAG:-latest}
    command: php artisan queue:work --queue=provisioning --timeout=600 --tries=1 --sleep=3
    depends_on:
      - mgmt-db
    env_file: .env
    restart: always
    networks:
      - mgmt-internal

  mgmt-db:
    image: mysql:8.4
    volumes:
      - db-data:/var/lib/mysql
    env_file: .env
    restart: always
    networks:
      - mgmt-internal

  s3-proxy:
    image: ${REGISTRY}/s3-proxy:${TAG:-latest}
    ports:
      - "9000:9000"
    depends_on:
      - mgmt-db
    env_file: .env
    restart: always
    networks:
      - mgmt-internal

  dns:
    image: coredns/coredns
    ports:
      - "53:53/tcp"
      - "53:53/udp"
    volumes:
      - ./coredns/Corefile:/etc/coredns/Corefile:ro
    command: -conf /etc/coredns/Corefile
    restart: always
    networks:
      - mgmt-internal

  otel-gateway:
    image: otel/opentelemetry-collector-contrib
    ports:
      - "4317:4317"
      - "4318:4318"
    volumes:
      - ./otel-config.yaml:/etc/otelcol/config.yaml:ro
    restart: always
    networks:
      - mgmt-internal

volumes:
  db-data:

networks:
  mgmt-internal:
    driver: bridge
```

### 3.1.2 各 Proxmox ノード上のスニペット API (Docker)

スニペット API は各 Proxmox ノードのローカルスニペットディレクトリに書き込む必要があるため、
各ノードで Docker コンテナとして直接実行する。

| ノード | コンテナ名 | ポート | ボリューム |
|--------|-----------|--------|-----------|
| pve1 | snippet-api | 8100 | /var/lib/vz/snippets:/app/snippets |
| pve2 | snippet-api | 8100 | /var/lib/vz/snippets:/app/snippets |
| pve3 | snippet-api | 8100 | /var/lib/vz/snippets:/app/snippets |

```bash
# 各 Proxmox ノードで実行
docker run -d \
  --name snippet-api \
  --restart always \
  -p 172.26.26.x:8100:8100 \
  -v /var/lib/vz/snippets:/app/snippets \
  -e SNIPPET_DIR=/app/snippets \
  -e API_TOKEN=${SNIPPET_API_TOKEN} \
  misosiru-cloud/snippet-api:latest
```

### 3.2 テナントVM（動的生成）

テナントの要求に応じて Cloud-init テンプレート VM を **クローン** して動的にデプロイされるVM。
クローン後に **ディスクリサイズ** (Proxmox API: `PUT /resize`) で希望サイズに拡張し、
VM 起動時に cloud-init の `growpart` + `resize2fs` がパーティション/ファイルシステムを自動拡張する。

| 用途 | ベースイメージ | 初期リソース | 備考 |
|------|-------------|------------|------|
| 汎用VM | Ubuntu 24.04 cloud image | 1vCPU / 1GB / 20GB | テナントが自由に利用 |
| DBaaS MySQL | Ubuntu 24.04 + MySQL | 2vCPU / 2GB / 40GB | Cloud-init で自動構成 |
| DBaaS PostgreSQL | Ubuntu 24.04 + PostgreSQL | 2vCPU / 2GB / 40GB | Cloud-init で自動構成 |
| DBaaS Redis | Ubuntu 24.04 + Redis | 1vCPU / 1GB / 20GB | Cloud-init で自動構成 |
| Nomad Worker | Ubuntu 24.04 + Nomad + Docker | 4vCPU / 8GB / 80GB | コンテナ基盤ワーカー |

### 3.3 Nomad クラスタ構成

```
[Nomad Server (3台 - 管理VM or 専用VM)]
        |
        ├── [Nomad Worker - pve1] (テナントコンテナ実行)
        ├── [Nomad Worker - pve2] (テナントコンテナ実行)
        └── [Nomad Worker - pve3] (テナントコンテナ実行)
```

| コンポーネント | 台数 | 配置 | 備考 |
|-------------|------|------|------|
| Nomad Server | 3 | 各ノードに1台ずつ | Raft合意のため奇数台 |
| Nomad Worker | 3+ | 各ノードに1台以上 | テナントの需要に応じてスケール |
| Consul Server | 3 | Nomad Server と同居可 | サービスディスカバリ |
| Traefik | 3+ | Nomad system job (全 Worker) | Consul Catalog ベース Ingress Proxy |

---

## 4. ストレージ設計

### 4.1 Proxmox ストレージ構成

| ストレージ名 | タイプ | 用途 | 備考 |
|------------|------|------|------|
| local | dir | ISOイメージ、Cloud-init スニペット | 各ノードローカル |
| local-lvm | LVM-thin | 一時的なVM / テスト用 | 各ノードローカル (非推奨) |
| ceph-pool | Ceph RBD | **VM ディスク (メイン)** | 3ノードクラスタ、ライブマイグレーション対応 |
| snippets | dir (/var/lib/vz/snippets) | Cloud-init スニペットファイル | スニペットAPIが書き込み |

### 4.1.1 Ceph 構成

3台の Proxmox ノードで Ceph クラスタを構成済み。

| 項目 | 値 |
|------|---|
| クラスタ構成 | 3ノード (pve1, pve2, pve3) |
| OSD | 各ノードのディスクを OSD として構成 |
| Mon | 各ノードで稼働 (3 Mon) |
| RBD Pool | ceph-pool (VM ディスク用) |
| レプリケーション | size=3, min_size=2 |

**Ceph を使うメリット:**
- VM のライブマイグレーションが可能（ノード障害時に他ノードへ移動）
- データは3ノードに分散冗長化（ノード1台故障してもデータロスなし）
- Proxmox HA と組み合わせて VM の自動復旧が可能

### 4.2 バックアップストレージ (外部 S3)

**外部 S3 サービス (AWS S3 / Wasabi 等) を使用する。** 自前の MinIO は運用しない。

| バケット名 | 用途 | 保持ポリシー | 暗号化 |
|-----------|------|------------|--------|
| dbaas-backups | DBaaS の定期バックアップ (sqldump) | 世代管理 (7日/4週/3月) | クライアントサイド (gpg AES-256) + サーバサイド (SSE-S3) |
| log-archives | ログの長期アーカイブ | 90日 → Glacier/低頻度階層 | SSE-S3 |
| vm-snapshots | VM スナップショット（任意） | 3世代 | SSE-S3 |
| registry-storage | コンテナイメージ保存 | - | SSE-S3 |

### 4.3 バックアップ方式 (S3 プロキシ経由)

**外部 S3 の認証情報はテナント VM に配布しない。** カスタム Go S3 プロキシが内部認証情報を発行し、
テナント VM は S3 プロキシ経由でバックアップをアップロードする。

```
[DBaaS VM] → cron: sqldump → gzip → gpg (AES-256)
    │
    ▼
[S3互換アップロード (aws cli / mc)]
    │  エンドポイント: http://s3.infra.example.com:9000
    │  認証: S3プロキシ発行の内部 Access Key / Secret Key
    ▼
[S3 プロキシ (s3-proxy コンテナ)]
    │  内部認証情報を検証 → テナント別バケットプレフィックスを付与
    │  外部 S3 (AWS/Wasabi) に転送
    ▼
[外部 S3 (AWS S3 / Wasabi)]
    s3://dbaas-backups/{tenant_id}/{db_id}/{date}.sql.gz.gpg
```

| 項目 | 方針 |
|------|------|
| 外部 S3 認証情報の保管場所 | s3-proxy コンテナの環境変数のみ (mgmt-docker VM) |
| 内部 S3 認証情報 | S3 プロキシが発行、管理DB (s3_credentials テーブル) に保存 |
| テナント VM の役割 | dump → 圧縮 → 暗号化 → S3 プロキシにアップロード |
| テナント隔離 | S3 プロキシが認証情報ごとにバケット/プレフィックスを制限 |
| 暗号化キー | テナントごとに異なるキー (管理DBに暗号化保存) |
| リストア | 管理パネルが S3 プロキシから取得 → SSH で VM に転送 → 復号・リストア |

**SSH/SCP 集中管理モデルとの比較:**
- ~~旧: mgmt-app が SSH で各 VM からファイルを取得 → S3 にアップロード~~
- 新: VM が直接 S3 プロキシにアップロード。mgmt-app の負荷を軽減し、並列アップロードが可能

---

## 5. 監視・可観測性設計

### 5.1 監視アーキテクチャ

```
[各VM / Nomadタスク]
      |
      ├── メトリクス: node_exporter / cAdvisor
      ├── ログ: journald / アプリログ → Alloy / Fluent Bit
      └── トレース: アプリケーション内蔵 OTLP
      |
      ↓
[OTel Collector Agent (各ノード)]
      |
      ↓
[OTel Collector Gateway (otel-gateway VM)]
      |
      ├── メトリクス → Grafana Cloud (Prometheus)
      ├── ログ → Grafana Cloud (Loki)  ※長期分は外部S3にアーカイブ
      └── トレース → Grafana Cloud (Tempo)
```

### 5.2 OTel Collector 構成

**Agent (各ノード/Worker):**

| 項目 | 値 |
|------|---|
| デプロイ方式 | Nomad system job / systemd service |
| Receiver | prometheus (node_exporter, cAdvisor), otlp |
| Processor | batch, memory_limiter, attributes (tenant_id付与) |
| Exporter | otlphttp (→ Gateway) |

**Gateway (集約):**

| 項目 | 値 |
|------|---|
| Receiver | otlp (Agent からの受信) |
| Processor | batch, filter, transform |
| Exporter | prometheusremotewrite (Grafana Cloud), loki (Grafana Cloud) |

### 5.3 ログ管理

| ログソース | 収集方法 | 送信先 | アーカイブ |
|-----------|---------|--------|----------|
| VM syslog | journald → Alloy | Grafana Cloud Loki | 30日超 → 外部S3 (gzip) |
| DBaaS ログ | 各DBのログファイル → Alloy | Grafana Cloud Loki | 30日超 → 外部S3 (gzip) |
| Nomad タスクログ | Nomad log API → Alloy | Grafana Cloud Loki | 30日超 → 外部S3 (gzip) |
| 管理パネルログ | Laravel Log → Alloy | Grafana Cloud Loki | - |

---

## 6. セキュリティ設計

### 6.1 ネットワークセキュリティ

| 対象 | 方式 | 詳細 |
|------|------|------|
| VPS - 自宅間 | WireGuard | 常時VPN接続、鍵認証 |
| テナント間隔離 | EVPN/VXLAN (Proxmox SDN) | VNI による L2 レベルでの完全隔離 |
| Proxmox API | HTTPS + APIトークン | 管理NWからのみアクセス可 |
| 管理パネル | HTTPS + 認証 | 管理NW or VPN経由のみ |

### 6.2 認証・認可

| 対象 | 方式 |
|------|------|
| 管理パネル | Laravel標準認証 (session-based) + 2FA (TOTP, 任意) |
| Proxmox API | API Token (per-tenant scope) |
| スニペットAPI | 内部ネットワーク + Bearer Token |
| コンテナレジストリ | Harbor (OIDC or DB認証) |
| 外部 S3 (AWS/Wasabi) | IAM Policy (バケット単位), 認証情報は s3-proxy コンテナのみ保持 |
| S3 プロキシ | 独自 Access Key / Secret Key 発行 (テナント別、管理DBに保存) |
| 内部 DNS | ネットワーク隔離のみ (管理NW内で稼働、認証不要) |

### 6.3 ファイアウォールルール概要

```
# Proxmox ノード間
管理NW: 全ポート許可（クラスタ通信）

# VM Network
172.26.27.0/24:
  - 同一テナントVNet(VXLAN)内: 全許可
  - テナント間: 全拒否 (異なるVNIのため物理的に不可)
  - → 管理VM (mgmt-docker): 拒否（管理NW経由のみ）
  - → コンテナレジストリ: tcp/443 許可
  - → S3 プロキシ: tcp/9000 許可
  - → 内部 DNS: tcp+udp/53 許可
  - → インターネット (outbound): 許可

# mgmt-docker VM
  - tcp/80,443: 管理パネル (管理NW or VPN経由のみ)
  - tcp/9000: S3 プロキシ (全VMからアクセス可)
  - tcp+udp/53: 内部 DNS (全VMからアクセス可)
  - tcp/4317,4318: OTel Collector Gateway

# Proxy VM
  - VPS → Proxy: WireGuard (udp/51820)
  - Proxy → テナントVM: 許可ポートのみ転送
```

---

## 6.4 S3 プロキシ設計

### 6.4.1 概要

カスタム Go 製の S3 互換プロキシサーバ。**独自の認証情報 (Access Key / Secret Key) を発行**し、
テナント VM やサービスが外部 S3 の認証情報を直接持つ必要をなくす。

### 6.4.2 アーキテクチャ

```
[テナント VM / サービス]
    │  S3互換リクエスト (PutObject, GetObject, etc.)
    │  Authorization: AWS4-HMAC-SHA256 (内部credentials)
    ▼
[S3 プロキシ (s3.infra.example.com:9000)]
    │  1. AWS Signature V4 を検証 (内部 Access Key / Secret Key)
    │  2. 認証情報からテナントIDを特定
    │  3. リクエストパスにテナントプレフィックスを付与
    │  4. 外部 S3 の認証情報で再署名
    │  5. 外部 S3 に転送
    ▼
[外部 S3 (AWS S3 / Wasabi)]
```

### 6.4.3 対応 S3 操作 (サブセット)

| 操作 | S3 API | 用途 |
|------|--------|------|
| PutObject | PUT /{bucket}/{key} | バックアップアップロード、ログアーカイブ |
| GetObject | GET /{bucket}/{key} | バックアップダウンロード (リストア用) |
| DeleteObject | DELETE /{bucket}/{key} | 古いバックアップ削除 |
| ListObjectsV2 | GET /{bucket}?list-type=2 | バックアップ一覧取得 |
| HeadObject | HEAD /{bucket}/{key} | オブジェクト存在確認 |
| CreateMultipartUpload | POST /{bucket}/{key}?uploads | 大容量ファイル分割アップロード |
| UploadPart / CompleteMultipartUpload | - | マルチパートアップロード完了 |

### 6.4.4 認証情報管理

- 管理パネル (mgmt-app) が `s3_credentials` テーブルに認証情報を作成
- テナント作成時に自動でデフォルトの S3 認証情報を発行
- 認証情報ごとにアクセス可能なバケット・プレフィックスを制限
- S3 プロキシは管理 DB (`s3_credentials` テーブル) を参照して認証を検証
- 認証情報のキャッシュ: 起動時にロード + 5分間の TTL でリフレッシュ

### 6.4.5 テナント隔離

```
テナントA の認証情報: allowed_bucket=dbaas-backups, allowed_prefix=tenant-1/
テナントB の認証情報: allowed_bucket=dbaas-backups, allowed_prefix=tenant-2/

テナントA が PUT /dbaas-backups/some-file.gpg を実行:
  → S3プロキシが PUT /dbaas-backups/tenant-1/some-file.gpg に書き換えて外部S3に転送

テナントA が PUT /dbaas-backups/tenant-2/file.gpg を実行:
  → プレフィックス不一致 → 403 Forbidden
```

---

## 6.5 DNS 設計

### 6.5.1 概要

`.internal` 等の非公開 TLD は **Let's Encrypt 証明書を取得できない** ため使用しない。
すべてのサービスに実ドメインを割り当て、DNS-01 チャレンジで Let's Encrypt 証明書を発行する。

DNS を **グローバル用** と **内部インフラ用** の2つのゾーンに分離し、それぞれ異なるプロバイダで管理する。

### 6.5.2 DNS ゾーン設計

| ゾーン | プロバイダ | 用途 | 例 |
|--------|----------|------|----|
| `example.com` | Cloudflare | 公開サービス・CaaS ワイルドカード | `app.example.com`, `*.containers.example.com` |
| `infra.example.com` | さくらのクラウド DNS 等 | 内部インフラサービス (プライベートIP) | `s3.infra.example.com`, `registry.infra.example.com` |

> **ポイント:** `infra.example.com` は `example.com` のサブドメインだが、NS レコード委任により別プロバイダで管理する。
> Cloudflare 側で `infra.example.com` の NS レコードをさくら DNS に向ける。

### 6.5.3 DNS レコード一覧

**グローバルゾーン (`example.com` — Cloudflare):**

| レコード | タイプ | 値 | 用途 |
|---------|------|---|------|
| `example.com` | A | VPS Global IP | メインサイト |
| `*.containers.example.com` | A | VPS Global IP (複数) | CaaS コンテナ ワイルドカード |
| `infra.example.com` | NS | さくら DNS の NS | ゾーン委任 |

> **CaaS ワイルドカード:** テナントがコンテナをデプロイすると `{app}.containers.example.com` が自動割当される。
> VPS の nginx → Traefik → コンテナの経路で Host ヘッダベースルーティングされる。

**内部インフラゾーン (`infra.example.com` — さくらのクラウド DNS 等):**

| レコード | タイプ | 値 | 用途 |
|---------|------|---|------|
| `mgmt.infra.example.com` | A | 172.26.26.10 | 管理パネル (Laravel) |
| `s3.infra.example.com` | A | 172.26.26.10 | S3 プロキシ |
| `registry.infra.example.com` | A | 172.26.26.10 | コンテナレジストリ (Harbor) |
| `dns.infra.example.com` | A | 172.26.26.10 | CoreDNS |
| `otel.infra.example.com` | A | 172.26.26.10 | OTel Collector Gateway |
| `snippet-pve1.infra.example.com` | A | 172.26.26.11 | スニペットAPI (pve1) |
| `snippet-pve2.infra.example.com` | A | 172.26.26.12 | スニペットAPI (pve2) |
| `snippet-pve3.infra.example.com` | A | 172.26.26.13 | スニペットAPI (pve3) |
| `*.infra.example.com` | A | 172.26.26.10 | 将来の内部サービス用ワイルドカード |

> **注記:** `infra.example.com` の A レコードはプライベート IP を指す。
> パブリック DNS に登録されるが、プライベート IP は外部から到達不可のため安全。

### 6.5.4 SSL/TLS 証明書 (Let's Encrypt)

**すべての証明書を Let's Encrypt (DNS-01 チャレンジ) で取得する。**
HTTP-01 チャレンジは内部サービスに使用できないため、DNS-01 を全面採用する。

| 証明書 | ドメイン | DNS プロバイダ (DNS-01) | 取得場所 |
|--------|--------|----------------------|----------|
| 管理パネル | `mgmt.infra.example.com` | さくら DNS API | mgmt-docker VM |
| S3 プロキシ | `s3.infra.example.com` | さくら DNS API | mgmt-docker VM |
| レジストリ | `registry.infra.example.com` | さくら DNS API | mgmt-docker VM |
| CaaS ワイルドカード | `*.containers.example.com` | Cloudflare API | VPS |
| VPS サイト | `example.com`, `*.example.com` | Cloudflare API | VPS |
| 内部ワイルドカード | `*.infra.example.com` | さくら DNS API | mgmt-docker VM |

> **実運用では `*.infra.example.com` のワイルドカード証明書1枚** で全内部サービスをカバーできる。
> certbot + DNS プロバイダプラグインで自動取得・更新する。

```bash
# 内部ワイルドカード証明書取得 (mgmt-docker VM)
certbot certonly \
  --dns-sakuracloud \
  --dns-sakuracloud-credentials /etc/letsencrypt/sakura.ini \
  -d "*.infra.example.com" \
  -d "infra.example.com"

# VPS でのワイルドカード証明書取得
certbot certonly \
  --dns-cloudflare \
  --dns-cloudflare-credentials /etc/letsencrypt/cloudflare.ini \
  -d "*.containers.example.com" \
  -d "*.example.com" \
  -d "example.com"
```

### 6.5.5 CoreDNS (キャッシュ/フォワーダ)

CoreDNS は **キャッシュ兼フォワーダ** として機能する。
内部サービスのドメイン解決は外部 DNS プロバイダ (さくら DNS) に委任されており、
CoreDNS は独自ゾーンを持たない。

| 項目 | 値 |
|------|---|
| ソフトウェア | CoreDNS |
| 起動方式 | Docker コンテナ (mgmt-docker VM 上) |
| リッスンIP | 172.26.26.10:53 (管理NW) |
| 役割 | キャッシュ + フォワーダ (+ オプショナルのスプリットホライズン) |
| フォワーダ | 8.8.8.8 / 8.8.4.4 |

**CoreDNS 設定 (Corefile):**

```
.:53 {
    hosts /etc/coredns/override.hosts {
        fallthrough
    }
    forward . 8.8.8.8 8.8.4.4
    cache 300
    log
}
```

**override.hosts (スプリットホライズン — 必要な場合のみ):**
```
# 例: グローバルドメインを内部IPに上書きしたい場合
# 172.26.26.10  registry.example.com
```

> **ポイント:** `infra.example.com` のレコードは外部 DNS プロバイダに実 A レコードとして登録済みのため、
> CoreDNS がフォワーダとして `8.8.8.8` に転送するだけで正しくプライベート IP に解決される。
> `.internal` ゾーン管理は不要になり、CoreDNS の役割が大幅に簡素化される。

---

## 7. テンプレート VM 設計 (Packer + Cloud-init)

### 7.1 概要

テンプレート VM は **Packer** (proxmox-clone ビルダー) で自動生成する。
Ubuntu 24.04 cloud image をベースに、用途別のプロビジョニングを Packer で実行し、
Proxmox テンプレート (Read-Only) として保存する。

**メリット:**
- テンプレート作成が再現可能・冪等 (`packer build` で同じテンプレートを何度でも生成)
- バージョンアップ時に新テンプレートを作って差し替え可能
- 手順書の「手動でこのコマンドを実行」がなくなる

### 7.2 ディレクトリ構成

```
packer/
├── base-ubuntu.pkr.hcl          # ベーステンプレート定義
├── dbaas-mysql.pkr.hcl          # MySQL DBaaS テンプレート
├── dbaas-postgres.pkr.hcl       # PostgreSQL DBaaS テンプレート
├── dbaas-redis.pkr.hcl          # Redis DBaaS テンプレート
├── nomad-worker.pkr.hcl         # Nomad Worker テンプレート
├── variables.pkr.hcl            # 共通変数定義
├── http/
│   └── user-data                # Packer ビルド用の初回 cloud-init (autoinstall)
└── scripts/
    ├── base.sh                  # 共通セットアップ (ユーザ, SSH, timezone, DNS, node_exporter)
    ├── cleanup.sh               # テンプレート化前のクリーンアップ
    ├── mysql.sh                 # MySQL インストール + 初期設定
    ├── postgres.sh              # PostgreSQL インストール + 初期設定
    ├── redis.sh                 # Redis インストール + 初期設定
    └── nomad-worker.sh          # Docker + Nomad agent + cAdvisor
```

### 7.3 テンプレート一覧

| テンプレート名 | VMID 範囲 | ベース | Packer でインストールするもの |
|-------------|----------|--------|---------------------------|
| base-ubuntu | 9000 | Ubuntu 24.04 cloud image | qemu-guest-agent, node_exporter, 共通ユーザ設定 |
| dbaas-mysql | 9010 | base-ubuntu クローン | MySQL 8.4 パッケージ |
| dbaas-postgres | 9011 | base-ubuntu クローン | PostgreSQL 17 パッケージ |
| dbaas-redis | 9012 | base-ubuntu クローン | Redis 7.x パッケージ |
| nomad-worker | 9020 | base-ubuntu クローン | Docker, Nomad agent, cAdvisor |

> **Packer vs Cloud-init の役割分担:**
> - **Packer (ビルド時):** パッケージインストール、バイナリ配置など**時間がかかる共通処理**
> - **Cloud-init (デプロイ時):** IP 設定、DB ユーザ/パスワード作成、テナント固有設定など**インスタンス固有の処理**

### 7.4 Packer テンプレート例 (base-ubuntu)

```hcl
# base-ubuntu.pkr.hcl
packer {
  required_plugins {
    proxmox = {
      version = ">= 1.2.0"
      source  = "github.com/hashicorp/proxmox"
    }
  }
}

source "proxmox-iso" "base-ubuntu" {
  proxmox_url              = var.proxmox_url
  username                 = var.proxmox_username
  token                    = var.proxmox_token
  node                     = var.proxmox_node
  insecure_skip_tls_verify = true

  iso_file    = "local:iso/ubuntu-24.04-live-server-amd64.iso"
  unmount_iso = true

  vm_id                = 9000
  vm_name              = "tmpl-base-ubuntu"
  template_description = "Base Ubuntu 24.04 template (Packer built)"
  qemu_agent           = true

  cores  = 2
  memory = 2048

  scsi_controller = "virtio-scsi-single"
  disks {
    type         = "scsi"
    disk_size    = "2G"
    storage_pool = "local-lvm"
  }

  network_adapters {
    model  = "virtio"
    bridge = "vmbr0"
  }

  cloud_init             = true
  cloud_init_storage_pool = "local-lvm"

  http_directory = "http"
  boot_command = [
    "<esc><wait>",
    "e<down><down><down><end>",
    " autoinstall ds=nocloud-net\\;s=http://{{ .HTTPIP }}:{{ .HTTPPort }}/",
    "<F10>"
  ]
  boot_wait = "5s"

  ssh_username = "packer"
  ssh_password = var.ssh_password
  ssh_timeout  = "15m"
}

build {
  sources = ["source.proxmox-iso.base-ubuntu"]

  provisioner "shell" {
    scripts = [
      "scripts/base.sh",
      "scripts/cleanup.sh",
    ]
  }
}
```

### 7.5 Packer テンプレート例 (dbaas-mysql)

```hcl
# dbaas-mysql.pkr.hcl
source "proxmox-clone" "dbaas-mysql" {
  proxmox_url              = var.proxmox_url
  username                 = var.proxmox_username
  token                    = var.proxmox_token
  node                     = var.proxmox_node
  insecure_skip_tls_verify = true

  clone_vm_id          = 9000   # base-ubuntu テンプレート
  vm_id                = 9010
  vm_name              = "tmpl-dbaas-mysql"
  template_description = "MySQL 8.4 DBaaS template (Packer built)"

  cores  = 2
  memory = 2048
  qemu_agent = true

  ssh_username = "packer"
  ssh_password = var.ssh_password
  ssh_timeout  = "10m"
}

build {
  sources = ["source.proxmox-clone.dbaas-mysql"]

  provisioner "shell" {
    scripts = [
      "scripts/mysql.sh",
      "scripts/cleanup.sh",
    ]
  }
}
```

> **dbaas-postgres, dbaas-redis, nomad-worker** も同じパターンで
> `clone_vm_id = 9000` からクローンし、用途別スクリプトを実行する。

### 7.6 共通変数定義

```hcl
# variables.pkr.hcl
variable "proxmox_url" {
  type    = string
  default = "https://pve1.infra.example.com:8006/api2/json"
}

variable "proxmox_username" {
  type    = string
  default = "packer@pve!packer-token"
}

variable "proxmox_token" {
  type      = string
  sensitive = true
}

variable "proxmox_node" {
  type    = string
  default = "pve1"
}

variable "ssh_password" {
  type      = string
  sensitive = true
}
```

### 7.7 プロビジョニングスクリプト例

```bash
# scripts/base.sh
#!/bin/bash
set -euxo pipefail

# パッケージ更新
apt-get update && apt-get upgrade -y

# 共通パッケージ
apt-get install -y \
  qemu-guest-agent \
  curl \
  jq \
  gpg \
  awscli

# タイムゾーン
timedatectl set-timezone Asia/Tokyo

# node_exporter (Prometheus メトリクス)
useradd --no-create-home --shell /bin/false node_exporter || true
curl -fsSL https://github.com/prometheus/node_exporter/releases/download/v1.8.2/node_exporter-1.8.2.linux-amd64.tar.gz \
  | tar xz --strip-components=1 -C /usr/local/bin/ node_exporter-1.8.2.linux-amd64/node_exporter
cat <<'EOF' > /etc/systemd/system/node_exporter.service
[Unit]
Description=Node Exporter
After=network.target
[Service]
User=node_exporter
ExecStart=/usr/local/bin/node_exporter
[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable node_exporter

# cloud-init で上書きされるため、ここでは DNS 設定しない
```

```bash
# scripts/mysql.sh
#!/bin/bash
set -euxo pipefail

# MySQL 8.4 インストール
apt-get install -y mysql-server-8.4

# サービス無効化 (cloud-init で設定後に起動する)
systemctl stop mysql
systemctl disable mysql
```

```bash
# scripts/cleanup.sh
#!/bin/bash
set -euxo pipefail

# Packer ビルドユーザ削除 (cloud-init で正式ユーザを作成する)
# ※ SSH 切断後に実行されるため最後に配置

# ログクリア
truncate -s 0 /var/log/*.log || true
journalctl --vacuum-time=1s

# machine-id リセット (クローン時に再生成される)
truncate -s 0 /etc/machine-id
rm -f /var/lib/dbus/machine-id

# cloud-init クリーンアップ (次回起動時に再実行させる)
cloud-init clean --logs

# apt キャッシュ削除
apt-get clean
rm -rf /var/lib/apt/lists/*

# 一時ファイル
rm -rf /tmp/* /var/tmp/*

# Bash history
unset HISTFILE
rm -f /root/.bash_history /home/*/.bash_history

sync
```

### 7.8 ビルドフロー

```
[開発者: packer build]
    │
    ▼
[Packer: proxmox-iso ビルダー]
    │  1. Ubuntu 24.04 ISO から VM 作成
    │  2. autoinstall で OS 自動インストール
    │  3. SSH 接続 → scripts/base.sh 実行
    │  4. scripts/cleanup.sh 実行
    │  5. VM → テンプレート変換
    ▼
[Proxmox: テンプレート VM (VMID 9000)]
    │  tmpl-base-ubuntu (Read-Only)
    │
    ▼
[Packer: proxmox-clone ビルダー (並列可能)]
    ├── dbaas-mysql (9010):  clone 9000 → scripts/mysql.sh → テンプレート化
    ├── dbaas-postgres (9011): clone 9000 → scripts/postgres.sh → テンプレート化
    ├── dbaas-redis (9012):  clone 9000 → scripts/redis.sh → テンプレート化
    └── nomad-worker (9020): clone 9000 → scripts/nomad-worker.sh → テンプレート化

[管理パネル: clone 時に template_vmid を指定]
    └── POST /nodes/{node}/qemu/9000/clone   (汎用 VM)
    └── POST /nodes/{node}/qemu/9010/clone   (MySQL DBaaS)
    └── POST /nodes/{node}/qemu/9011/clone   (PostgreSQL DBaaS)
    └── ...
```

### 7.9 テンプレート更新運用

テンプレートの更新 (OS パッチ、DB バージョンアップなど) は以下の手順で行う:

1. `packer build` で新しいテンプレートを一時 VMID (例: 9100) に作成
2. 動作確認 (手動でクローンしてテスト)
3. 旧テンプレートを削除、新テンプレートの VMID を正規 VMID にリネーム
   - または管理 DB の `proxmox_nodes.template_vmid` を新 VMID に更新
4. 以後の VM 作成は新テンプレートからクローンされる

> 既存 VM は影響を受けない (フルクローンのため)。

---

## 8. Cloud-init (デプロイ時設定)

### 8.1 Cloud-init の役割

Packer でテンプレート化された VM をクローンした後、**インスタンス固有の設定** を Cloud-init で注入する。

| 設定項目 | Cloud-init で注入 | 理由 |
|---------|------------------|------|
| IP アドレス | network-config.yaml | インスタンスごとに異なる |
| ホスト名 | meta-data.yaml | インスタンスごとに異なる |
| SSH 公開鍵 | user-data.yaml | テナント/ユーザごとに異なる |
| DNS 参照先 | user-data.yaml | 全 VM 共通だがテンプレートに焼かない (変更可能性) |
| DB ユーザ/パスワード | user-data.yaml | DBaaS インスタンスごとに異なる |
| DB 設定ファイル | user-data.yaml | スペック・チューニングがインスタンスごとに異なる |
| バックアップ cron | user-data.yaml | S3 認証情報がテナントごとに異なる |

### 8.2 DNS 設定 (全テンプレート共通)

全ての Cloud-init user-data に以下の DNS 設定を含める。

```yaml
# cloud-config (抜粋)
manage_resolv_conf: true
resolv_conf:
  nameservers:
    - 172.26.26.10    # CoreDNS (キャッシュ/フォワーダ)
  options:
    edns0: true
    trust-ad: true
```

> **効果:** VM 起動時に `/etc/resolv.conf` が CoreDNS を参照するよう自動設定される。
> `s3.infra.example.com`, `registry.infra.example.com` 等の実ドメインが正しく解決される。
> CoreDNS がキャッシュするため、外部 DNS への問い合わせ回数を削減できる。

### 8.3 スニペットファイル配置フロー

```
[管理パネル (Laravel)]
      |
      | POST /api/snippets (yaml payload)
      ↓
[スニペット保存API (Python/FastAPI)]
      |
      | ファイル書き込み: /var/lib/vz/snippets/{vm_id}-user.yaml
      ↓
[Proxmox ノードのローカルストレージ]
      |
      | cloud-init drive として VM にアタッチ
      ↓
[VM 起動時に cloud-init 実行]
```

---

## 9. 構成図（ネットワークトポロジ）

```
                        ┌──────────────┐
                        │  Internet    │
                        └──────┬───────┘
                               │
                        ┌──────┴───────┐
                        │  外部 VPS    │
                        │  (Global IP) │
                        │  nginx/HAProxy│
                        │  WireGuard   │
                        └──────┬───────┘
                               │ WireGuard Tunnel
                               │ (10.255.0.0/24)
                               │
┌──────────────────────────────┼──────────────────────────────┐
│  自宅ネットワーク            │                              │
│                       ┌──────┴───────┐                      │
│                       │  Proxy VM    │                      │
│                       │ 172.26.27.x  │                      │
│                       └──────┬───────┘                      │
│                              │                              │
│  ┌───────────────────────────┼───────────────────────────┐  │
│  │         VM Network (172.26.27.0/24 / vmbr1)           │  │
│  │                           │                           │  │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐              │  │
│  │  │ Tenant  │  │ DBaaS   │  │ Nomad   │   ...        │  │
│  │  │ VM      │  │ VM      │  │ Worker  │              │  │
│  │  └─────────┘  └─────────┘  └─────────┘              │  │
│  │      │              │            │                    │  │
│  │      │    S3プロキシ / DNS / レジストリ参照           │  │
│  └──────┼──────────────┼────────────┼────────────────────┘  │
│         │              │            │                        │
│  ┌──────┼──────────────┼────────────┼────────────────────┐  │
│  │      ▼  管理 Network (vmbr0)    ▼                     │  │
│  │                                                       │  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │ mgmt-docker VM (Docker Compose)                 │  │  │
│  │  │  ┌─────────┐ ┌────────┐ ┌─────┐ ┌──────────┐  │  │  │
│  │  │  │mgmt-app │ │s3-proxy│ │ dns │ │ registry │  │  │  │
│  │  │  │(Laravel)│ │  (Go)  │ │Core │ │ (Harbor) │  │  │  │
│  │  │  └─────────┘ └────────┘ │ DNS │ └──────────┘  │  │  │
│  │  │  ┌─────────┐            └─────┘ ┌──────────┐  │  │  │
│  │  │  │ mgmt-db │                    │ otel-gw  │  │  │  │
│  │  │  │ (MySQL) │                    │          │  │  │  │
│  │  │  └─────────┘                    └──────────┘  │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  │                                                       │  │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐              │  │
│  │  │ pve1    │  │ pve2    │  │ pve3    │              │  │
│  │  │+snippet │  │+snippet │  │+snippet │              │  │
│  │  │ -api    │  │ -api    │  │ -api    │              │  │
│  │  └─────────┘  └─────────┘  └─────────┘              │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌──────────┐                                               │
│  │自宅ルータ │ ── [L2スイッチ] ── Proxmox ノード x3        │
│  └──────────┘                                               │
└──────────────────────────────────────────────────────────────┘

外部S3 (AWS/Wasabi): s3-proxy コンテナからのみ接続
```
```
