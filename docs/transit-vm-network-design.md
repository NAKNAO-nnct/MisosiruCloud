# Transit VM ネットワーク詳細設計書

## 1. 概要と設計思想

### 1.1 解決すべき課題

| # | 課題 | 説明 |
|---|------|------|
| 1 | VPS → テナントVMへのルーティング | VPS がどのテナントサブネットに到達できるかを知る必要がある |
| 2 | テナント追加時の経路反映 | 新テナント作成時に VPS 側の経路を更新する仕組み |
| 3 | Outbound 経路の最適化 | テナントVMからの通常インターネット通信は自宅回線（高速）を使いたい |
| 4 | Inbound Reply の非対称ルーティング防止 | VPS経由で入ったパケットへの返信はVPS経由で返す必要がある |
| 5 | 冗長性 | Transit VM の単一障害点を防ぐ |

### 1.2 前提: Proxmox SDN (EVPN) の影響

**現環境は Proxmox SDN の EVPN Zone を使用している。** これにより前提が大きく変わる：

| 項目 | VLAN方式 (旧設計) | EVPN方式 (現環境) |
|------|-----------------|------------------|
| テナント隔離 | VLAN ID で分離 | VXLAN VNI で分離 |
| VM接続 | VLAN trunk + sub-if | Proxmox が作る VNet bridge に接続 |
| L3 ゲートウェイ | Transit VM が各VLAN の GW を担当 | **Proxmox SDN の anycast GW が担当** |
| テナント間ルーティング | Transit VM で制御 | Proxmox SDN (VRF) で制御 |
| Exit (外部への出口) | Transit VM が直接 | **Proxmox exit node → Transit VM** |

**結論: Transit VM はテナントサブネットの L3 ゲートウェイを担う必要がない。**
Proxmox SDN (EVPN) がテナント内のルーティングと隔離を処理し、Transit VM は WireGuard の終端と VPS 経由 Inbound の処理に専念する。

### 1.3 設計方針

- **Transit VM はシンプルな WireGuard ゲートウェイとして動作**
- Proxmox SDN EVPN の **exit node** 機能で、テナントの外向きトラフィックを Transit VM に誘導
- VPS への経路伝搬は **管理パネルによる静的ルート管理を基本** とし、BGP はオプションとして検討
- Keepalived (VRRP) で Active-Standby 冗長化

---

## 2. 全体ネットワークトポロジ

```
                          ┌──────────────────┐
                          │    Internet      │
                          └────────┬─────────┘
                                   │
              ┌────────────────────┼────────────────────┐
              │                    │                    │
     ┌────────┴─────────┐ ┌───────┴────────┐          ...
     │   VPS #1         │ │   VPS #2       │   (VPS N台)
     │   Global IP-A    │ │   Global IP-B  │
     │   wg0: 10.255.1.1│ │   wg0: 10.255.2.1│
     └────────┬─────────┘ └───────┬────────┘
              │ WireGuard Tunnel  │ WireGuard Tunnel
              │ (暗号化)           │ (暗号化)
              └─────────┬─────────┘
          ┌────────────────────────┼────────────────────────┐
          │                        │                        │
  ┌───────┴────────┐      ┌───────┴────────┐               │
  │ Transit VM #1  │      │ Transit VM #2  │               │
  │ (Active)       │      │ (Standby)      │               │
  │                │      │                │               │
  │ eth0: 管理NW   │      │ eth0: 管理NW   │               │
  │  172.26.26.x   │      │  172.26.26.x   │               │
  │ eth1: 172.26.27.2     │ eth1: 172.26.27.3              │
  │ wg0: 10.255.0.2│      │ wg0: 10.255.0.3│               │
  │ VIP: 172.26.27.254    │ (VRRP standby) │               │
  └───────┬────────┘      └───────┬────────┘               │
          │ VRRP (VIP)            │                        │
          ├───────────────────────┘                        │
          │                                                │
  ┌───────┴──────────────────────────────────────────────┐ │
  │    vmbr1 - VM Network (172.26.27.0/24)               │ │
  └──────────────────────┬───────────────────────────────┘ │
                         │                                 │
  ┌──────────────────────┴───────────────────────────────┐ │
  │  Proxmox SDN (EVPN Zone)                             │ │
  │  ┌ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ┐  │ │
  │  │  VXLAN Fabric (ノード間は UDP/4789)            │  │ │
  │  │                                                │  │ │
  │  │  ┌─────────────┐  ┌─────────────┐             │  │ │
  │  │  │ VNet        │  │ VNet        │             │  │ │
  │  │  │ tenant-1    │  │ tenant-2    │  ...        │  │ │
  │  │  │ VNI 10001   │  │ VNI 10002   │             │  │ │
  │  │  │ 10.1.0.0/24 │  │ 10.2.0.0/24 │             │  │ │
  │  │  │ GW 10.1.0.1 │  │ GW 10.2.0.1 │             │  │ │
  │  │  │  VM  VM     │  │  VM  VM     │             │  │ │
  │  │  └─────────────┘  └─────────────┘             │  │ │
  │  │                                                │  │ │
  │  │  Exit Node: pve1 (primary) / pve2 (secondary) │  │ │
  │  │  Exit next-hop → 172.26.27.254 (Transit VIP)   │  │ │
  │  └ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ┘  │ │
  └──────────────────────────────────────────────────────┘ │
                                                           │
  ┌──────────────────────────────────────────────────────┐ │
  │    vmbr0 - 管理 Network (172.26.26.0/24)             │ │
  │    pve1 / pve2 / pve3 / mgmt-app / mgmt-db          │ │
  └──────────────────────────────────────────────────────┘ │
          │                                                │
  ┌───────┴──────────┐                                     │
  │    自宅ルータ     │ ←── デフォルトGW                    │
  │    172.26.27.1    │                                     │
  └──────────────────┘                                     │
```

### パケットフロー

**テナントVM → インターネット (Outbound):**

```
Tenant VM (10.1.0.10)
  → SDN anycast GW (10.1.0.1)
    → EVPN exit node (Proxmox ノード)
      → Transit VM VIP (172.26.27.254)
        → 自宅ルータ (172.26.27.1) → インターネット  ✅ 高速回線
```

**インターネット → テナントVM (Inbound via VPS):**

```
Client → VPS #N (Global IP)
  → WireGuard → Transit VM (10.255.{N}.2)
    → [dst: 10.1.0.10] ip route → Proxmox exit node
      → EVPN fabric → Tenant VM (10.1.0.10)
```

---

## 3. IPアドレス設計

### 3.1 Transit VM のインターフェース

| インターフェース | IPアドレス | 用途 |
|---------------|----------|------|
| eth0 | 172.26.26.x/24 (固定) | 管理ネットワーク |
| eth1 | 172.26.27.2/24 (Active) / .3 (Standby) | VM Network 物理IP |
| eth1 (VIP) | 172.26.27.254/24 | VRRP 仮想IP (exit node の next-hop) |
| wg0 | 10.255.1.2/24 (Active) / .3 (Standby) | WireGuard VPN tunnel → VPS #1 |
| wg1 | 10.255.2.2/24 (Active) / .3 (Standby) | WireGuard VPN tunnel → VPS #2 |
| wg{N} | 10.255.{N+1}.2/24 | WireGuard VPN tunnel → VPS #N |

> **旧設計との違い:** VLAN サブインターフェース (eth1.101, eth1.102...) は不要。
> テナントサブネットへのルーティングは Proxmox SDN (EVPN) が処理する。

### 3.2 VPS 側インターフェース

各 VPS は固有の WireGuard サブネットを持つ。VPS ID に応じて `10.255.{vps_id}.0/24` を割り当てる。

| インターフェース | IPアドレス | 用途 |
|---------------|----------|------|
| eth0 | 各 VPS のグローバル IP | インターネット接続 |
| wg0 | 10.255.{vps_id}.1/24 | WireGuard VPN tunnel |

**例:**

| VPS | Global IP | WireGuard IP | 用途 |
|-----|-----------|-------------|------|
| VPS #1 | 203.0.113.10 | 10.255.1.1 | HTTP/HTTPS リバースプロキシ |
| VPS #2 | 198.51.100.20 | 10.255.2.1 | ゲームサーバ・TCP/UDP転送 |

### 3.3 テナントサブネットのアドレス体系

```
VNet 名 = tenant-{tenant_id}
VNI = 10000 + tenant_id
サブネット = 10.{tenant_id}.0.0/24
ゲートウェイ = 10.{tenant_id}.0.1 (Proxmox SDN anycast GW)
使用可能範囲 = 10.{tenant_id}.0.10 ~ 10.{tenant_id}.0.254

例:
  Tenant 1: VNet tenant-1 / VNI 10001 / 10.1.0.0/24 / GW 10.1.0.1
  Tenant 2: VNet tenant-2 / VNI 10002 / 10.2.0.0/24 / GW 10.2.0.1
  Tenant 15: VNet tenant-15 / VNI 10015 / 10.15.0.0/24 / GW 10.15.0.1
```

---

## 4. Proxmox SDN EVPN 設定

### 4.1 EVPN Zone 設定

Proxmox Web UI または API で以下を設定：

| パラメータ | 値 | 説明 |
|----------|---|------|
| Zone ID | evpn-zone | EVPN ゾーン名 |
| Type | EVPN | ゾーンタイプ |
| Controller | (Proxmox内蔵FRR) | 自動管理 |
| VRF VXLAN | 4000 | L3 VRF 用の VXLAN VNI |
| Exit Nodes | pve1, pve2 | 外部トラフィックの出口ノード |
| Primary Exit Node | pve1 | 優先 exit node |
| Advertise Subnets | yes | サブネット情報を BGP EVPN で広告 |
| Disable ARP/ND Suppression | no | ARP suppress 有効 (推奨) |

### 4.2 テナント VNet 作成例

**VNet:**

| パラメータ | 値 |
|----------|---|
| VNet ID | tenant-1 |
| Zone | evpn-zone |
| Tag (VNI) | 10001 |

**Subnet:**

| パラメータ | 値 |
|----------|---|
| Subnet | 10.1.0.0/24 |
| Gateway | 10.1.0.1 |
| SNAT | no (Transit VM 経由で出る) |

> **注記:** EVPN Zone では Proxmox SDN の DHCP (dnsmasq) 機能は使用しない。
> テナント VM の IP は Cloud-init で静的に設定する。

### 4.3 Exit Node の動作

Proxmox EVPN で exit node を設定すると：

1. exit node の FRR が EVPN VRF にデフォルトルート (Type-5) を注入
2. テナント VM が外部宛 (0.0.0.0/0) に通信すると、EVPN fabric が exit node へ転送
3. exit node はパケットを VRF から取り出し、物理ネットワーク (vmbr1) に転送
4. 次ホップとして Transit VM VIP (172.26.27.254) に送る

**exit node での構成ポイント:**

- Proxmox SDN が FRR 設定を自動生成する (exitnodes / exitnodes-primary を指定)
- exit node の vmbr1 上にデフォルト GW が自宅ルータ (172.26.27.1) に設定されていれば、テナントのインターネット向けトラフィックは自動的にそちらに流れる
- Transit VM VIP 経由にしたい場合は exit node 上で VRF のデフォルトルートを明示的に Transit VM VIP に向ける

> **補足:** Proxmox SDN の exitnodes 設定 + exitnodes-primary を指定すると、
> SDN コントローラが FRR 設定を自動生成する。
> 外向きルートを Transit VM VIP (172.26.27.254) にするかどうかは運用ポリシー次第。

---

## 5. VPS ← → Transit VM 間の経路管理

### 5.1 方式の比較

| 方式 | 複雑度 | 適用規模 | メリット | デメリット |
|------|--------|---------|---------|----------|
| **静的ルート (推奨)** | 低 | ~50テナント | シンプル、デバッグ容易 | テナント追加時にAPI呼び出し必要 |
| **サマリルート** | 最低 | 制限なし | 設定変更不要 | テナント外のIPにもルーティングされうる |
| **BGP** | 高 | 50+テナント / 複数VPS | テナント追加時に自動広告 | FRR運用・デバッグの知識が必要 |

### 5.2 推奨: サマリルート

テナントサブネットは全て 10.0.0.0/8 内に収まるため、VPS 側では **サマリルート 1行で済む。**

**各 VPS 側:**

```bash
# 全テナントサブネットへの経路（サマリ）
# 各 VPS は自身の WireGuard Peer (Transit VM) 経由で転送
# VPS #1 の場合:
ip route add 10.0.0.0/8 via 10.255.1.2 dev wg0

# VPS #2 の場合:
ip route add 10.0.0.0/8 via 10.255.2.2 dev wg0

# または Transit VM の WireGuard AllowedIPs でカバー
# AllowedIPs = 10.0.0.0/8, 172.26.27.0/24
```

**Transit VM 側:**

```bash
# テナントサブネットへの経路
# Proxmox exit node (pve1 の vmbr1 IP) が next-hop
# exit node が EVPN VRF からのパケットを vmbr1 に出すので、
# Transit VM は同一 L2 セグメント上でルーティング可能
ip route add 10.0.0.0/8 via <pve1-vmbr1-ip> dev eth1
```

> **注意点:** サマリルート (10.0.0.0/8) は WireGuard VPN のアドレス (10.255.{vps_id}.0/24) も含む。
> WireGuard トンネルは直結 (dev wg{N}) なので、longer match で wg{N} が優先される。問題なし。

### 5.3 オプション: BGP (大規模向け)

> Gemini の plan-7.md で提案されていた方式。テナント数が多い場合や、
> テナントサブネットの個別制御が必要な場合に導入を検討。
> **おうちクラウドの規模（~50テナント）ではサマリルートで十分な可能性が高い。**

BGP を使う場合の設計は以下の通り:

| ノード | ASN | 役割 |
|-------|-----|------|
| 各 VPS | 65000 | Transit VM からテナント経路を学習 |
| Transit VM | 65001 | テナント経路を全 VPS に広告 |

Transit VM の FRR 設定（BGP部分のみ、複数VPS対応）:

```
router bgp 65001
 bgp router-id 10.255.1.2
 no bgp ebgp-requires-policy
 ! VPS #1
 neighbor 10.255.1.1 remote-as 65000
 neighbor 10.255.1.1 update-source wg0
 ! VPS #2
 neighbor 10.255.2.1 remote-as 65000
 neighbor 10.255.2.1 update-source wg1
 address-family ipv4 unicast
  redistribute connected route-map TENANT-ONLY
  network 172.26.27.0/24
 exit-address-family
!
ip prefix-list TENANT-NETS seq 10 permit 10.0.0.0/8 ge 24 le 24
ip prefix-list TENANT-NETS seq 20 permit 172.26.27.0/24
!
route-map TENANT-ONLY permit 10
 match ip address prefix-list TENANT-NETS
route-map TENANT-ONLY deny 20
```

**BGP を使うのは以下の場合のみ検討:**
- テナント数が非常に多く、個別サブネットの制御が必要
- テナントごとに異なるルーティングポリシーを適用したい
- 冗長化で Active/Standby 切り替え時に経路を自動収束させたい

**BGP が不要なケース（大半のおうちクラウド）:**
- テナント数が少ない (~50)
- サマリルート (10.0.0.0/8) で十分
- VPS は1台

---

## 6. WireGuard 設定

各 VPS は固有の WireGuard サブネット `10.255.{vps_id}.0/24` を持ち、Transit VM との間に個別のトンネルを張る。
Transit VM 側では複数の WireGuard インターフェース (`wg0`, `wg1`, ...) を作成し、各 VPS とのトンネルを分離する。

### 6.1 Transit VM 側 — VPS #1 用 (/etc/wireguard/wg0.conf)

```ini
[Interface]
PrivateKey = <TRANSIT_VM_PRIVATE_KEY_1>
Address = 10.255.1.2/24
ListenPort = 51820
MTU = 1420
Table = off
PostUp = ip route add 10.255.1.0/24 dev wg0
PostDown = ip route del 10.255.1.0/24 dev wg0

[Peer]
PublicKey = <VPS1_PUBLIC_KEY>
Endpoint = <VPS1_GLOBAL_IP>:51820
AllowedIPs = 10.255.1.1/32, 0.0.0.0/0
PersistentKeepalive = 25
```

### 6.1.1 Transit VM 側 — VPS #2 用 (/etc/wireguard/wg1.conf)

```ini
[Interface]
PrivateKey = <TRANSIT_VM_PRIVATE_KEY_2>
Address = 10.255.2.2/24
ListenPort = 51821
MTU = 1420
Table = off
PostUp = ip route add 10.255.2.0/24 dev wg1
PostDown = ip route del 10.255.2.0/24 dev wg1

[Peer]
PublicKey = <VPS2_PUBLIC_KEY>
Endpoint = <VPS2_GLOBAL_IP>:51820
AllowedIPs = 10.255.2.1/32, 0.0.0.0/0
PersistentKeepalive = 25
```

> **拡張:** VPS を追加する場合は `wg{N}` インターフェースと対応する conf ファイルを追加する。
> ListenPort は VPS ごとにインクリメント (51820, 51821, 51822, ...).
> 管理パネルから VPS 追加時に自動生成する。

### 6.2 VPS #1 側 (/etc/wireguard/wg0.conf)

```ini
[Interface]
PrivateKey = <VPS1_PRIVATE_KEY>
Address = 10.255.1.1/24
ListenPort = 51820
MTU = 1420
Table = off
PostUp = ip route add 10.255.1.0/24 dev wg0
# サマリルート: 全テナントサブネットを Transit VM 経由に
PostUp = ip route add 10.0.0.0/8 dev wg0 via 10.255.1.2
PostUp = ip route add 172.26.27.0/24 dev wg0 via 10.255.1.2
PostDown = ip route del 10.0.0.0/8 dev wg0
PostDown = ip route del 172.26.27.0/24 dev wg0

[Peer]
PublicKey = <TRANSIT_VM_1_PUBLIC_KEY>
AllowedIPs = 10.0.0.0/8, 172.26.27.0/24
PersistentKeepalive = 25
```

### 6.2.1 VPS #2 側 (/etc/wireguard/wg0.conf)

```ini
[Interface]
PrivateKey = <VPS2_PRIVATE_KEY>
Address = 10.255.2.1/24
ListenPort = 51820
MTU = 1420
Table = off
PostUp = ip route add 10.255.2.0/24 dev wg0
PostUp = ip route add 10.0.0.0/8 dev wg0 via 10.255.2.2
PostUp = ip route add 172.26.27.0/24 dev wg0 via 10.255.2.2
PostDown = ip route del 10.0.0.0/8 dev wg0
PostDown = ip route del 172.26.27.0/24 dev wg0

[Peer]
PublicKey = <TRANSIT_VM_1_PUBLIC_KEY>
AllowedIPs = 10.0.0.0/8, 172.26.27.0/24
PersistentKeepalive = 25
```

> **ポイント:** 各 VPS は同じ `AllowedIPs = 10.0.0.0/8` で全テナントサブネットをカバー。
> テナント追加時に各 VPS の WireGuard 設定の変更は不要。

---

## 7. PBR (ポリシーベースルーティング)

### 7.1 なぜ PBR が必要か

Transit VM は2つの出口を持つ:
- **自宅ルータ (172.26.27.1):** 通常のインターネット通信（高速回線）
- **WireGuard (wg0, wg1, ...):** 各 VPS 経由の通信

VPS 経由で入ったパケットへの返信は、必ず同じ VPS 経由で返す必要がある（非対称ルーティング防止）。

### 7.2 2つのアプローチ

#### アプローチA: VPS で SNAT する（シンプル・推奨）

VPS が DNAT + SNAT (masquerade) を行い、テナント VM から見るとソースIPは VPS の WireGuard IP。

```
[Client] → [VPS #1: DNAT+SNAT, src→10.255.1.1] → WireGuard → [Transit VM] → [Tenant VM]

Reply: [Tenant VM] → dst:10.255.1.1 → [Transit VM] → wg0 → [VPS #1: reverse NAT] → [Client]
```

- Transit VM での PBR **不要**。宛先 10.255.{vps_id}.0/24 は対応する wg{N} で直結なので自動的に正しい WireGuard インターフェースに流れる
- HTTP/HTTPS はリバースプロキシの X-Forwarded-For でクライアント実IP取得可能
- TCP/UDP ゲームサーバではクライアント実IPが見えない

**VPS 側 nftables (各 VPS で個別設定):**

各 VPS はそれぞれ担当するドメイン・ポートに応じた DNAT/SNAT ルールを持つ。

```
# VPS #1 (HTTP/HTTPS 担当)
table inet nat {
    chain prerouting {
        type nat hook prerouting priority dstnat;
        # HTTP → テナントA
        iif eth0 tcp dport 80 dnat to 10.1.0.10:80
        iif eth0 tcp dport 443 dnat to 10.1.0.10:443
    }
    chain postrouting {
        type nat hook postrouting priority srcnat;
        oif wg0 masquerade
    }
}
```

```
# VPS #2 (ゲームサーバ担当)
table inet nat {
    chain prerouting {
        type nat hook prerouting priority dstnat;
        iif eth0 tcp dport 25565 dnat to 10.2.0.20:25565
        iif eth0 udp dport 25565 dnat to 10.2.0.20:25565
    }
    chain postrouting {
        type nat hook postrouting priority srcnat;
        oif wg0 masquerade
    }
}
```
```

#### アプローチB: 透過モード + connmark PBR（クライアント実IP保持）

VPS は DNAT のみ（SNAT しない）。Transit VM で connmark を使い、入ってきた WireGuard インターフェースごとにマークし、返信を同じ VPS 経由に誘導。

```
[Client: 203.0.113.50] → [VPS #2: DNAT only] → WireGuard → [Transit VM: mark] → [Tenant VM]
                                                                               src=203.0.113.50 が見える

Reply: [Tenant VM] → dst:203.0.113.50 → [Transit VM: connmark→wg1] → [VPS #2: reverse DNAT] → [Client]
```

**Transit VM の nftables + ip rule (複数VPS対応):**

```bash
# nftables: 各 WireGuard インターフェースごとに異なる mark
table inet mangle {
    chain prerouting {
        type filter hook prerouting priority mangle;
        iif wg0 ct mark set 0x1
        iif wg1 ct mark set 0x2
        ct mark 0x1 meta mark set ct mark
        ct mark 0x2 meta mark set ct mark
    }
}

# ip rule: mark ごとに対応する VPS 経由のテーブルへルーティング
ip rule add fwmark 0x1 table 100 priority 100
ip rule add fwmark 0x2 table 101 priority 101
ip route add default via 10.255.1.1 dev wg0 table 100
ip route add default via 10.255.2.1 dev wg1 table 101
```

> **ポイント:** VPS が増えた場合は connmark と ip rule/table を追加する。
> 管理パネルから VPS 追加時に Transit VM の nftables + ip rule も自動更新する。

### 7.3 推奨

| 用途 | 推奨アプローチ | 理由 |
|------|-------------|------|
| HTTP/HTTPS | A (NAT) | X-Forwarded-For で十分。シンプル |
| ゲームサーバ (TCP/UDP) | B (透過+PBR) | クライアント実IP 必須 |
| 初期構築 | **A (NAT) で開始** | まず動作確認を優先 |

---

## 8. Keepalived (VRRP) 冗長化

### 8.1 構成

```
Transit VM #1 (Active):  172.26.27.2 + VIP 172.26.27.254
Transit VM #2 (Standby): 172.26.27.3
```

### 8.2 Keepalived 設定 (Transit VM #1)

```
global_defs {
    router_id TRANSIT_1
    script_user root
    enable_script_security
}

vrrp_script chk_wireguard {
    script "/usr/local/bin/check-wg.sh"
    interval 5
    weight -20
    fall 3
    rise 2
}

vrrp_instance VI_TRANSIT {
    state MASTER
    interface eth1
    virtual_router_id 51
    priority 100
    advert_int 1

    authentication {
        auth_type PASS
        auth_pass <VRRP_SECRET>
    }

    virtual_ipaddress {
        172.26.27.254/24
    }

    track_script {
        chk_wireguard
    }

    notify_master "/usr/local/bin/transit-notify.sh master"
    notify_backup "/usr/local/bin/transit-notify.sh backup"
    notify_fault  "/usr/local/bin/transit-notify.sh fault"
}
```

### 8.3 フェイルオーバー通知スクリプト

```bash
#!/bin/bash
# /usr/local/bin/transit-notify.sh
STATE=$1
case "$STATE" in
    master)
        logger "VRRP: Transitioning to MASTER"
        # 全 VPS への WireGuard 接続を確認
        for iface in wg0 wg1; do
            wg set "$iface" peer <VPS_PUBLIC_KEY> persistent-keepalive 25 2>/dev/null
        done
        ;;
    backup|fault)
        logger "VRRP: Transitioning to $STATE"
        ;;
esac
```

### 8.4 ヘルスチェック

```bash
#!/bin/bash
# /usr/local/bin/check-wg.sh
# 全 VPS への WireGuard 接続を確認 (いずれか1台でも応答があればOK)
for ip in 10.255.1.1 10.255.2.1; do
    ping -c 1 -W 2 "$ip" > /dev/null 2>&1 && exit 0
done
exit 1
```

> **注意:** Standby 側も全 VPS との WireGuard 接続を維持する。
> 各 VPS の AllowedIPs は Active の IP (10.255.{vps_id}.2) のみ。
> フェイルオーバー時は各 VPS の WireGuard peer 設定を一括切り替える必要がある。
> これは管理パネル or 各 VPS 上のスクリプトで自動化する。

---

## 9. Transit VM 仕様と OS 設定

### 9.1 VM スペック

| 項目 | Active | Standby |
|------|--------|---------|
| OS | Alpine Linux 3.20+ | 同左 |
| vCPU | 1 | 1 |
| RAM | 512MB | 512MB |
| ディスク | 5GB (Ceph RBD) | 5GB (Ceph RBD) |
| NIC 1 (eth0) | 管理NW - vmbr0 (172.26.26.0/24) | 同左 |
| NIC 2 (eth1) | VM NW - vmbr1 (172.26.27.0/24) | 同左 |
| ソフトウェア | WireGuard, Keepalived, nftables | 同左 |
| オプション | FRR (BGP使用時のみ) | 同左 |

> **旧設計との違い:**
> - NIC は 2つだけ (VLAN trunk 不要)
> - FRR は BGP を使わない場合は不要 (大幅に簡素化)
> - テナント VNet への NIC 追加も不要

### 9.2 カーネルパラメータ (/etc/sysctl.d/99-transit.conf)

```
# IP フォワーディング有効化
net.ipv4.ip_forward = 1

# リバースパスフィルタ緩和 (非対称ルーティング対応)
net.ipv4.conf.all.rp_filter = 2
net.ipv4.conf.default.rp_filter = 2
net.ipv4.conf.wg0.rp_filter = 0
net.ipv4.conf.wg1.rp_filter = 0
net.ipv4.conf.eth1.rp_filter = 2

# conntrack テーブルサイズ
net.netfilter.nf_conntrack_max = 65536
```

### 9.3 ネットワーク設定 (Alpine: /etc/network/interfaces)

```
auto lo
iface lo inet loopback

auto eth0
iface eth0 inet static
    address 172.26.26.x
    netmask 255.255.255.0
    # 管理NWにはGWなし (グローバルアクセス不可)

auto eth1
iface eth1 inet static
    address 172.26.27.2
    netmask 255.255.255.0
    gateway 172.26.27.1
    # デフォルトGWは自宅ルータ (インターネット向け通常トラフィック)
```

> **旧設計との違い:** VLAN サブインターフェース (eth1.101, eth1.102...) は一切不要。

### 9.4 ルーティングテーブル

```bash
# デフォルト: 自宅ルータ経由 (インターネット)
default via 172.26.27.1 dev eth1

# WireGuard VPN (VPS #1)
10.255.1.0/24 dev wg0

# WireGuard VPN (VPS #2)
10.255.2.0/24 dev wg1

# テナントサブネット → Proxmox exit node 経由
# exit node (pve1) の vmbr1 IP を next hop にする
10.0.0.0/8 via <pve1-vmbr1-ip> dev eth1

# 管理ネットワーク
172.26.26.0/24 dev eth0
```

### 9.5 nftables 基本ルール (/etc/nftables.conf)

```
#!/usr/sbin/nft -f
flush ruleset

table inet filter {
    chain input {
        type filter hook input priority 0; policy drop;
        ct state established,related accept
        iif lo accept

        # VRRP
        ip protocol vrrp accept
        # WireGuard
        udp dport { 51820, 51821 } accept
        # SSH (管理NWからのみ)
        iif eth0 tcp dport 22 accept
        # ICMP
        icmp type echo-request accept
    }

    chain forward {
        type filter hook forward priority 0; policy drop;
        ct state established,related accept

        # WireGuard → VM Network (VPS からの inbound)
        iif wg0 oif eth1 accept
        iif wg1 oif eth1 accept

        # VM Network → WireGuard (返信)
        iif eth1 oif wg0 accept
        iif eth1 oif wg1 accept

        # VM Network → VM Network (通過トラフィック)
        iif eth1 oif eth1 accept
    }

    chain output {
        type filter hook output priority 0; policy accept;
    }
}

# PBR 用 connmark (アプローチB 使用時のみ、VPS ごとに異なる mark)
# table inet mangle {
#     chain prerouting {
#         type filter hook prerouting priority mangle;
#         iif wg0 ct mark set 0x1
#         iif wg1 ct mark set 0x2
#         ct mark 0x1 meta mark set ct mark
#         ct mark 0x2 meta mark set ct mark
#     }
# }
```

---

## 10. テナント作成時の設定フロー

EVPN を使用するため、テナント作成時に Transit VM 側の設定変更は **基本的に不要。**

```
[管理パネル (Laravel)]
    │
    │ 1. テナントDB登録 (tenant_id, vnet_name, network_cidr)
    │
    │ 2. Proxmox SDN API 呼び出し
    │    ├── VNet 作成 (tenant-{id}, zone: evpn-zone, tag: 1000{id})
    │    ├── Subnet 作成 (10.{id}.0.0/24, gateway: 10.{id}.0.1)
    │    └── SDN 適用 (PUT /cluster/sdn)
    │
    │ 3. Proxmox が自動的に実行:
    │    ├── 各ノードに VNet ブリッジ作成
    │    ├── FRR (EVPN) にサブネット情報を注入
    │    ├── Exit node にルーティング設定を追加
    │    └── VXLAN トンネルの確立
    │
    │ ※ Transit VM への設定変更は不要！
    │    (サマリルート 10.0.0.0/8 で全テナントカバー済み)
    │
    │ ※ VPS 側への設定変更も不要！
    │    (各 VPS の AllowedIPs 10.0.0.0/8 でカバー済み)
    │
    ▼
[完了: テナント VM デプロイ可能]
```

> **旧設計との大きな違い:**
> - Transit VM への VLAN サブインターフェース追加 → 不要
> - FRR への BGP ネットワーク追加 → 不要
> - VPS 側の経路更新 → 不要（サマリルートでカバー）
> - **テナント追加がほぼ Proxmox SDN API 操作だけで完結する**

---

## 11. VPS 側リバースプロキシ設計

各 VPS がそれぞれ担当するドメイン・ポートに応じて nginx 設定を持つ。
管理パネルの `vps_gateways` テーブルでどの VPS がどのドメインを担当するかを管理する。

### 11.1 nginx でのドメインベースルーティング (各 VPS ごと)

**VM 直接ルーティング (汎用 VM / DBaaS):**

```nginx
# /etc/nginx/conf.d/tenant-routing.conf

server {
    listen 443 ssl;
    server_name app-a.example.com;

    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    location / {
        proxy_pass http://10.1.0.10:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

**CaaS (Nomad) コンテナルーティング → Traefik 経由:**

Nomad 上のコンテナは動的ポートが割り当てられるため、VPS nginx は Nomad Worker 上の **Traefik (:80)** にプロキシする。Traefik が Consul Catalog を参照して正しいコンテナにルーティングする。

```nginx
# CaaS コンテナ用: Nomad Worker の Traefik へ転送
upstream nomad_workers {
    # Nomad Worker VM の IP (vmbr1 / 172.26.27.x)
    server 172.26.27.20:80;
    server 172.26.27.21:80;
    server 172.26.27.22:80;
}

server {
    listen 443 ssl;
    server_name *.containers.example.com;

    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    location / {
        proxy_pass http://nomad_workers;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

> **ポイント:** VPS nginx は「どのコンテナにどのポートで転送するか」を知る必要がない。
> Host ヘッダを保持して Traefik に転送すれば、Traefik が Consul Catalog のタグに基づいて
> 正しいコンテナ (動的ポート) にルーティングする。

### 11.2 nginx stream でのTCP/UDPプロキシ

```nginx
stream {
    server {
        listen 25565;
        proxy_pass 10.2.0.20:25565;
    }
    server {
        listen 27015 udp;
        proxy_pass 10.3.0.30:27015;
    }
}
```

### 11.3 VPS のプロキシ設定管理

管理パネルから各 VPS の nginx 設定を動的に更新する仕組みが必要：
- 各 VPS 上に軽量 API (Go) を配置し、管理パネルから WireGuard 経由で設定を push
- VPS 追加時に WireGuard トンネル設定 + nginx 初期設定をプロビジョニング
- 管理パネルの VPS 管理画面から VPS の追加・削除・設定変更が可能

---

## 12. 動作確認チェックリスト

### Phase 1: WireGuard 基本疎通

- [ ] Transit VM ↔ VPS #1 間で WireGuard 疎通確認 (ping 10.255.1.1 ↔ 10.255.1.2)
- [ ] Transit VM ↔ VPS #2 間で WireGuard 疎通確認 (ping 10.255.2.1 ↔ 10.255.2.2)
- [ ] Transit VM のデフォルトGW が自宅ルータであることを確認
- [ ] Transit VM から各 VPS 側のネットワーク到達確認

### Phase 2: Proxmox SDN EVPN

- [ ] EVPN Zone の作成・適用
- [ ] テスト VNet + Subnet の作成
- [ ] テスト VM をテスト VNet に接続し、GW (anycast) への疎通確認
- [ ] テスト VM → インターネット疎通確認 (exit node 経由)
- [ ] 異なる物理ノード上の同一 VNet VM 間の L2 疎通確認 (VXLAN 動作確認)

### Phase 3: VPS → テナント VM End-to-End

- [ ] 各 VPS からテナント VM へ直接 ping 確認 (10.0.0.0/8 サマリルート)
- [ ] 各 VPS の DNAT/SNAT 設定と外部からのアクセス確認
- [ ] テナント VM からのインターネット通信が自宅ルータ経由になることを確認
- [ ] VPS 追加・削除時の Transit VM WireGuard 設定反映確認

### Phase 4: 冗長化

- [ ] Keepalived VRRP の動作確認 (VIP 172.26.27.254 の移動)
- [ ] Active 停止時に Standby が VIP を引き継ぐことを確認
- [ ] フェイルバック動作の確認

---

## 付録A: BGP 設計 (オプション)

> このセクションは BGP を導入する判断をした場合のリファレンスです。
> 初期構築ではサマリルート方式を推奨します。

BGP を使う場合:

| ノード | ASN |
|-------|-----|
| VPS | 65000 |
| Transit VM | 65001 |

Transit VM に FRR をインストールし、WireGuard 上で eBGP ピアリングを張る。
Transit VM は Proxmox SDN (EVPN) が保持するテナントサブネット情報を
FRR の redistribute または network コマンドで VPS に広告する。

**BGP が有用になるケース:**
- テナント数が50を超え、個別制御が必要
- Transit VM の冗長化で Active/Standby 切り替え時の経路収束を自動化したい
- 複数 VPS 間で経路制御を最適化したい場合

**BGP が不要なケース（大半のおうちクラウド）:**
- テナント数が少ない (~50)
- サマリルート (10.0.0.0/8) で十分
- VPS 台数が少なく、各 VPS のサマリルートで対応可能
