# Transit VM (Active-Standby) ↔ VPS 間 WireGuard VPN 構築手順書

## 1. 概要

本ドキュメントは、冗長化された Transit VM (Active-Standby) と外部 VPS 間の WireGuard VPN トンネルを構築するための詳細手順を記載する。全ての手順にコマンド例を含む。

> **重要: 冗長化前提**
> 本手順は Transit VM を Active-Standby の2台構成で構築することを前提としている。
> Keepalived (VRRP) により VIP を管理し、WireGuard はフェイルオーバー時に自動切替される。

### 1.1 前提条件

| 項目 | 値 |
|------|---|
| Transit VM OS | Alpine Linux 3.20+ |
| VPS OS | Ubuntu 24.04 / Debian 12 (例) |
| Transit VM 台数 | **2台 (Active + Standby)** |
| Transit VM #1 (Active) 管理 IP | 172.26.26.200 (eth0) |
| Transit VM #2 (Standby) 管理 IP | 172.26.26.201 (eth0) |
| Transit VM #1 VM Network IP | 172.26.27.2 (eth1) |
| Transit VM #2 VM Network IP | 172.26.27.3 (eth1) |
| Transit VM VIP (VRRP) | **172.26.27.254** (eth1) |
| VPS Global IP | 各 VPS ごとに異なる |
| WireGuard サブネット | 10.255.{vps_id}.0/24 (VPS ごとに固有) |
| WireGuard ポート | Transit VM: 51820 + N / VPS: 51820 |

### 1.2 冗長化アーキテクチャ

Transit VM は Keepalived (VRRP) による Active-Standby 構成を採る。

**設計上の重要な制約:**

WireGuard は peer を公開鍵で識別する。VPS 側の Peer 設定 (`PublicKey`) に対応できるのは1つの公開鍵のみ。同じ `AllowedIPs` を持つ2つの peer を登録することは WireGuard の仕様上不可能である。

**→ Active と Standby で WireGuard の鍵ペア (秘密鍵/公開鍵) を共有する必要がある。**

| 項目 | Active (Transit VM #1) | Standby (Transit VM #2) |
|------|----------------------|------------------------|
| eth0 (管理 NW) | 172.26.26.200 | 172.26.26.201 |
| eth1 (VM NW) 物理 IP | 172.26.27.2 | 172.26.27.3 |
| eth1 VIP (VRRP) | **172.26.27.254** (所有) | (待機) |
| WireGuard 秘密鍵 | **共有** (同一鍵) | **共有** (同一鍵) |
| WireGuard 公開鍵 | **共有** (同一鍵) | **共有** (同一鍵) |
| wg0 IP (→VPS #1) | 10.255.1.2 | 10.255.1.2 (同一) |
| wg1 IP (→VPS #2) | 10.255.2.2 | 10.255.2.2 (同一) |
| WireGuard 状態 | **UP** | **DOWN** (待機) |
| Keepalived 優先度 | 100 (高) | 50 (低) |

**フェイルオーバーの動作:**

```
正常時:
  Transit VM #1 (MASTER) → WireGuard UP → VPS と通信中
  Transit VM #2 (BACKUP) → WireGuard DOWN → 待機

障害発生 (Transit VM #1 ダウン):
  Transit VM #2 が MASTER に昇格
  → Keepalived notify スクリプトが WireGuard を UP
  → 同一の鍵ペアで VPS に接続
  → VPS は最後にハンドシェイクした peer を有効と見なす
  → 自動的に切り替え完了 (VPS 側の設定変更不要)
```

### 1.3 ネットワーク構成図

```
                          Internet
                             │
                ┌────────────┼────────────┐
                │            │            │
         ┌──────┴───────┐  ┌┴─────────┐  │
         │ VPS #1       │  │ VPS #2   │  ...
         │ 203.0.113.10 │  │ 198.51.100.20
         │ wg0:         │  │ wg0:     │
         │ 10.255.1.1   │  │ 10.255.2.1
         └──────┬───────┘  └┬─────────┘
                │            │
                │ WireGuard  │ WireGuard
                │ :51820     │ :51820
                │            │
   ┌────────────┴────────────┴────────────────────┐
   │                                               │
   │  ┌─────────────────────┐  ┌────────────────┐ │
   │  │ Transit VM #1       │  │ Transit VM #2  │ │
   │  │ (Active / MASTER)   │  │ (Standby/BACKUP)│ │
   │  │                     │  │                │ │
   │  │ eth0: 172.26.26.200 │  │ eth0: .201     │ │
   │  │ eth1: 172.26.27.2   │  │ eth1: .3       │ │
   │  │ VIP:  172.26.27.254 │  │ (VIP 待機)     │ │
   │  │ wg0:  10.255.1.2 ✅ │  │ wg0: (DOWN)    │ │
   │  │ wg1:  10.255.2.2 ✅ │  │ wg1: (DOWN)    │ │
   │  │ WG: UP              │  │ WG: DOWN       │ │
   │  └─────────┬───────────┘  └───────┬────────┘ │
   │            │ VRRP (VIP)           │           │
   │            ├──────────────────────┘           │
   │            │                                  │
   │  ┌────────┴──────────────────────┐            │
   │  │ vmbr1 - VM NW (172.26.27.0/24)│            │
   │  └────────┬──────────────────────┘            │
   │           │                                   │
   │  ┌────────┴──────────────────────┐            │
   │  │ Proxmox SDN (EVPN Zone)      │            │
   │  │ Exit next-hop → VIP .254     │            │
   │  │ tenant-1: 10.1.0.0/24       │            │
   │  │ tenant-2: 10.2.0.0/24       │            │
   │  └───────────────────────────────┘            │
   └───────────────────────────────────────────────┘
```

---

## 2. Transit VM セットアップ (Active/Standby 共通)

> **このセクション (2.1 〜 2.6) は Active・Standby 両方の Transit VM で同一の手順を実施する。**
> 差分がある箇所は明示する。

### 2.1 WireGuard のインストール (Alpine Linux)

```bash
# ── 両方の Transit VM で実行 ──
apk update
apk add wireguard-tools nftables keepalived

# カーネルモジュール確認 (Alpine 3.20+ ではデフォルトで利用可能)
modprobe wireguard
lsmod | grep wireguard
```

### 2.2 カーネルパラメータの設定

```bash
# ── 両方の Transit VM で実行 ──
cat > /etc/sysctl.d/99-transit.conf << 'EOF'
# IP フォワーディング有効化
net.ipv4.ip_forward = 1

# リバースパスフィルタ緩和 (非対称ルーティング対応)
net.ipv4.conf.all.rp_filter = 2
net.ipv4.conf.default.rp_filter = 2
net.ipv4.conf.eth1.rp_filter = 2

# conntrack テーブルサイズ
net.netfilter.nf_conntrack_max = 65536

# VRRP マルチキャスト受信用
net.ipv4.conf.eth1.arp_ignore = 1
net.ipv4.conf.eth1.arp_announce = 2
EOF

sysctl -p /etc/sysctl.d/99-transit.conf
```

### 2.3 鍵ペアの生成と Active-Standby 間の共有

**Active (Transit VM #1) で鍵を生成し、Standby にコピーする。**

#### 2.3.1 Active 側で鍵ペアを生成

```bash
# ── Transit VM #1 (Active) で実行 ──

mkdir -p /etc/wireguard
chmod 700 /etc/wireguard

# VPS #1 用の鍵ペア生成
wg genkey | tee /etc/wireguard/transit-vps1-privatekey | wg pubkey > /etc/wireguard/transit-vps1-publickey
chmod 600 /etc/wireguard/transit-vps1-privatekey

# VPS #2 用の鍵ペア生成
wg genkey | tee /etc/wireguard/transit-vps2-privatekey | wg pubkey > /etc/wireguard/transit-vps2-publickey
chmod 600 /etc/wireguard/transit-vps2-privatekey

# 公開鍵の確認 (VPS 側に伝える値)
cat /etc/wireguard/transit-vps1-publickey
cat /etc/wireguard/transit-vps2-publickey
```

#### 2.3.2 Standby 側に鍵をコピー

```bash
# ── Transit VM #1 (Active) から Transit VM #2 (Standby) へコピー ──

# Standby 側のディレクトリ準備 (Standby で実行)
ssh 172.26.26.201 "mkdir -p /etc/wireguard && chmod 700 /etc/wireguard"

# Active → Standby に秘密鍵・公開鍵をコピー
scp /etc/wireguard/transit-vps*-privatekey /etc/wireguard/transit-vps*-publickey \
    172.26.26.201:/etc/wireguard/

# Standby 側でパーミッション確認
ssh 172.26.26.201 "chmod 600 /etc/wireguard/transit-vps*-privatekey && ls -la /etc/wireguard/"
```

> **セキュリティ:** 秘密鍵の転送は管理ネットワーク (172.26.26.0/24) 内の SSH で行う。
> インターネット経由での転送は厳禁。コピー後、両方の Transit VM で鍵の内容が一致することを確認する。

```bash
# 鍵の一致確認 (Active 側で実行)
diff <(cat /etc/wireguard/transit-vps1-privatekey) \
     <(ssh 172.26.26.201 cat /etc/wireguard/transit-vps1-privatekey)
# → 出力なし (一致) であること
```

### 2.4 WireGuard 設定ファイルの作成

> **Active・Standby で全く同一の設定ファイルを配置する。**
> WireGuard の Address (トンネル IP) は共有するため両方とも同じ値。

#### VPS #1 用 (wg0)

```bash
# ── 両方の Transit VM で実行 ──
cat > /etc/wireguard/wg0.conf << 'EOF'
[Interface]
PrivateKey = <TRANSIT_VM_VPS1_PRIVATE_KEY>
Address = 10.255.1.2/24
ListenPort = 51820
MTU = 1420
Table = off

PostUp = ip route add 10.255.1.0/24 dev wg0
PostUp = sysctl -w net.ipv4.conf.wg0.rp_filter=0
PostDown = ip route del 10.255.1.0/24 dev wg0 2>/dev/null || true

[Peer]
PublicKey = <VPS1_PUBLIC_KEY>
Endpoint = 203.0.113.10:51820
AllowedIPs = 10.255.1.1/32, 0.0.0.0/0
PersistentKeepalive = 25
EOF

chmod 600 /etc/wireguard/wg0.conf
```

#### VPS #2 用 (wg1)

```bash
# ── 両方の Transit VM で実行 ──
cat > /etc/wireguard/wg1.conf << 'EOF'
[Interface]
PrivateKey = <TRANSIT_VM_VPS2_PRIVATE_KEY>
Address = 10.255.2.2/24
ListenPort = 51821
MTU = 1420
Table = off

PostUp = ip route add 10.255.2.0/24 dev wg1
PostUp = sysctl -w net.ipv4.conf.wg1.rp_filter=0
PostDown = ip route del 10.255.2.0/24 dev wg1 2>/dev/null || true

[Peer]
PublicKey = <VPS2_PUBLIC_KEY>
Endpoint = 198.51.100.20:51820
AllowedIPs = 10.255.2.1/32, 0.0.0.0/0
PersistentKeepalive = 25
EOF

chmod 600 /etc/wireguard/wg1.conf
```

**設定項目の解説:**

| 項目 | 値 | 説明 |
|------|---|------|
| `PrivateKey` | Active/Standby 共有の秘密鍵 | `transit-vps1-privatekey` の内容 |
| `Address` | `10.255.1.2/24` | **Active/Standby 共通** のトンネル IP |
| `ListenPort` | `51820` (wg0), `51821` (wg1) | VPS ごとにインクリメント |
| `MTU` | `1420` | WireGuard オーバーヘッド分を差し引いた値 |
| `Table = off` | - | WireGuard の自動ルーティング追加を無効化 (手動管理) |
| `Endpoint` | VPS のグローバル IP:ポート | Transit VM → VPS 方向に接続開始 |
| `AllowedIPs` | `10.255.1.1/32, 0.0.0.0/0` | このピアから受信を許可する送信元 IP |
| `PersistentKeepalive` | `25` | 25秒ごとに keepalive を送信 (NAT 越え維持) |

> **VPS 追加時のパターン:** VPS #N を追加する場合、`wg{N-1}.conf` を作成し、
> `ListenPort` を `51820 + (N-1)` にインクリメントする。
> 作成した conf は Active・Standby 両方に配置すること。

### 2.5 nftables 設定

```bash
# ── 両方の Transit VM で実行 ──
cat > /etc/nftables.conf << 'NFTEOF'
#!/usr/sbin/nft -f
flush ruleset

table inet filter {
    chain input {
        type filter hook input priority 0; policy drop;

        # 確立済みセッション
        ct state established,related accept

        # ループバック
        iif lo accept

        # VRRP (Keepalived) - 必須
        ip protocol vrrp accept

        # WireGuard (各 VPS 用ポート)
        udp dport { 51820, 51821 } accept

        # SSH (管理NWからのみ)
        iif eth0 tcp dport 22 accept

        # ICMP
        icmp type echo-request accept
    }

    chain forward {
        type filter hook forward priority 0; policy drop;

        # 確立済みセッション
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
NFTEOF

nft -f /etc/nftables.conf
nft list ruleset

# 永続化 (Alpine)
rc-update add nftables default
```

> **VPS 追加時:** `forward` チェインに `iif wg{N} oif eth1 accept` と `iif eth1 oif wg{N} accept` を追加する。
> `input` チェインの udp dport に新しいポート番号を追加する。
> **Active・Standby 両方** の nftables.conf を更新すること。

### 2.6 ルーティング設定

```bash
# ── 両方の Transit VM で実行 ──

# テナントサブネットへのサマリルート
# Proxmox exit node (pve1 の vmbr1 IP) を next-hop にする
ip route add 10.0.0.0/8 via 172.26.27.10 dev eth1

# 確認
ip route show

# 期待される出力:
# default via 172.26.27.1 dev eth1           ← 自宅ルータ
# 10.0.0.0/8 via 172.26.27.10 dev eth1      ← テナントサブネット (exit node 経由)
# 172.26.26.0/24 dev eth0 scope link        ← 管理 NW
# 172.26.27.0/24 dev eth1 scope link        ← VM NW
```

**永続化 (Alpine Linux):**

```bash
# ── 両方の Transit VM で実行 ──
cat >> /etc/network/interfaces << 'EOF'

# テナントサブネットへのサマリルート
up ip route add 10.0.0.0/8 via 172.26.27.10 dev eth1
down ip route del 10.0.0.0/8 via 172.26.27.10 dev eth1
EOF
```

---

## 3. Keepalived と WireGuard 制御の設定

> **このセクションが冗長化の核心部分。Active/Standby で設定が異なる箇所がある。**

### 3.1 WireGuard 制御スクリプト (notify)

Keepalived の状態遷移に連動して WireGuard を起動/停止するスクリプト。

```bash
# ── 両方の Transit VM で実行 ──
cat > /usr/local/bin/transit-notify.sh << 'NOTIFYEOF'
#!/bin/sh

# Keepalived VRRP notify スクリプト
# 引数: GROUP|INSTANCE NAME master|backup|fault PRIORITY
# 例:   INSTANCE VI_1 master 100

TYPE=$1
NAME=$2
STATE=$3
PRIORITY=$4

LOG_TAG="transit-notify"

# WireGuard インターフェースのリスト (VPS 追加時にここに追記)
WG_INTERFACES="wg0 wg1"

logger -t "$LOG_TAG" "VRRP transition: $TYPE $NAME → $STATE (priority=$PRIORITY)"

case "$STATE" in
    master)
        logger -t "$LOG_TAG" "Becoming MASTER - starting WireGuard interfaces"
        for iface in $WG_INTERFACES; do
            if [ -f "/etc/wireguard/${iface}.conf" ]; then
                wg-quick up "$iface" 2>/dev/null || true
                logger -t "$LOG_TAG" "  Started $iface"
            fi
        done

        # PBR ルール追加 (アプローチ B 使用時)
        if [ -f /etc/local.d/pbr.start ]; then
            /etc/local.d/pbr.start
            logger -t "$LOG_TAG" "  Applied PBR rules"
        fi
        ;;
    backup|fault)
        logger -t "$LOG_TAG" "Becoming $STATE - stopping WireGuard interfaces"
        for iface in $WG_INTERFACES; do
            wg-quick down "$iface" 2>/dev/null || true
            logger -t "$LOG_TAG" "  Stopped $iface"
        done
        ;;
esac
NOTIFYEOF

chmod +x /usr/local/bin/transit-notify.sh
```

### 3.2 Keepalived の設定

#### Transit VM #1 (Active / 優先度: 高)

```bash
# ── Transit VM #1 (Active) で実行 ──
cat > /etc/keepalived/keepalived.conf << 'EOF'
global_defs {
    router_id TRANSIT_VM_1
    script_user root
    enable_script_security
}

vrrp_instance VI_TRANSIT {
    state MASTER
    interface eth1
    virtual_router_id 51
    priority 100
    advert_int 1
    nopreempt

    authentication {
        auth_type PASS
        auth_pass transit-ha
    }

    virtual_ipaddress {
        172.26.27.254/24
    }

    notify /usr/local/bin/transit-notify.sh
}
EOF
```

#### Transit VM #2 (Standby / 優先度: 低)

```bash
# ── Transit VM #2 (Standby) で実行 ──
cat > /etc/keepalived/keepalived.conf << 'EOF'
global_defs {
    router_id TRANSIT_VM_2
    script_user root
    enable_script_security
}

vrrp_instance VI_TRANSIT {
    state BACKUP
    interface eth1
    virtual_router_id 51
    priority 50
    advert_int 1
    nopreempt

    authentication {
        auth_type PASS
        auth_pass transit-ha
    }

    virtual_ipaddress {
        172.26.27.254/24
    }

    notify /usr/local/bin/transit-notify.sh
}
EOF
```

**設定項目の解説:**

| 項目 | Active | Standby | 説明 |
|------|--------|---------|------|
| `router_id` | TRANSIT_VM_1 | TRANSIT_VM_2 | **差分:** 各ノード固有の識別子 |
| `state` | MASTER | BACKUP | **差分:** 初期状態 |
| `priority` | 100 | 50 | **差分:** 優先度 (高い方が MASTER) |
| `virtual_router_id` | 51 | 51 | **共通:** 同じ VRRP グループ |
| `interface` | eth1 | eth1 | **共通:** VRRP を動作させる NIC |
| `nopreempt` | あり | あり | **共通:** 障害復旧時に自動で MASTER に戻さない |
| `auth_pass` | transit-ha | transit-ha | **共通:** 認証パスワード |
| `virtual_ipaddress` | 172.26.27.254 | 172.26.27.254 | **共通:** VIP |
| `notify` | transit-notify.sh | transit-notify.sh | **共通:** 状態遷移時に WG を制御 |

### 3.3 サービスの起動順序と登録

```bash
# ── 両方の Transit VM で実行 ──

# 1. ネットワーク (eth0, eth1)
rc-update add networking boot

# 2. nftables
rc-update add nftables default

# 3. Keepalived (WireGuard は Keepalived の notify で制御)
rc-update add keepalived default

# ★ WireGuard は rc-update に登録しない ★
# WireGuard の起動/停止は Keepalived の notify スクリプトが管理する。
# rc-update に登録すると Active/Standby 両方で WireGuard が起動し、
# 鍵の競合による障害が発生する。
```

> **絶対に `rc-update add wg-quick.wg0 default` 等で WireGuard を自動起動登録しないこと。**
> WireGuard は Keepalived の MASTER/BACKUP 状態に連動して起動/停止する。

### 3.4 Keepalived の起動と初期動作確認

```bash
# ── Transit VM #1 (Active) で実行 ──
service keepalived start

# VIP が付与されているか確認
ip addr show eth1 | grep 172.26.27.254
# → inet 172.26.27.254/24 scope global secondary eth1

# WireGuard が notify で起動されているか確認
wg show
# → wg0, wg1 のインターフェースが表示されること

# Keepalived ログ確認
grep -i vrrp /var/log/messages | tail -5
# → "Entering MASTER STATE" が出ていること
```

```bash
# ── Transit VM #2 (Standby) で実行 ──
service keepalived start

# VIP が付与されていないことを確認
ip addr show eth1 | grep 172.26.27.254
# → 出力なし (VIP は Active 側が所有)

# WireGuard が DOWN であること
wg show
# → 出力なし (インターフェースが存在しない)

# Keepalived ログ確認
grep -i vrrp /var/log/messages | tail -5
# → "Entering BACKUP STATE" が出ていること
```

---

## 4. VPS 側のセットアップ

> VPS 側は冗長化を意識する必要がない。VPS からは Transit VM は「1つの peer」に見える。

### 4.1 WireGuard のインストール (Ubuntu/Debian)

```bash
apt update
apt install -y wireguard wireguard-tools

modprobe wireguard
lsmod | grep wireguard
```

### 4.2 IP フォワーディングの有効化

```bash
cat > /etc/sysctl.d/99-vpn.conf << 'EOF'
net.ipv4.ip_forward = 1
EOF

sysctl -p /etc/sysctl.d/99-vpn.conf
```

### 4.3 鍵ペアの生成

```bash
mkdir -p /etc/wireguard
chmod 700 /etc/wireguard

wg genkey | tee /etc/wireguard/vps-privatekey | wg pubkey > /etc/wireguard/vps-publickey
chmod 600 /etc/wireguard/vps-privatekey

# 公開鍵の確認 (Transit VM 側に伝える値)
cat /etc/wireguard/vps-publickey
```

### 4.4 WireGuard 設定ファイルの作成

#### VPS #1 の場合

```bash
cat > /etc/wireguard/wg0.conf << 'EOF'
[Interface]
PrivateKey = <VPS1_PRIVATE_KEY>
Address = 10.255.1.1/24
ListenPort = 51820
MTU = 1420
Table = off

PostUp = ip route add 10.255.1.0/24 dev wg0
PostUp = ip route add 10.0.0.0/8 dev wg0 via 10.255.1.2
PostUp = ip route add 172.26.27.0/24 dev wg0 via 10.255.1.2

PostDown = ip route del 10.0.0.0/8 dev wg0 2>/dev/null || true
PostDown = ip route del 172.26.27.0/24 dev wg0 2>/dev/null || true
PostDown = ip route del 10.255.1.0/24 dev wg0 2>/dev/null || true

[Peer]
# Transit VM の公開鍵 (Active/Standby 共通)
PublicKey = <TRANSIT_VM_VPS1_PUBLIC_KEY>
# Transit VM は NAT 配下のため Endpoint は設定しない
# Transit VM 側から接続を開始する
AllowedIPs = 10.0.0.0/8, 172.26.27.0/24
PersistentKeepalive = 25
EOF

chmod 600 /etc/wireguard/wg0.conf
```

#### VPS #2 の場合

```bash
cat > /etc/wireguard/wg0.conf << 'EOF'
[Interface]
PrivateKey = <VPS2_PRIVATE_KEY>
Address = 10.255.2.1/24
ListenPort = 51820
MTU = 1420
Table = off

PostUp = ip route add 10.255.2.0/24 dev wg0
PostUp = ip route add 10.0.0.0/8 dev wg0 via 10.255.2.2
PostUp = ip route add 172.26.27.0/24 dev wg0 via 10.255.2.2

PostDown = ip route del 10.0.0.0/8 dev wg0 2>/dev/null || true
PostDown = ip route del 172.26.27.0/24 dev wg0 2>/dev/null || true
PostDown = ip route del 10.255.2.0/24 dev wg0 2>/dev/null || true

[Peer]
# Transit VM の公開鍵 (Active/Standby 共通)
PublicKey = <TRANSIT_VM_VPS2_PUBLIC_KEY>
AllowedIPs = 10.0.0.0/8, 172.26.27.0/24
PersistentKeepalive = 25
EOF

chmod 600 /etc/wireguard/wg0.conf
```

**設定項目の解説 (VPS 側):**

| 項目 | 説明 |
|------|------|
| `PublicKey` | Transit VM (Active/Standby 共通) の公開鍵。フェイルオーバー後も変わらない |
| `AllowedIPs` | テナント全体 (10.0.0.0/8) + VM Network (172.26.27.0/24) |
| `Table = off` | ルーティングは PostUp で手動管理 |
| `Endpoint` | **設定しない。** Transit VM が NAT 配下のため VPS 側から接続できない。Transit VM 側から接続を開始する。フェイルオーバー時に Standby が新たに接続すると、VPS は自動的に endpoint を更新する |
| `PersistentKeepalive` | VPS 側でも設定しておくと、Transit VM → VPS の接続が確立後に VPS → Transit VM の通信も維持できる |

### 4.5 WireGuard の起動と自動起動設定

```bash
wg-quick up wg0

wg show
# → peer の latest handshake が表示されれば接続成功

# systemd で自動起動
systemctl enable wg-quick@wg0
systemctl status wg-quick@wg0
```

### 4.6 ルーティング確認

```bash
ip route show

# 期待される出力 (VPS #1):
# default via <VPS_DEFAULT_GW> dev eth0     ← VPS のインターネット GW
# 10.0.0.0/8 via 10.255.1.2 dev wg0        ← テナントサブネット
# 10.255.1.0/24 dev wg0 scope link          ← WireGuard サブネット
# 172.26.27.0/24 via 10.255.1.2 dev wg0     ← VM Network
```

---

## 5. 鍵交換手順のまとめ

WireGuard は公開鍵暗号方式を使用する。秘密鍵は各ノードのローカルにのみ保存し、公開鍵のみを対向に伝える。

### 5.1 必要な鍵の一覧

| ノード | 秘密鍵 (ローカル保持) | 公開鍵 (対向に伝える) |
|-------|---------------------|---------------------|
| Transit VM Active (→VPS #1用) | `transit-vps1-privatekey` | `transit-vps1-publickey` → VPS #1 Peer.PublicKey |
| Transit VM Standby (→VPS #1用) | **同上 (Active からコピー)** | **同上** |
| Transit VM Active (→VPS #2用) | `transit-vps2-privatekey` | `transit-vps2-publickey` → VPS #2 Peer.PublicKey |
| Transit VM Standby (→VPS #2用) | **同上 (Active からコピー)** | **同上** |
| VPS #1 | `vps-privatekey` | `vps-publickey` → Transit VM wg0.conf Peer.PublicKey |
| VPS #2 | `vps-privatekey` | `vps-publickey` → Transit VM wg1.conf Peer.PublicKey |

> **重要:** Active と Standby は同一の鍵ペアを使用する。VPS から見ると Transit VM は常に「同じ peer」であり、フェイルオーバーは VPS 側に透過的。

### 5.2 鍵交換フロー

```
1. Transit VM #1 (Active) で鍵ペア生成 (VPS ごとに1つずつ)
   → 公開鍵をメモ

2. Active → Standby に秘密鍵・公開鍵をコピー (管理NW経由 SCP)

3. 各 VPS で鍵ペア生成
   → 公開鍵をメモ

4. 公開鍵を安全な方法で交換
   (SSH 経由、暗号化メッセージ等。平文メールは非推奨)

5. 各ノードの conf ファイルに相手の公開鍵を記載
   → Active・Standby 両方の conf を同一内容で更新

6. 鍵の一致を確認
   diff <(cat /etc/wireguard/transit-vps1-privatekey) \
        <(ssh 172.26.26.201 cat /etc/wireguard/transit-vps1-privatekey)
```

> **セキュリティ注意:** 秘密鍵をインターネット経由で送信しない。管理ネットワーク内の SSH のみで転送する。

---

## 6. NAT/ファイアウォール設定 (VPS 側)

### 6.1 VPS 側の nftables 設定 (NAT: アプローチ A)

初期構築では DNAT + SNAT (masquerade) のシンプルな方式を推奨。

#### VPS #1 (HTTP/HTTPS リバースプロキシ担当)

```bash
cat > /etc/nftables.conf << 'NFTEOF'
#!/usr/sbin/nft -f
flush ruleset

table inet filter {
    chain input {
        type filter hook input priority 0; policy drop;
        ct state established,related accept
        iif lo accept

        # SSH
        tcp dport 22 accept

        # HTTP/HTTPS
        tcp dport { 80, 443 } accept

        # WireGuard
        udp dport 51820 accept

        # ICMP
        icmp type echo-request accept
    }

    chain forward {
        type filter hook forward priority 0; policy drop;
        ct state established,related accept

        iif eth0 oif wg0 accept
        iif wg0 oif eth0 accept
    }

    chain output {
        type filter hook output priority 0; policy accept;
    }
}

table inet nat {
    chain prerouting {
        type nat hook prerouting priority dstnat;

        # 例: HTTP/HTTPS → テナント A の VM (10.1.0.10)
        iif eth0 tcp dport 80 dnat to 10.1.0.10:80
        iif eth0 tcp dport 443 dnat to 10.1.0.10:443
    }

    chain postrouting {
        type nat hook postrouting priority srcnat;

        # WireGuard トンネルに出るパケットを SNAT (masquerade)
        oif wg0 masquerade
    }
}
NFTEOF

nft -f /etc/nftables.conf
```

#### VPS #2 (ゲームサーバ担当)

```bash
cat > /etc/nftables.conf << 'NFTEOF'
#!/usr/sbin/nft -f
flush ruleset

table inet filter {
    chain input {
        type filter hook input priority 0; policy drop;
        ct state established,related accept
        iif lo accept

        tcp dport 22 accept
        tcp dport 25565 accept
        udp dport 25565 accept
        udp dport 51820 accept
        icmp type echo-request accept
    }

    chain forward {
        type filter hook forward priority 0; policy drop;
        ct state established,related accept

        iif eth0 oif wg0 accept
        iif wg0 oif eth0 accept
    }

    chain output {
        type filter hook output priority 0; policy accept;
    }
}

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
NFTEOF

nft -f /etc/nftables.conf
```

### 6.2 VPS 側の nftables 設定 (透過モード: アプローチ B)

クライアントの実 IP をテナント VM に見せたい場合 (SNAT なし)。
この場合、Transit VM 側で PBR (ポリシーベースルーティング) の設定が必要。

#### VPS 側 (DNAT のみ、SNAT なし)

```bash
table inet nat {
    chain prerouting {
        type nat hook prerouting priority dstnat;
        iif eth0 tcp dport 25565 dnat to 10.2.0.20:25565
        iif eth0 udp dport 25565 dnat to 10.2.0.20:25565
    }
    # postrouting で masquerade しない
}
```

#### Transit VM 側の PBR 設定 (connmark)

> **Active/Standby 両方の Transit VM に同一の設定を配置する。**
> PBR ルールは Keepalived の notify スクリプトで WireGuard 起動時に適用される。

```bash
# ── 両方の Transit VM で実行 ──
mkdir -p /etc/nftables.d

cat > /etc/nftables.d/pbr.conf << 'EOF'
table inet mangle {
    chain prerouting {
        type filter hook prerouting priority mangle;

        # wg0 (VPS #1) から入ったパケットの conntrack に mark 0x1 を付与
        iif wg0 ct mark set 0x1
        # wg1 (VPS #2) から入ったパケットの conntrack に mark 0x2 を付与
        iif wg1 ct mark set 0x2

        # connmark → fwmark にコピー (返信パケットのルーティングに使用)
        ct mark 0x1 meta mark set ct mark
        ct mark 0x2 meta mark set ct mark
    }
}
EOF
```

PBR のルーティングルールは notify スクリプトから呼ばれるスクリプトで管理する:

```bash
# ── 両方の Transit VM で実行 ──
cat > /etc/local.d/pbr.start << 'EOF'
#!/bin/sh
# PBR: ip rule + route tables
# WireGuard MASTER 遷移時に呼ばれる

# nftables mangle テーブル適用
nft -f /etc/nftables.d/pbr.conf 2>/dev/null || true

# VPS #1 経由で返す
ip rule add fwmark 0x1 table 100 priority 100 2>/dev/null || true
ip route add default via 10.255.1.1 dev wg0 table 100 2>/dev/null || true

# VPS #2 経由で返す
ip rule add fwmark 0x2 table 101 priority 101 2>/dev/null || true
ip route add default via 10.255.2.1 dev wg1 table 101 2>/dev/null || true
EOF

chmod +x /etc/local.d/pbr.start
```

---

## 7. VPS 側リバースプロキシ (nginx) の設定

NAT 方式 (アプローチ A) の代わりに、nginx リバースプロキシで HTTP/HTTPS を処理する方法。
こちらの方が柔軟性が高く推奨される。

### 7.1 nginx のインストール

```bash
apt install -y nginx certbot python3-certbot-nginx
```

### 7.2 Let's Encrypt SSL 証明書の取得

```bash
certbot certonly --nginx -d app-a.example.com -d app-b.example.com
certbot renew --dry-run
systemctl status certbot.timer
```

### 7.3 nginx 設定例 (ドメインベースルーティング)

```bash
cat > /etc/nginx/sites-available/tenant-a.conf << 'EOF'
server {
    listen 80;
    server_name app-a.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name app-a.example.com;

    ssl_certificate /etc/letsencrypt/live/app-a.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app-a.example.com/privkey.pem;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location / {
        proxy_pass http://10.1.0.10:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";

        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
EOF

ln -s /etc/nginx/sites-available/tenant-a.conf /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 7.4 TCP/UDP プロキシ (stream モジュール)

```bash
cat > /etc/nginx/stream.d/game-servers.conf << 'EOF'
# Minecraft (TCP)
server {
    listen 25565;
    proxy_pass 10.2.0.20:25565;
    proxy_connect_timeout 5s;
    proxy_timeout 600s;
}

# Minecraft (UDP)
server {
    listen 25565 udp;
    proxy_pass 10.2.0.20:25565;
    proxy_timeout 60s;
}
EOF
```

`/etc/nginx/nginx.conf` に stream ブロックを追加:

```bash
# nginx.conf の末尾に追加 (http ブロックの外)
stream {
    include /etc/nginx/stream.d/*.conf;
}
```

---

## 8. 接続テストと疎通確認

### 8.1 WireGuard 接続状態の確認

```bash
# ── Transit VM #1 (Active / MASTER) 側 ──

wg show

# 確認ポイント:
# - wg0, wg1 が表示される (Standby では表示されない)
# - "latest handshake" が表示される → 接続確立済み
# - "transfer" にデータ量が表示される → 通信が発生している
```

```bash
# ── Transit VM #2 (Standby / BACKUP) 側 ──

wg show
# → 出力なし (WireGuard が DOWN であること)

# Keepalived の状態確認
ip addr show eth1 | grep 172.26.27.254
# → 出力なし (VIP は Active 側が所有)
```

### 8.2 Ping テスト

```bash
# ── Transit VM #1 (Active) → VPS ──
ping -c 3 10.255.1.1    # VPS #1
ping -c 3 10.255.2.1    # VPS #2

# ── VPS #1 → Transit VM ──
ping -c 3 10.255.1.2    # Transit VM トンネル IP

# ── VPS #1 → テナント VM (サマリルート経由) ──
ping -c 3 10.1.0.10     # テナント A の VM

# ── VPS #1 → VM Network VIP ──
ping -c 3 172.26.27.254 # Transit VM VIP
```

### 8.3 ルーティング確認

```bash
# ── Transit VM #1 (Active) ──
ip route show
# 確認: 10.0.0.0/8 → pve exit node, 10.255.x.0/24 → wg{N}

traceroute 10.1.0.10
# 期待: Transit VM → Proxmox exit node → Tenant VM

# ── VPS ──
ip route show
# 確認: 10.0.0.0/8 → wg0 via Transit VM IP

traceroute 10.1.0.10
# 期待: VPS → (WireGuard) → Transit VM → exit node → Tenant VM
```

### 8.4 End-to-End テスト

```bash
# ── 外部クライアントから VPS 経由でテナント VM にアクセス ──
curl -v http://203.0.113.10/
curl -v https://app-a.example.com/
nc -vz 198.51.100.20 25565
```

### 8.5 MTU 確認

```bash
ip link show wg0 | grep mtu

# パスMTU ディスカバリテスト
ping -M do -s 1392 -c 3 10.255.1.1

# MTU 調整 (必要な場合)
ip link set wg0 mtu 1380
```

---

## 9. フェイルオーバーテスト

> **運用前に必ず実施する。** Active の障害時に Standby が正しく引き継ぐことを確認する。

### 9.1 手動フェイルオーバーテスト

#### Step 1: Active 側の Keepalived を停止

```bash
# ── Transit VM #1 (Active) で実行 ──
service keepalived stop

# → notify スクリプトにより WireGuard が停止される
wg show
# → 出力なし
```

#### Step 2: Standby が MASTER に昇格することを確認

```bash
# ── Transit VM #2 (元 Standby) で確認 ──

# VIP が付与されたか
ip addr show eth1 | grep 172.26.27.254
# → inet 172.26.27.254/24 scope global secondary eth1

# WireGuard が起動したか
wg show
# → wg0, wg1 が表示される

# ハンドシェイク確認
wg show wg0 latest-handshakes
# → 数秒前のタイムスタンプ
```

#### Step 3: VPS からの疎通確認

```bash
# ── VPS #1 で実行 ──
ping -c 3 10.255.1.2
# → 応答あり (フェイルオーバー先の Transit VM #2)

ping -c 3 10.1.0.10
# → 応答あり (テナント VM)

# endpoint が変わっていることを確認
wg show wg0
# → endpoint が Transit VM #2 の NAT 外部 IP に変わっている
```

#### Step 4: 元 Active を復旧

```bash
# ── Transit VM #1 で実行 ──
service keepalived start

# nopreempt のため MASTER には戻らない
# Transit VM #1 は BACKUP 状態になる
ip addr show eth1 | grep 172.26.27.254
# → 出力なし (VIP は #2 が引き続き所有)

wg show
# → 出力なし (BACKUP なので WireGuard は DOWN)
```

> **nopreempt の動作:** 障害復旧した元 Active は BACKUP として起動する。
> MASTER への復帰は手動で行う (現 MASTER の Keepalived を再起動する等)。

### 9.2 フェイルオーバー時間の計測

```bash
# ── VPS #1 で実行 ──
# Active を落とす前に ping を開始
ping 10.255.1.2

# 別ターミナルで Active の Keepalived を停止
# → ping がロスする時間を計測
# 期待値: 3〜10秒以内 (VRRP advert_int × 3 + WireGuard 起動時間)
```

### 9.3 フェイルオーバーチェックリスト

| # | 確認項目 | Active停止後の期待値 |
|---|---------|-------------------|
| 1 | Standby の VIP 取得 | `ip addr` で 172.26.27.254 が表示 |
| 2 | WireGuard 起動 | `wg show` で wg0, wg1 が表示 |
| 3 | VPS ↔ Transit VM 疎通 | `ping 10.255.1.2` 応答あり |
| 4 | VPS → テナント VM 疎通 | `ping 10.1.0.10` 応答あり |
| 5 | VPS → Transit VM VIP 疎通 | `ping 172.26.27.254` 応答あり |
| 6 | 外部 → テナント HTTP | `curl https://app-a.example.com/` 正常 |
| 7 | 元 Active が BACKUP に | VIP なし・WG なし |
| 8 | 切替時間 | 10秒以内 |

---

## 10. トラブルシューティング

### 10.1 WireGuard が接続しない

```bash
# 1. ポートが開いているか確認 (Active 側)
ss -ulnp | grep 51820

# 2. VPS 側のファイアウォールで WireGuard ポートが許可されているか
# Ubuntu (ufw)
ufw status | grep 51820
ufw allow 51820/udp

# 3. 鍵が正しいか確認
wg show wg0
# "peer" セクションの公開鍵が、対向で設定した値と一致するか

# 4. Active/Standby で鍵が一致しているか
diff <(ssh 172.26.26.200 cat /etc/wireguard/transit-vps1-privatekey) \
     <(ssh 172.26.26.201 cat /etc/wireguard/transit-vps1-privatekey)

# 5. Endpoint が正しいか確認
wg show wg0

# 6. WireGuard のデバッグログ有効化
echo module wireguard +p > /sys/kernel/debug/dynamic_debug/control
dmesg -w | grep wireguard
echo module wireguard -p > /sys/kernel/debug/dynamic_debug/control
```

### 10.2 VPS からテナント VM に到達できない

```bash
# 1. VPS のルーティング確認
ip route get 10.1.0.10
# → via 10.255.1.2 dev wg0 であること

# 2. Transit VM (Active) でパケット転送確認
tcpdump -i wg0 -n host 10.1.0.10
tcpdump -i eth1 -n host 10.1.0.10

# 3. IP フォワーディングが有効か
sysctl net.ipv4.ip_forward

# 4. nftables の forward チェインで DROP されていないか
nft list chain inet filter forward

# 5. nftables カウンタで確認
nft add rule inet filter forward counter
nft list ruleset | grep counter
```

### 10.3 フェイルオーバーが動作しない

```bash
# 1. Keepalived ログ確認
grep -i "vrrp\|keepalived" /var/log/messages | tail -20

# 2. VRRP パケットが到達しているか (Standby 側)
tcpdump -i eth1 -n vrrp
# → VRRP advertisement が見えること

# 3. notify スクリプトの実行確認
ls -la /usr/local/bin/transit-notify.sh
# → 実行権限があること (chmod +x)

# 4. 手動で notify スクリプトを起動してテスト
/usr/local/bin/transit-notify.sh INSTANCE VI_TRANSIT master 100
wg show
# → WireGuard が起動すること

/usr/local/bin/transit-notify.sh INSTANCE VI_TRANSIT backup 50
wg show
# → WireGuard が停止すること

# 5. WireGuard が自動起動登録されていないか確認
rc-status | grep wg-quick
# → wg-quick が登録されていないこと (登録されていたら削除)
rc-update del wg-quick.wg0 default 2>/dev/null
rc-update del wg-quick.wg1 default 2>/dev/null
```

### 10.4 Active/Standby で鍵が不一致

```bash
# 鍵の比較
for key in transit-vps1-privatekey transit-vps1-publickey transit-vps2-privatekey transit-vps2-publickey; do
    echo "=== $key ==="
    diff <(ssh 172.26.26.200 cat /etc/wireguard/$key) \
         <(ssh 172.26.26.201 cat /etc/wireguard/$key) \
    && echo "OK: matched" || echo "NG: MISMATCH"
done

# 不一致の場合は Active から Standby に再コピー
scp 172.26.26.200:/etc/wireguard/transit-vps*-privatekey \
    172.26.26.200:/etc/wireguard/transit-vps*-publickey \
    172.26.26.201:/etc/wireguard/
```

### 10.5 非対称ルーティング (返信パケットが VPS 経由で戻らない)

```bash
# 1. NAT 方式 (アプローチ A) の場合
nft list table inet nat
# → oif wg0 masquerade があるか

conntrack -L | grep 10.1.0.10

# 2. 透過モード (アプローチ B) の場合
nft list table inet mangle
ip rule show | grep fwmark
ip route show table 100
ip route show table 101
```

### 10.6 WireGuard パフォーマンス測定

```bash
# テナント VM で iperf3 サーバ起動
iperf3 -s

# VPS から測定
iperf3 -c 10.1.0.10 -t 10

# Transit VM (Active) 直接
iperf3 -c 10.255.1.1 -t 10
```

---

## 11. VPS 追加時の手順チェックリスト

新しい VPS (VPS #N) を追加する際の手順。

> **Transit VM は Active・Standby の2台更新が必要。** 片方だけ更新すると、
> フェイルオーバー時にVPS #N との接続が失敗する。

### 11.1 Transit VM 側 (Active・Standby 両方)

```bash
# ── Transit VM #1 (Active) で鍵を生成 ──
wg genkey | tee /etc/wireguard/transit-vpsN-privatekey | wg pubkey > /etc/wireguard/transit-vpsN-publickey
chmod 600 /etc/wireguard/transit-vpsN-privatekey

# ── Standby にコピー ──
scp /etc/wireguard/transit-vpsN-privatekey /etc/wireguard/transit-vpsN-publickey \
    172.26.26.201:/etc/wireguard/
ssh 172.26.26.201 "chmod 600 /etc/wireguard/transit-vpsN-privatekey"

# ── 両方の Transit VM で実行 ──
# WireGuard 設定ファイル作成 (ListenPort は既存の最大値 + 1)
cat > /etc/wireguard/wg${N-1}.conf << EOF
[Interface]
PrivateKey = $(cat /etc/wireguard/transit-vpsN-privatekey)
Address = 10.255.${N}.2/24
ListenPort = $((51820 + N - 1))
MTU = 1420
Table = off
PostUp = ip route add 10.255.${N}.0/24 dev wg${N-1}
PostUp = sysctl -w net.ipv4.conf.wg${N-1}.rp_filter=0
PostDown = ip route del 10.255.${N}.0/24 dev wg${N-1} 2>/dev/null || true

[Peer]
PublicKey = <VPSN_PUBLIC_KEY>
Endpoint = <VPSN_GLOBAL_IP>:51820
AllowedIPs = 10.255.${N}.1/32, 0.0.0.0/0
PersistentKeepalive = 25
EOF
chmod 600 /etc/wireguard/wg${N-1}.conf

# ── 両方の Transit VM で nftables 更新 ──
# /etc/nftables.conf を編集:
#   input: udp dport にポート追加
#   forward: iif wg${N-1} oif eth1 accept / iif eth1 oif wg${N-1} accept
nft -f /etc/nftables.conf

# ── 両方の Transit VM で notify スクリプト更新 ──
# WG_INTERFACES に wg${N-1} を追加
sed -i "s/WG_INTERFACES=\"\(.*\)\"/WG_INTERFACES=\"\1 wg${N-1}\"/" /usr/local/bin/transit-notify.sh

# ── Active 側のみ: WireGuard を起動 ──
# (Standby は notify で管理されるため手動起動不要)
wg-quick up wg${N-1}

# ── PBR 使用時: 両方の Transit VM で PBR ルール追加 ──
# /etc/local.d/pbr.start に追記:
#   ip rule add fwmark 0x${N} table $((99 + N)) priority $((99 + N))
#   ip route add default via 10.255.${N}.1 dev wg${N-1} table $((99 + N))
# Active 側では即時適用も実行
```

### 11.2 新 VPS 側

```bash
# 1. WireGuard インストール
apt install -y wireguard wireguard-tools

# 2. IP フォワーディング有効化
echo 'net.ipv4.ip_forward = 1' > /etc/sysctl.d/99-vpn.conf
sysctl -p /etc/sysctl.d/99-vpn.conf

# 3. 鍵ペア生成
wg genkey | tee /etc/wireguard/vps-privatekey | wg pubkey > /etc/wireguard/vps-publickey
chmod 600 /etc/wireguard/vps-privatekey

# 4. 公開鍵を Transit VM 管理者に伝達
cat /etc/wireguard/vps-publickey

# 5. Transit VM の公開鍵を受け取り、設定ファイル作成
cat > /etc/wireguard/wg0.conf << EOF
[Interface]
PrivateKey = $(cat /etc/wireguard/vps-privatekey)
Address = 10.255.${N}.1/24
ListenPort = 51820
MTU = 1420
Table = off
PostUp = ip route add 10.255.${N}.0/24 dev wg0
PostUp = ip route add 10.0.0.0/8 dev wg0 via 10.255.${N}.2
PostUp = ip route add 172.26.27.0/24 dev wg0 via 10.255.${N}.2
PostDown = ip route del 10.0.0.0/8 dev wg0 2>/dev/null || true
PostDown = ip route del 172.26.27.0/24 dev wg0 2>/dev/null || true
PostDown = ip route del 10.255.${N}.0/24 dev wg0 2>/dev/null || true

[Peer]
# Transit VM の公開鍵 (Active/Standby 共通)
PublicKey = <TRANSIT_VM_VPSN_PUBLIC_KEY>
AllowedIPs = 10.0.0.0/8, 172.26.27.0/24
PersistentKeepalive = 25
EOF

# 6. WireGuard 起動 & 自動起動
wg-quick up wg0
systemctl enable wg-quick@wg0

# 7. 疎通確認
ping -c 3 10.255.${N}.2
ping -c 3 10.1.0.10    # テナント VM
```

### 11.3 確認チェックリスト

| # | 確認項目 | コマンド / 確認方法 | 期待値 |
|---|---------|-------------------|--------|
| 1 | Active ↔ VPS 疎通 | `ping -c3 10.255.{N}.1` (Active から) | 応答あり |
| 2 | VPS → Transit VM 疎通 | `ping -c3 10.255.{N}.2` (VPS から) | 応答あり |
| 3 | VPS → テナント VM 疎通 | `ping -c3 10.1.0.10` (VPS から) | 応答あり |
| 4 | WireGuard ハンドシェイク | `wg show` の latest handshake | 数秒前 |
| 5 | ルーティング | `ip route get 10.1.0.10` (VPS から) | via wg0 |
| 6 | nftables (Active) | `nft list ruleset` | wg{N-1} のルールあり |
| 7 | nftables (Standby) | `nft list ruleset` (Standby で確認) | wg{N-1} のルールあり |
| 8 | 鍵一致 | `diff` で Active/Standby 比較 | 一致 |
| 9 | conf 一致 | `diff` で wg{N-1}.conf 比較 | 一致 |
| 10 | notify スクリプト | `grep wg{N-1} /usr/local/bin/transit-notify.sh` | WG_INTERFACES に含まれる |
| 11 | **フェイルオーバーテスト** | Active 停止 → VPS から疎通 | 応答あり |

> **確認項目 11 が最も重要。** VPS 追加後、必ずフェイルオーバーテストを実施して、
> Standby 側でも新 VPS との接続が成功することを確認する。

---

## 付録A: 設定ファイル一覧

### Transit VM (Active/Standby 共通)

| ファイル | 用途 | Active/Standby |
|---------|------|---------------|
| `/etc/wireguard/wg0.conf` | VPS #1 用 WireGuard 設定 | **同一** |
| `/etc/wireguard/wg1.conf` | VPS #2 用 WireGuard 設定 | **同一** |
| `/etc/wireguard/transit-vps{N}-privatekey` | VPS #N 接続用の秘密鍵 | **同一 (鍵共有)** |
| `/etc/wireguard/transit-vps{N}-publickey` | VPS #N 接続用の公開鍵 | **同一 (鍵共有)** |
| `/etc/nftables.conf` | ファイアウォール + NAT ルール | **同一** |
| `/etc/nftables.d/pbr.conf` | PBR 用 connmark | **同一** |
| `/etc/sysctl.d/99-transit.conf` | カーネルパラメータ | **同一** |
| `/etc/network/interfaces` | NIC + 静的ルート設定 | **同一** |
| `/etc/local.d/pbr.start` | PBR ip rule/route 永続化 | **同一** |
| `/usr/local/bin/transit-notify.sh` | WireGuard 制御スクリプト | **同一** |
| `/etc/keepalived/keepalived.conf` | VRRP 冗長化 | **差分あり** (下記参照) |

### Transit VM Keepalived の差分

| 項目 | Active (VM #1) | Standby (VM #2) |
|------|---------------|-----------------|
| `router_id` | TRANSIT_VM_1 | TRANSIT_VM_2 |
| `state` | MASTER | BACKUP |
| `priority` | 100 | 50 |

### VPS

| ファイル | 用途 |
|---------|------|
| `/etc/wireguard/wg0.conf` | Transit VM 用 WireGuard 設定 |
| `/etc/wireguard/vps-privatekey` | 秘密鍵 |
| `/etc/wireguard/vps-publickey` | 公開鍵 |
| `/etc/nftables.conf` | ファイアウォール + NAT ルール |
| `/etc/sysctl.d/99-vpn.conf` | カーネルパラメータ |
| `/etc/nginx/sites-available/*.conf` | リバースプロキシ設定 |
| `/etc/nginx/stream.d/*.conf` | TCP/UDP プロキシ設定 |

---

## 付録B: コマンドリファレンス

### WireGuard 操作

```bash
wg-quick up wg0                                 # 起動
wg-quick down wg0                               # 停止
wg show                                          # 全インターフェース状態
wg show wg0                                      # 特定インターフェース
wg show wg0 latest-handshakes                    # ハンドシェイク時刻
wg show wg0 transfer                             # 転送量
wg set wg0 peer <PK> endpoint <IP>:51820 \
    allowed-ips 10.255.3.1/32 persistent-keepalive 25  # ピア動的追加
wg set wg0 peer <PK> remove                      # ピア動的削除
wg syncconf wg0 <(wg-quick strip wg0)            # 設定再読み込み (無切断)
```

### Keepalived 操作

```bash
service keepalived start                         # 起動
service keepalived stop                          # 停止
service keepalived restart                       # 再起動
ip addr show eth1 | grep 172.26.27.254          # VIP 確認
grep -i vrrp /var/log/messages | tail -20        # VRRP ログ
tcpdump -i eth1 -n vrrp                          # VRRP パケット確認
kill -USR1 $(cat /var/run/keepalived.pid)        # keepalived データダンプ
```

### ルーティング操作

```bash
ip route show                                    # ルート確認
ip route show table 100                          # PBR テーブル
ip route get 10.1.0.10                           # 特定 IP への経路確認
ip route add 10.0.0.0/8 via 172.26.27.10 dev eth1  # ルート追加
ip route del 10.0.0.0/8                          # ルート削除
ip rule show                                     # PBR ルール
ip rule add fwmark 0x1 table 100 priority 100    # PBR ルール追加
ip rule del fwmark 0x1                           # PBR ルール削除
```

### nftables 操作

```bash
nft list ruleset                                 # 全ルール表示
nft list table inet filter                       # 特定テーブル
nft -f /etc/nftables.conf                        # ルール適用
nft add rule inet filter forward iif wg2 oif eth1 accept  # ルール追加
nft flush ruleset                                # 全ルール削除 (注意)
nft add rule inet filter forward iif wg0 oif eth1 counter accept  # カウンタ付き
```

### デバッグ

```bash
tcpdump -i wg0 -n                                # WireGuard トンネル
tcpdump -i eth1 -n host 10.1.0.10                # VM Network
tcpdump -i eth0 -n udp port 51820                # WireGuard UDP パケット
tcpdump -i eth1 -n vrrp                          # VRRP パケット
conntrack -L | head -20                          # conntrack テーブル
conntrack -L -s 10.255.1.1                       # 特定 src
conntrack -C                                     # エントリ数
echo module wireguard +p > /sys/kernel/debug/dynamic_debug/control  # WG デバッグ
dmesg -w | grep wireguard                        # WG ログ
echo module wireguard -p > /sys/kernel/debug/dynamic_debug/control  # デバッグ停止
```

### Active/Standby 管理

```bash
# 鍵の一致確認
for key in transit-vps1-privatekey transit-vps1-publickey transit-vps2-privatekey transit-vps2-publickey; do
    diff <(ssh 172.26.26.200 cat /etc/wireguard/$key) \
         <(ssh 172.26.26.201 cat /etc/wireguard/$key) || echo "MISMATCH: $key"
done

# conf ファイルの一致確認
for conf in wg0.conf wg1.conf; do
    diff <(ssh 172.26.26.200 cat /etc/wireguard/$conf) \
         <(ssh 172.26.26.201 cat /etc/wireguard/$conf) || echo "MISMATCH: $conf"
done

# nftables の一致確認
diff <(ssh 172.26.26.200 cat /etc/nftables.conf) \
     <(ssh 172.26.26.201 cat /etc/nftables.conf)

# notify スクリプトの一致確認
diff <(ssh 172.26.26.200 cat /usr/local/bin/transit-notify.sh) \
     <(ssh 172.26.26.201 cat /usr/local/bin/transit-notify.sh)
```
