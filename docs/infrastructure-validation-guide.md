# インフラ手動検証手順書

開発着手前に、設計書の構成が実際に動作するかを手動で検証するための手順書。
各フェーズの検証が通れば、その部分の管理パネル開発に着手できる。

---

## 前提: 現在の環境状態

| 項目 | 状態 |
|------|------|
| Proxmox VE 9 × 3 ノード (pve1, pve2, pve3) | ✅ 構成済み |
| Ceph 3 ノードクラスタ | ✅ 構成済み |
| vmbr0 (管理NW: 172.26.26.0/24) | ✅ 構成済み |
| vmbr1 (VM NW: 172.26.27.0/24) | ✅ 構成済み |
| Proxmox SDN EVPN Zone | ✅ 構成済み |
| 外部 VPS (OS インストール済み) | ✅ 構成済み |
| WireGuard VPN (Transit VM ↔ VPS) | ❌ 未構成 (本手順で一から構築) |
| Transit VM | ❌ 未構成 (本手順で一から構築) |

> **方針:** 各フェーズの末尾に「検証結果チェック」を設け、全項目をパスしたら次のフェーズに進む。

---

## Phase 0: ネットワーク基盤検証

### 目的

Transit VM ↔ VPS 間の WireGuard VPN、Proxmox SDN EVPN、サマリルートによるテナントサブネットへの到達性が正しく機能することを確認する。

> **重要:** 既存の WireGuard / Transit VM (proxy VM) 設定は使用しない。本フェーズで一から構築する。
> 既存の proxy VM がある場合は停止しておくこと。IP アドレスやポートが競合しないように注意する。

---

### 0-0. VPS 側の初期準備

外部 VPS に OS はインストール済みの前提。WireGuard やルーティング設定は一切入っていない状態から開始する。

```bash
# === VPS #1 に SSH ログイン ===
ssh root@<VPS1_GLOBAL_IP>

# OS 確認
cat /etc/os-release
# → Ubuntu 24.04 / Debian 12 等を想定

# パッケージ更新
apt update && apt upgrade -y

# 既存の WireGuard 設定があれば削除 (クリーンな状態にする)
systemctl stop wg-quick@wg0 2>/dev/null || true
systemctl disable wg-quick@wg0 2>/dev/null || true
rm -rf /etc/wireguard/*

# ファイアウォールで WireGuard ポートを開放
# ufw の場合:
ufw allow 51820/udp
ufw status

# iptables の場合:
# iptables -A INPUT -p udp --dport 51820 -j ACCEPT

# グローバル IP の確認
ip -4 addr show eth0 | grep inet
curl -4 ifconfig.me
# → この IP を Transit VM 側の Endpoint に使用する
```

---

### 0-1. Transit VM の作成

Proxmox Web UI または CLI で Transit VM を作成する。

```bash
# pve1 で実行
# Alpine Linux ISO を事前にダウンロード
wget -P /var/lib/vz/template/iso/ \
  https://dl-cdn.alpinelinux.org/alpine/v3.21/releases/x86_64/alpine-virt-3.21.3-x86_64.iso

# VM 作成 (VMID: 200)
qm create 200 \
  --name transit-vm-1 \
  --ostype l26 \
  --cpu cputype=host \
  --cores 1 \
  --memory 512 \
  --net0 virtio,bridge=vmbr0 \
  --net1 virtio,bridge=vmbr1 \
  --scsihw virtio-scsi-single \
  --scsi0 ceph-pool:5,discard=on,iothread=1 \
  --cdrom local:iso/alpine-virt-3.21.3-x86_64.iso \
  --boot order=scsi0';'ide2 \
  --start 1
```

> **ヒント:** Alpine Linux のインストールは `setup-alpine` コマンドで対話的に実行する。
> ディスクは `sys` モードでインストールし、インストール後に ISO を取り外して再起動する。

#### Alpine Linux 初期設定

```bash
# VM コンソールにログイン後
setup-alpine
# 対話形式で以下を設定:
#   キーボード: jp / jp
#   ホスト名: transit-vm-1
#   ネットワーク: eth0 は後で手動設定するので none / done
#   タイムゾーン: Asia/Tokyo
#   ミラー: 1 (デフォルト)
#   ユーザ: 必要に応じて作成
#   SSH: openssh
#   ディスク: sda → sys モード
# 完了後:
reboot

# ISO 取り外し (Proxmox ホスト側)
qm set 200 --cdrom none
```

#### ネットワーク設定

```bash
# /etc/network/interfaces
cat > /etc/network/interfaces << 'EOF'
auto lo
iface lo inet loopback

auto eth0
iface eth0 inet static
    address 172.26.26.200
    netmask 255.255.255.0
    # 管理NW にはデフォルト GW を設定しない

auto eth1
iface eth1 inet static
    address 172.26.27.2
    netmask 255.255.255.0
    gateway 172.26.27.1
EOF

# 反映
rc-service networking restart

# 疎通確認
ping -c 3 172.26.27.1    # 自宅ルータ
ping -c 3 172.26.26.1    # 管理NW GW (存在する場合)
ping -c 3 8.8.8.8        # インターネット
```

---

### 0-2. Transit VM のカーネルパラメータ設定

```bash
cat > /etc/sysctl.d/99-transit.conf << 'EOF'
net.ipv4.ip_forward = 1
net.ipv4.conf.all.rp_filter = 2
net.ipv4.conf.default.rp_filter = 2
net.ipv4.conf.eth1.rp_filter = 2
net.netfilter.nf_conntrack_max = 65536
EOF

sysctl -p /etc/sysctl.d/99-transit.conf

# 確認
sysctl net.ipv4.ip_forward
# → 1
```

---

### 0-3. WireGuard セットアップ (Transit VM → VPS)

```bash
# WireGuard インストール
apk update
apk add wireguard-tools nftables

# 鍵生成
mkdir -p /etc/wireguard && chmod 700 /etc/wireguard
wg genkey | tee /etc/wireguard/transit-vps1-privatekey | wg pubkey > /etc/wireguard/transit-vps1-publickey
chmod 600 /etc/wireguard/transit-vps1-privatekey

# 公開鍵を確認 (VPS 側に伝える)
echo "=== Transit VM Public Key ==="
cat /etc/wireguard/transit-vps1-publickey
```

#### Transit VM 側 WireGuard 設定

```bash
# <VPS1_PUBLIC_KEY> と <VPS1_GLOBAL_IP> は実際の値に置き換える
cat > /etc/wireguard/wg0.conf << 'EOF'
[Interface]
PrivateKey = <TRANSIT_VM_VPS1_PRIVATE_KEY の内容をここに貼り付け>
Address = 10.255.1.2/24
ListenPort = 51820
MTU = 1420
Table = off
PostUp = ip route add 10.255.1.0/24 dev wg0
PostUp = sysctl -w net.ipv4.conf.wg0.rp_filter=0
PostDown = ip route del 10.255.1.0/24 dev wg0

[Peer]
PublicKey = <VPS1_PUBLIC_KEY>
Endpoint = <VPS1_GLOBAL_IP>:51820
AllowedIPs = 10.255.1.1/32, 0.0.0.0/0
PersistentKeepalive = 25
EOF

chmod 600 /etc/wireguard/wg0.conf
```

> **秘密鍵の貼り付け:**
> ```bash
> # 秘密鍵の内容を確認
> cat /etc/wireguard/transit-vps1-privatekey
> # 出力例: yAnz5TF+lXXJte14tji3zlMNq+hd2rYUIgJBgB3fBmk=
> # この値を wg0.conf の PrivateKey に記載
> ```

#### VPS 側 WireGuard 設定

```bash
# === VPS #1 で実行 ===

# WireGuard インストール (Ubuntu/Debian)
apt update && apt install -y wireguard wireguard-tools

# IP フォワーディング有効化
echo 'net.ipv4.ip_forward = 1' > /etc/sysctl.d/99-vpn.conf
sysctl -p /etc/sysctl.d/99-vpn.conf

# 鍵生成
mkdir -p /etc/wireguard && chmod 700 /etc/wireguard
wg genkey | tee /etc/wireguard/vps-privatekey | wg pubkey > /etc/wireguard/vps-publickey
chmod 600 /etc/wireguard/vps-privatekey

echo "=== VPS #1 Public Key ==="
cat /etc/wireguard/vps-publickey
# → この値を Transit VM の wg0.conf の [Peer] PublicKey に記載

# 設定ファイル作成
cat > /etc/wireguard/wg0.conf << 'EOF'
[Interface]
PrivateKey = <VPS1_PRIVATE_KEY の内容をここに貼り付け>
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
PublicKey = <TRANSIT_VM_VPS1_PUBLIC_KEY>
AllowedIPs = 10.0.0.0/8, 172.26.27.0/24
PersistentKeepalive = 25
EOF

chmod 600 /etc/wireguard/wg0.conf
```

#### 双方で WireGuard 起動 & 疎通確認

```bash
# === Transit VM ===
wg-quick up wg0
wg show wg0
# → latest handshake が表示されるか確認

ping -c 3 10.255.1.1    # VPS #1 のトンネル IP

# === VPS #1 ===
wg-quick up wg0
wg show wg0

ping -c 3 10.255.1.2    # Transit VM のトンネル IP
ping -c 3 172.26.27.2   # Transit VM の VM NW IP (サマリルート経由)
```

#### WireGuard 自動起動設定

```bash
# === Transit VM (Alpine) ===
ln -sf /etc/init.d/wg-quick /etc/init.d/wg-quick.wg0
rc-update add wg-quick.wg0 default

# === VPS (Ubuntu/Debian) ===
systemctl enable wg-quick@wg0
```

---

### 0-4. nftables 基本ルール (Transit VM)

```bash
cat > /etc/nftables.conf << 'NFTEOF'
#!/usr/sbin/nft -f
flush ruleset

table inet filter {
    chain input {
        type filter hook input priority 0; policy drop;
        ct state established,related accept
        iif lo accept
        ip protocol vrrp accept
        udp dport { 51820 } accept
        iif eth0 tcp dport 22 accept
        icmp type echo-request accept
    }
    chain forward {
        type filter hook forward priority 0; policy drop;
        ct state established,related accept
        iif wg0 oif eth1 accept
        iif eth1 oif wg0 accept
        iif eth1 oif eth1 accept
    }
    chain output {
        type filter hook output priority 0; policy accept;
    }
}
NFTEOF

nft -f /etc/nftables.conf
nft list ruleset

# 永続化
rc-update add nftables default
```

---

### 0-5. Proxmox SDN EVPN テスト VNet 作成

Proxmox Web UI (Datacenter → SDN) または CLI で検証用のテナント VNet を作成する。

```bash
# === pve1 で実行 ===

# EVPN Zone が既にあることを確認
pvesh get /cluster/sdn/zones --output-format json | jq '.[] | select(.type=="evpn")'

# テスト用 VNet 作成 (tenant-test / VNI 19999)
pvesh create /cluster/sdn/vnets \
  --vnet tenant-test \
  --zone evpn-zone \
  --tag 19999

# テスト用サブネット作成 (10.99.0.0/24)
pvesh create /cluster/sdn/vnets/tenant-test/subnets \
  --subnet 10.99.0.0/24 \
  --gateway 10.99.0.1 \
  --type subnet

# SDN 適用 (全ノードに設定を反映)
pvesh set /cluster/sdn

# 確認: 各ノードに VNet ブリッジが作成されたか
# pve1, pve2, pve3 それぞれで:
ip link show | grep tenant-test
# → tenant-test が表示されるはず
```

---

### 0-6. テスト VM 作成 (テナント VNet 上)

テスト VNet に接続する VM を作成し、SDN routing と Transit VM 経由の到達性を検証する。

```bash
# === pve1 で実行 ===

# テスト VM 作成 (VMID: 300) — 軽量 Alpine Linux
qm create 300 \
  --name test-tenant-vm \
  --ostype l26 \
  --cpu cputype=host \
  --cores 1 \
  --memory 256 \
  --net0 virtio,bridge=tenant-test \
  --scsihw virtio-scsi-single \
  --scsi0 ceph-pool:5,discard=on,iothread=1 \
  --cdrom local:iso/alpine-virt-3.21.3-x86_64.iso \
  --boot order=scsi0';'ide2 \
  --start 1
```

#### テスト VM のネットワーク設定

```bash
# テスト VM コンソールにログイン後 (ライブ環境で設定)
# Alpine の setup-interfaces でも良いが、一時的なので直接設定

ip addr add 10.99.0.10/24 dev eth0
ip link set eth0 up
ip route add default via 10.99.0.1

# SDN anycast GW への疎通
ping -c 3 10.99.0.1
# → 応答があれば EVPN Zone + VNet + Subnet は正常

# setup-alpine でインストールする場合は:
# setup-alpine で eth0: 10.99.0.10/24, gw: 10.99.0.1 を指定
```

---

### 0-7. Proxmox Exit Node 設定と外部到達性の確認

テナント VM からインターネットおよび VM Network (172.26.27.0/24) に到達できるようにする。

```bash
# === pve1 で実行 ===

# EVPN Zone に exit node を設定
# Proxmox Web UI: Datacenter → SDN → Zones → evpn-zone → Edit
#   Exit Nodes: pve1 (または pve1,pve2)
#   Primary Exit Node: pve1

# CLI の場合:
pvesh set /cluster/sdn/zones/evpn-zone \
  --exitnodes pve1 \
  --exitnodes-primary pve1

# SDN 適用
pvesh set /cluster/sdn
```

#### exit node のルーティング確認

```bash
# pve1 (exit node) で確認
# FRR/VRF が作成されているか
ip link show type vrf
ip route show vrf <vrf名>

# テナントサブネットからのパケットが vmbr1 に出るか確認
# → exit node がテナントサブネットからのパケットを処理し、
#   vmbr1 (172.26.27.0/24) に転送すること

# テスト VM からインターネットへの疎通 (テスト VM で)
ping -c 3 8.8.8.8
# → exit node → 自宅ルータ → インターネット
# → 応答があれば exit node は正常動作

# テスト VM から VM Network への疎通
ping -c 3 172.26.27.2    # Transit VM
ping -c 3 172.26.27.1    # 自宅ルータ
```

> **troubleshoot:** テスト VM からインターネットに到達できない場合:
> 1. exit node の VRF ルーティング確認: `ip route show vrf <vrf名>`
> 2. exit node で SNAT が必要か確認: EVPN Zone の設定で `advertise-subnets` を有効に
> 3. 自宅ルータに 10.99.0.0/24 → 172.26.27.2 (Transit VM) の静的ルートが必要な場合あり
> 4. exit node 上で tcpdump: `tcpdump -i vmbr1 host 10.99.0.10`

---

### 0-8. Transit VM のサマリルート設定

Transit VM が Proxmox exit node 経由でテナントサブネットに到達できるようにする。

```bash
# === Transit VM で実行 ===

# pve1 の vmbr1 IP を確認 (Proxmox ホストで)
# ssh pve1 "ip -4 addr show vmbr1"
# 例: 172.26.27.10

# Transit VM にサマリルート追加
ip route add 10.0.0.0/8 via 172.26.27.10 dev eth1

# 確認
ip route get 10.99.0.10
# → 10.99.0.10 via 172.26.27.10 dev eth1

# テスト VM への疎通
ping -c 3 10.99.0.10
```

**永続化:**

```bash
# /etc/network/interfaces に追記
cat >> /etc/network/interfaces << 'EOF'
    # テナントサブネットへのサマリルート (exit node 経由)
    up ip route add 10.0.0.0/8 via 172.26.27.10 dev eth1
    down ip route del 10.0.0.0/8 via 172.26.27.10 dev eth1
EOF
```

---

### 0-9. VPS → テナント VM End-to-End 疎通確認

```bash
# === VPS #1 で実行 ===

# テスト VM への疎通 (WireGuard → Transit VM → exit node → EVPN → テスト VM)
ping -c 3 10.99.0.10

# traceroute で経路確認
traceroute -n 10.99.0.10
# 期待:
# 1  10.255.1.2 (Transit VM WireGuard IP)
# 2  172.26.27.10 (exit node / pve1 vmbr1)  ← 見えない場合もある
# 3  10.99.0.10 (テスト VM)
```

---

### 0-10. VPS 側 NAT/リバースプロキシ検証

テスト VM 上で HTTP サーバを起動し、VPS のグローバル IP からアクセスできることを確認する。

```bash
# === テスト VM (10.99.0.10) で実行 ===
# 簡易 HTTP サーバ起動
apk add python3
python3 -m http.server 8080 &

# Transit VM から確認
curl -s http://10.99.0.10:8080/
# → ディレクトリリストが返るはず
```

```bash
# === VPS #1 で実行 ===

# 方法A: nftables で DNAT + SNAT (ポート 18080 → テスト VM:8080)
nft add table inet nat
nft add chain inet nat prerouting '{ type nat hook prerouting priority dstnat; }'
nft add chain inet nat postrouting '{ type nat hook postrouting priority srcnat; }'
nft add rule inet nat prerouting iif eth0 tcp dport 18080 dnat to 10.99.0.10:8080
nft add rule inet nat postrouting oif wg0 masquerade

# 確認
nft list table inet nat

# フォワードルールも必要
nft add table inet filter 2>/dev/null
nft add chain inet filter forward '{ type filter hook forward priority 0; policy accept; }' 2>/dev/null
```

```bash
# === 外部マシン (自分のPC等) から確認 ===
curl -v http://<VPS1_GLOBAL_IP>:18080/
# → テスト VM のディレクトリリストが返れば End-to-End 成功
```

```bash
# === VPS #1: テスト用 NAT ルールの後片付け ===
nft flush table inet nat
nft delete table inet nat
```

---

### Phase 0 検証チェックリスト

| # | 検証項目 | コマンド (実行場所) | 期待結果 | 結果 |
|---|---------|-------------------|---------|------|
| 1 | Transit VM → インターネット | `ping -c3 8.8.8.8` (Transit VM) | 応答あり | ☐ |
| 2 | Transit VM → VPS WireGuard 疎通 | `ping -c3 10.255.1.1` (Transit VM) | 応答あり | ☐ |
| 3 | VPS → Transit VM WireGuard 疎通 | `ping -c3 10.255.1.2` (VPS) | 応答あり | ☐ |
| 4 | VPS → Transit VM VM NW 疎通 | `ping -c3 172.26.27.2` (VPS) | 応答あり | ☐ |
| 5 | テスト VM → SDN anycast GW | `ping -c3 10.99.0.1` (テスト VM) | 応答あり | ☐ |
| 6 | テスト VM → インターネット | `ping -c3 8.8.8.8` (テスト VM) | 応答あり | ☐ |
| 7 | Transit VM → テスト VM | `ping -c3 10.99.0.10` (Transit VM) | 応答あり | ☐ |
| 8 | VPS → テスト VM (E2E) | `ping -c3 10.99.0.10` (VPS) | 応答あり | ☐ |
| 9 | 外部 → VPS → テスト VM (NAT) | `curl http://<VPS_IP>:18080/` (外部) | HTTP 応答 | ☐ |
| 10 | WireGuard ハンドシェイク | `wg show wg0` (両側) | latest handshake 表示 | ☐ |

**全項目パス → Phase 1 に進む**

---

## Phase 1: Proxmox SDN テナント操作の検証

### 目的

管理パネルから実行する Proxmox SDN API 操作 (VNet 作成、Subnet 作成、SDN 適用) が正しく動作し、テナント隔離が機能することを確認する。

---

### 1-1. テナント VNet の作成 (API 操作)

管理パネルが行うのと同じ操作を Proxmox API (pvesh) で手動検証する。

```bash
# === pve1 で実行 ===

# テナント1用 VNet 作成
pvesh create /cluster/sdn/vnets \
  --vnet tenant-1 \
  --zone evpn-zone \
  --tag 10001

# テナント1用サブネット作成
pvesh create /cluster/sdn/vnets/tenant-1/subnets \
  --subnet 10.1.0.0/24 \
  --gateway 10.1.0.1 \
  --type subnet

# テナント2用 VNet 作成
pvesh create /cluster/sdn/vnets \
  --vnet tenant-2 \
  --zone evpn-zone \
  --tag 10002

pvesh create /cluster/sdn/vnets/tenant-2/subnets \
  --subnet 10.2.0.0/24 \
  --gateway 10.2.0.1 \
  --type subnet

# SDN 適用 (全ノードに反映)
pvesh set /cluster/sdn

# 確認: 各ノードに VNet ブリッジが作成されたか
for node in pve1 pve2 pve3; do
  echo "=== $node ==="
  ssh $node "ip link show | grep -E 'tenant-[12]'"
done
```

---

### 1-2. テナント隔離の検証

2つのテナントが互いに通信できないことを確認する。

```bash
# テナント1の VM 作成 (VMID: 301)
qm create 301 \
  --name test-tenant1-vm \
  --ostype l26 \
  --cores 1 --memory 256 \
  --net0 virtio,bridge=tenant-1 \
  --scsihw virtio-scsi-single \
  --scsi0 ceph-pool:5,discard=on,iothread=1 \
  --cdrom local:iso/alpine-virt-3.21.3-x86_64.iso \
  --boot order=scsi0';'ide2 \
  --start 1

# テナント2の VM 作成 (VMID: 302) — pve2 に配置して VXLAN も検証
qm create 302 \
  --name test-tenant2-vm \
  --ostype l26 \
  --cores 1 --memory 256 \
  --net0 virtio,bridge=tenant-2 \
  --scsihw virtio-scsi-single \
  --scsi0 ceph-pool:5,discard=on,iothread=1 \
  --cdrom local:iso/alpine-virt-3.21.3-x86_64.iso \
  --boot order=scsi0';'ide2 \
  --start 1
```

> **pve2 に VM を作成するには:** `qm create` を pve2 で実行するか、`qm migrate` を使用する。

```bash
# === テナント1 VM (301) コンソールで設定 ===
ip addr add 10.1.0.10/24 dev eth0
ip link set eth0 up
ip route add default via 10.1.0.1

# GW への疎通確認
ping -c 3 10.1.0.1
# → 応答あり

# === テナント2 VM (302) コンソールで設定 ===
ip addr add 10.2.0.10/24 dev eth0
ip link set eth0 up
ip route add default via 10.2.0.1

ping -c 3 10.2.0.1
# → 応答あり
```

#### テナント隔離テスト

```bash
# テナント1 VM からテナント2 VM に ping
# === テナント1 VM (10.1.0.10) ===
ping -c 3 10.2.0.10
# → 応答なし (timeout) ← テナント間は隔離されている

# テナント2 VM からテナント1 VM に ping
# === テナント2 VM (10.2.0.10) ===
ping -c 3 10.1.0.10
# → 応答なし (timeout) ← 同上
```

#### 同一テナント・異なるノード間の通信 (VXLAN 検証)

```bash
# テナント1にもう1台 VM を pve2 上に作成 (VMID: 303)
# pve2 で実行:
qm create 303 \
  --name test-tenant1-vm2 \
  --ostype l26 \
  --cores 1 --memory 256 \
  --net0 virtio,bridge=tenant-1 \
  --scsihw virtio-scsi-single \
  --scsi0 ceph-pool:5,discard=on,iothread=1 \
  --cdrom local:iso/alpine-virt-3.21.3-x86_64.iso \
  --boot order=scsi0';'ide2 \
  --start 1

# === テナント1 VM2 (303) コンソールで設定 ===
ip addr add 10.1.0.11/24 dev eth0
ip link set eth0 up
ip route add default via 10.1.0.1

# === テナント1 VM1 (301, pve1) → テナント1 VM2 (303, pve2) ===
ping -c 3 10.1.0.11
# → 応答あり ← VXLAN で異なるノード間でも L2 通信可能
```

---

### 1-3. VPS → 各テナント VM への到達確認

```bash
# === VPS #1 で実行 ===

# テナント1 VM
ping -c 3 10.1.0.10
# → 応答あり

# テナント2 VM
ping -c 3 10.2.0.10
# → 応答あり

# テナント1 VM2 (pve2 上)
ping -c 3 10.1.0.11
# → 応答あり
```

---

### 1-4. テナント VNet の削除 (クリーンアップ確認)

管理パネルでテナント削除時に必要になる操作の検証。

```bash
# テスト VM を先に停止・削除
qm stop 301 && qm destroy 301
qm stop 303 && qm destroy 303   # pve2 で実行
# テナント2
qm stop 302 && qm destroy 302

# サブネット削除
pvesh delete /cluster/sdn/vnets/tenant-1/subnets/10.1.0.0-24
pvesh delete /cluster/sdn/vnets/tenant-2/subnets/10.2.0.0-24

# VNet 削除
pvesh delete /cluster/sdn/vnets/tenant-1
pvesh delete /cluster/sdn/vnets/tenant-2

# SDN 適用
pvesh set /cluster/sdn

# 確認: VNet ブリッジが削除されたか
ip link show | grep -E 'tenant-[12]'
# → 表示なし
```

---

### Phase 1 検証チェックリスト

| # | 検証項目 | 期待結果 | 結果 |
|---|---------|---------|------|
| 1 | VNet 作成 (API) | VNet ブリッジが全ノードに作成される | ☐ |
| 2 | Subnet 作成 (API) | anycast GW に ping が通る | ☐ |
| 3 | テナント内通信 (同一ノード) | 10.1.0.10 ↔ 10.1.0.1 応答あり | ☐ |
| 4 | テナント内通信 (異ノード / VXLAN) | 10.1.0.10 ↔ 10.1.0.11 応答あり | ☐ |
| 5 | テナント間隔離 | 10.1.0.10 → 10.2.0.10 応答なし | ☐ |
| 6 | テナント VM → インターネット | exit node 経由で 8.8.8.8 に到達 | ☐ |
| 7 | VPS → テナント VM (E2E) | VPS から各テナント VM に ping 通る | ☐ |
| 8 | VNet 削除 (API) | VNet ブリッジが全ノードから削除される | ☐ |

**全項目パス → Phase 2 に進む**

---

## Phase 2: Cloud-init テンプレートと VM プロビジョニング

### 目的

Packer で VM テンプレートをビルドし、Cloud-init を使ったテンプレートクローン → VM 起動のフローが動作することを確認する。

---

### 2-1. Ubuntu 24.04 Cloud Image テンプレートの作成

Packer ビルドの前に、まず手動で Cloud-init テンプレートを作成して動作確認する。

```bash
# === pve1 で実行 ===

# Ubuntu 24.04 Cloud Image をダウンロード
cd /tmp
wget https://cloud-images.ubuntu.com/noble/current/noble-server-cloudimg-amd64.img

# テンプレート VM 作成 (VMID: 9000)
qm create 9000 \
  --name tmpl-base-ubuntu \
  --ostype l26 \
  --cpu cputype=host \
  --cores 2 \
  --memory 2048 \
  --agent enabled=1 \
  --net0 virtio,bridge=tenant-test \
  --scsihw virtio-scsi-single \
  --serial0 socket \
  --vga serial0

# Cloud Image をディスクとしてインポート
qm importdisk 9000 noble-server-cloudimg-amd64.img ceph-pool

# インポートしたディスクをアタッチ
qm set 9000 --scsi0 ceph-pool:vm-9000-disk-0,discard=on,iothread=1

# Cloud-init ドライブ追加
qm set 9000 --ide2 ceph-pool:cloudinit

# ブート順序設定
qm set 9000 --boot order=scsi0

# テンプレートに変換
qm template 9000

# 確認
qm config 9000
```

---

### 2-2. スニペット API の動作確認

Cloud-init のカスタムスニペット (user-data, network-config) を各ノードに配置する API の検証。

```bash
# === pve1 で実行 ===

# スニペットディレクトリ確認
ls -la /var/lib/vz/snippets/
# ディレクトリが存在しなければ作成
mkdir -p /var/lib/vz/snippets

# テスト用 Cloud-init user-data スニペットを直接配置 (API の代わり)
cat > /var/lib/vz/snippets/vm-400-user-data.yaml << 'EOF'
#cloud-config
hostname: test-cloud-init
manage_etc_hosts: true
users:
  - name: ubuntu
    sudo: ALL=(ALL) NOPASSWD:ALL
    shell: /bin/bash
    ssh_authorized_keys:
      - ssh-ed25519 AAAA... your-key-here
packages:
  - curl
  - vim
  - htop
runcmd:
  - echo "Cloud-init provisioning complete" > /var/log/cloud-init-done.txt
EOF

# テスト用 network-config スニペット
cat > /var/lib/vz/snippets/vm-400-network-config.yaml << 'EOF'
version: 2
ethernets:
  eth0:
    addresses:
      - 10.99.0.20/24
    routes:
      - to: default
        via: 10.99.0.1
    nameservers:
      addresses:
        - 8.8.8.8
        - 8.8.4.4
EOF
```

---

### 2-3. テンプレートからクローン → Cloud-init で起動

```bash
# テンプレートからフルクローン (VMID: 400)
qm clone 9000 400 \
  --name test-cloudinit-vm \
  --full \
  --storage ceph-pool

# Cloud-init 設定 (スニペット参照)
qm set 400 \
  --cicustom "user=local:snippets/vm-400-user-data.yaml,network=local:snippets/vm-400-network-config.yaml"

# テナントテスト VNet に接続
qm set 400 --net0 virtio,bridge=tenant-test

# ディスクリサイズ (20GB に拡張)
qm resize 400 scsi0 20G

# VM 起動
qm start 400

# Cloud-init の進捗確認 (コンソールまたは qm guest exec)
# 数分待ってから...
qm guest exec 400 -- cat /var/log/cloud-init-done.txt
# → "Cloud-init provisioning complete"

# ネットワーク確認
qm guest exec 400 -- ip addr show eth0
# → 10.99.0.20/24

qm guest exec 400 -- ping -c 3 8.8.8.8
# → 応答あり
```

> **qm guest exec が使えない場合:** VM コンソール (Proxmox Web UI) からログインして確認する。
> ユーザ名: ubuntu (Cloud-init で設定)

---

### 2-4. VPS からクローン VM への疎通確認

```bash
# === VPS #1 で実行 ===
ping -c 3 10.99.0.20

# HTTP テスト (VM 上で簡易サーバ起動)
# VM コンソールから:
python3 -m http.server 8080 &

# VPS から:
curl -s http://10.99.0.20:8080/
```

---

### 2-5. クリーンアップ

```bash
# テスト VM 削除
qm stop 400 && qm destroy 400 --purge

# テンプレートは残す (Phase 3 以降でも使用する場合)
# 不要なら: qm destroy 9000

# テスト VNet のテスト VM がまだある場合は削除
qm stop 300 && qm destroy 300 2>/dev/null

# スニペット削除
rm -f /var/lib/vz/snippets/vm-400-*.yaml
```

---

### Phase 2 検証チェックリスト

| # | 検証項目 | 期待結果 | 結果 |
|---|---------|---------|------|
| 1 | Cloud Image インポート | テンプレート (VMID 9000) が作成される | ☐ |
| 2 | テンプレートからクローン | VM (VMID 400) がフルクローンで作成される | ☐ |
| 3 | Cloud-init スニペット適用 | user-data, network-config が VM に反映 | ☐ |
| 4 | Cloud-init による IP 設定 | eth0 に 10.99.0.20/24 が設定される | ☐ |
| 5 | Cloud-init パッケージ導入 | curl, vim, htop がインストールされている | ☐ |
| 6 | ディスクリサイズ | 20GB に拡張される | ☐ |
| 7 | VM → インターネット | 8.8.8.8 に到達可能 | ☐ |
| 8 | VPS → クローン VM (E2E) | VPS から 10.99.0.20 に到達可能 | ☐ |

**全項目パス → Phase 3 に進む**

---

## Phase 3: mgmt-docker VM と Docker Compose 基盤

### 目的

管理サービス群を動かす Docker ホスト VM が正常に動作し、コンテナ起動・ネットワーク到達性を確認する。

---

### 3-1. mgmt-docker VM の作成

```bash
# === pve1 で実行 ===

# Cloud Image テンプレートからクローン (VMID: 100)
qm clone 9000 100 \
  --name mgmt-docker \
  --full \
  --storage ceph-pool

# スペック設定
qm set 100 \
  --cores 4 \
  --memory 8192 \
  --net0 virtio,bridge=vmbr0

# Cloud-init で基本設定
# user-data スニペット作成
cat > /var/lib/vz/snippets/vm-100-user-data.yaml << 'EOF'
#cloud-config
hostname: mgmt-docker
manage_etc_hosts: true
users:
  - name: ubuntu
    sudo: ALL=(ALL) NOPASSWD:ALL
    shell: /bin/bash
    ssh_authorized_keys:
      - ssh-ed25519 AAAA... your-key-here
packages:
  - curl
  - vim
  - git
  - htop
  - ca-certificates
  - gnupg
runcmd:
  - curl -fsSL https://get.docker.com | sh
  - usermod -aG docker ubuntu
  - systemctl enable docker
EOF

cat > /var/lib/vz/snippets/vm-100-network-config.yaml << 'EOF'
version: 2
ethernets:
  eth0:
    addresses:
      - 172.26.26.10/24
    nameservers:
      addresses:
        - 8.8.8.8
        - 8.8.4.4
EOF

qm set 100 \
  --cicustom "user=local:snippets/vm-100-user-data.yaml,network=local:snippets/vm-100-network-config.yaml"

# ディスクリサイズ
qm resize 100 scsi0 100G

# 起動
qm start 100
```

> **注意:** 管理 NW (172.26.26.0/24) はデフォルト GW がないため、インターネットアクセスが必要な場合は
> 一時的に vmbr1 の NIC を追加するか、管理 NW のルータ設定を確認する。

---

### 3-2. Docker / Docker Compose の確認

```bash
# === mgmt-docker VM (172.26.26.10) に SSH ===
ssh ubuntu@172.26.26.10

# Docker 確認
docker --version
docker compose version

# テストコンテナ起動
docker run --rm hello-world
```

---

### 3-3. MySQL コンテナの起動テスト

```bash
# テスト用 compose.yaml
mkdir -p /opt/test-compose && cd /opt/test-compose

cat > compose.yaml << 'EOF'
services:
  db:
    image: mysql:8.4
    environment:
      MYSQL_ROOT_PASSWORD: test-password-12345
      MYSQL_DATABASE: misosiru
    ports:
      - "3306:3306"
    volumes:
      - db-data:/var/lib/mysql

volumes:
  db-data:
EOF

docker compose up -d

# 接続テスト
docker compose exec db mysql -uroot -p'test-password-12345' -e "SHOW DATABASES;"
# → misosiru が表示される

# クリーンアップ
docker compose down -v
rm -rf /opt/test-compose
```

---

### 3-4. Transit VM から mgmt-docker VM への疎通

```bash
# Transit VM (172.26.26.200) から mgmt-docker (172.26.26.10) への疎通
# === Transit VM ===
ping -c 3 172.26.26.10
ssh ubuntu@172.26.26.10 "hostname"
# → mgmt-docker
```

---

### Phase 3 検証チェックリスト

| # | 検証項目 | 期待結果 | 結果 |
|---|---------|---------|------|
| 1 | mgmt-docker VM 起動 | Cloud-init で正常起動 | ☐ |
| 2 | Docker インストール | `docker --version` 正常出力 | ☐ |
| 3 | Docker Compose | `docker compose version` 正常出力 | ☐ |
| 4 | MySQL コンテナ起動 | 接続して SHOW DATABASES 成功 | ☐ |
| 5 | Transit VM → mgmt-docker 疎通 | 管理 NW 経由で ping 通る | ☐ |

**全項目パス → Phase 4 に進む**

---

## Phase 4: NAT / リバースプロキシ検証

### 目的

VPS のグローバル IP 経由で外部からテナント VM のサービスにアクセスできることを、nginx リバースプロキシで確認する。

---

### 4-1. テスト Web サーバの準備

```bash
# === テスト VM (10.99.0.10 等、Phase 0 のテスト VM を再利用 or 新規) ===

# nginx をインストールして起動
apk add nginx
cat > /etc/nginx/http.d/default.conf << 'EOF'
server {
    listen 80;
    location / {
        return 200 "Hello from tenant VM ($(hostname))\n";
        add_header Content-Type text/plain;
    }
}
EOF
rc-service nginx start
```

---

### 4-2. VPS 側 nginx リバースプロキシの設定

```bash
# === VPS #1 で実行 ===

apt install -y nginx

# リバースプロキシ設定
cat > /etc/nginx/sites-available/test-proxy.conf << 'EOF'
server {
    listen 80;
    server_name test.example.com;

    # テスト用: どのドメインでもアクセス可能に
    server_name _;

    location / {
        proxy_pass http://10.99.0.10:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
EOF

ln -sf /etc/nginx/sites-available/test-proxy.conf /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

nginx -t && systemctl reload nginx
```

---

### 4-3. 外部アクセステスト

```bash
# === 外部マシン (自分の PC) から ===
curl -v http://<VPS1_GLOBAL_IP>/
# → "Hello from tenant VM (...)" が返れば成功

# X-Forwarded-For ヘッダの確認 (テスト VM 側でヘッダをログに出力)
```

---

### 4-4. TCP/UDP ストリームプロキシのテスト (ゲームサーバ想定)

```bash
# === テスト VM で nc リスナー起動 ===
# Alpine の場合
apk add nmap-ncat
ncat -l -p 25565 -k -e '/bin/echo TCP connection successful'

# === VPS #1 で nginx stream 設定 ===
cat >> /etc/nginx/nginx.conf << 'EOF'

stream {
    server {
        listen 25565;
        proxy_pass 10.99.0.10:25565;
    }
}
EOF

nginx -t && systemctl reload nginx

# === 外部マシンからテスト ===
echo "test" | nc -w 3 <VPS1_GLOBAL_IP> 25565
# → "TCP connection successful"
```

---

### 4-5. クリーンアップ

```bash
# VPS 側
rm /etc/nginx/sites-enabled/test-proxy.conf
# nginx.conf から stream ブロックを削除
systemctl reload nginx
```

---

### Phase 4 検証チェックリスト

| # | 検証項目 | 期待結果 | 結果 |
|---|---------|---------|------|
| 1 | VPS nginx → テナント VM HTTP | 外部から HTTP 応答取得 | ☐ |
| 2 | X-Forwarded-For ヘッダ | クライアント IP が含まれる | ☐ |
| 3 | TCP ストリームプロキシ | 外部から TCP 接続成功 | ☐ |

**全項目パス → Phase 5 に進む**

---

## Phase 5: Keepalived (VRRP) 冗長化検証

### 目的

Transit VM の Active/Standby 構成で、VIP フェイルオーバーが正しく動作することを確認する。

---

### 5-1. Transit VM #2 (Standby) の作成

```bash
# === pve2 で実行 ===

# Alpine ISO がなければダウンロード
ls /var/lib/vz/template/iso/alpine-virt-*.iso || \
  wget -P /var/lib/vz/template/iso/ \
    https://dl-cdn.alpinelinux.org/alpine/v3.21/releases/x86_64/alpine-virt-3.21.3-x86_64.iso

# VM 作成 (VMID: 201)
qm create 201 \
  --name transit-vm-2 \
  --ostype l26 \
  --cpu cputype=host \
  --cores 1 \
  --memory 512 \
  --net0 virtio,bridge=vmbr0 \
  --net1 virtio,bridge=vmbr1 \
  --scsihw virtio-scsi-single \
  --scsi0 ceph-pool:5,discard=on,iothread=1 \
  --cdrom local:iso/alpine-virt-3.21.3-x86_64.iso \
  --boot order=scsi0';'ide2 \
  --start 1
```

Alpine インストール後、Transit VM #1 と同様にセットアップするが、以下のIPが異なる:

```bash
# /etc/network/interfaces (Transit VM #2)
cat > /etc/network/interfaces << 'EOF'
auto lo
iface lo inet loopback

auto eth0
iface eth0 inet static
    address 172.26.26.201
    netmask 255.255.255.0

auto eth1
iface eth1 inet static
    address 172.26.27.3
    netmask 255.255.255.0
    gateway 172.26.27.1
    up ip route add 10.0.0.0/8 via 172.26.27.10 dev eth1
    down ip route del 10.0.0.0/8 via 172.26.27.10 dev eth1
EOF
```

WireGuard も同様にセットアップする (鍵は別途生成、IP は 10.255.1.3):

```bash
# WireGuard 設定 (Transit VM #2)
apk add wireguard-tools keepalived nftables

wg genkey | tee /etc/wireguard/transit-vps1-privatekey | wg pubkey > /etc/wireguard/transit-vps1-publickey
chmod 600 /etc/wireguard/transit-vps1-privatekey

cat > /etc/wireguard/wg0.conf << 'EOF'
[Interface]
PrivateKey = <TRANSIT_VM2_PRIVATE_KEY>
Address = 10.255.1.3/24
ListenPort = 51820
MTU = 1420
Table = off
PostUp = ip route add 10.255.1.0/24 dev wg0
PostUp = sysctl -w net.ipv4.conf.wg0.rp_filter=0
PostDown = ip route del 10.255.1.0/24 dev wg0

[Peer]
PublicKey = <VPS1_PUBLIC_KEY>
Endpoint = <VPS1_GLOBAL_IP>:51820
AllowedIPs = 10.255.1.1/32, 0.0.0.0/0
PersistentKeepalive = 25
EOF
```

---

### 5-2. Keepalived のインストールと設定

```bash
# === Transit VM #1 (Active) ===
apk add keepalived

cat > /etc/keepalived/keepalived.conf << 'EOF'
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
        auth_pass misosiru-vrrp
    }

    virtual_ipaddress {
        172.26.27.254/24
    }

    track_script {
        chk_wireguard
    }
}
EOF

# ヘルスチェックスクリプト
cat > /usr/local/bin/check-wg.sh << 'EOF'
#!/bin/sh
for ip in 10.255.1.1; do
    ping -c 1 -W 2 "$ip" > /dev/null 2>&1 && exit 0
done
exit 1
EOF
chmod +x /usr/local/bin/check-wg.sh

rc-update add keepalived default
rc-service keepalived start
```

```bash
# === Transit VM #2 (Standby) ===
cat > /etc/keepalived/keepalived.conf << 'EOF'
global_defs {
    router_id TRANSIT_2
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
    state BACKUP
    interface eth1
    virtual_router_id 51
    priority 90
    advert_int 1

    authentication {
        auth_type PASS
        auth_pass misosiru-vrrp
    }

    virtual_ipaddress {
        172.26.27.254/24
    }

    track_script {
        chk_wireguard
    }
}
EOF

# 同じヘルスチェックスクリプト
cat > /usr/local/bin/check-wg.sh << 'EOF'
#!/bin/sh
for ip in 10.255.1.1; do
    ping -c 1 -W 2 "$ip" > /dev/null 2>&1 && exit 0
done
exit 1
EOF
chmod +x /usr/local/bin/check-wg.sh

rc-update add keepalived default
rc-service keepalived start
```

---

### 5-3. VIP 動作確認

```bash
# === Transit VM #1 ===
ip addr show eth1 | grep 172.26.27.254
# → 172.26.27.254/24 が表示される (VIP を保持中)

# === Transit VM #2 ===
ip addr show eth1 | grep 172.26.27.254
# → 表示されない (Standby)
```

---

### 5-4. フェイルオーバーテスト

```bash
# === 別のマシン (mgmt-docker 等) から VIP に継続 ping ===
ping 172.26.27.254

# === Transit VM #1 でフェイルオーバーをシミュレート ===
# 方法1: Keepalived を停止
rc-service keepalived stop

# → ping が数秒途切れた後、再開されるはず

# === Transit VM #2 で VIP を確認 ===
ip addr show eth1 | grep 172.26.27.254
# → 172.26.27.254/24 が表示される (VIP がフェイルオーバーした)

# === フェイルバック ===
# Transit VM #1 で Keepalived を再開
rc-service keepalived start

# → Transit VM #1 が MASTER に戻り VIP を取り戻す (priority が高いため)
ip addr show eth1 | grep 172.26.27.254
# → Transit VM #1 に戻る
```

---

### Phase 5 検証チェックリスト

| # | 検証項目 | 期待結果 | 結果 |
|---|---------|---------|------|
| 1 | VIP が Active に付与 | Transit VM #1 に 172.26.27.254 | ☐ |
| 2 | Active 停止で VIP 移動 | Transit VM #2 に VIP がフェイルオーバー | ☐ |
| 3 | フェイルバック | Transit VM #1 再開で VIP が戻る | ☐ |
| 4 | フェイルオーバー中の ping 途切れ | 数秒以内に復旧 | ☐ |

---

## 全体クリーンアップ手順

全フェーズの検証が完了した後、テスト用リソースを削除する。

```bash
# === Proxmox ===

# テスト VM の停止・削除
for vmid in 200 201 300 301 302 303 400; do
  qm stop $vmid 2>/dev/null
  qm destroy $vmid --purge 2>/dev/null
done

# テスト VNet / Subnet の削除
pvesh delete /cluster/sdn/vnets/tenant-test/subnets/10.99.0.0-24 2>/dev/null
pvesh delete /cluster/sdn/vnets/tenant-test 2>/dev/null
pvesh delete /cluster/sdn/vnets/tenant-1/subnets/10.1.0.0-24 2>/dev/null
pvesh delete /cluster/sdn/vnets/tenant-1 2>/dev/null
pvesh delete /cluster/sdn/vnets/tenant-2/subnets/10.2.0.0-24 2>/dev/null
pvesh delete /cluster/sdn/vnets/tenant-2 2>/dev/null
pvesh set /cluster/sdn

# テンプレート (検証後不要なら)
qm destroy 9000 2>/dev/null

# スニペット削除
rm -f /var/lib/vz/snippets/vm-*.yaml

# === VPS ===
# テスト用 nftables / nginx 設定を削除
# (各フェーズのクリーンアップ手順を参照)
```

> **残すもの:** Transit VM #1 / #2 と WireGuard / Keepalived 設定は、本番でもそのまま使用するため削除しない。
> mgmt-docker VM (VMID 100) も残して開発環境として使用可能。

---

## 検証結果サマリ

| Phase | 検証内容 | 項目数 | 通過 | 不通過 |
|-------|---------|--------|------|--------|
| 0 | ネットワーク基盤 (WireGuard, EVPN, ルーティング) | 10 | | |
| 1 | SDN テナント操作 (VNet CRUD, 隔離) | 8 | | |
| 2 | Cloud-init テンプレート & VM プロビジョニング | 8 | | |
| 3 | mgmt-docker VM & Docker Compose | 5 | | |
| 4 | NAT / リバースプロキシ | 3 | | |
| 5 | Keepalived VRRP 冗長化 | 4 | | |
| **合計** | | **38** | | |

**全 38 項目をパスしたら、管理パネルの開発に着手する。**
