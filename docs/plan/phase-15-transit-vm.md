# Phase 15: Transit VM & ネットワーク基盤 (VRRP / PBR / WireGuard)

## 概要

外部 VPS と自宅ネットワークを結ぶ **Transit VM** (Active-Standby VRRP 構成) を構築する。
Transit VM は Alpine Linux + FRR + Keepalived + WireGuard で構成され、
ポリシーベースルーティング (PBR) により通信経路を自動切り替えする。

Phase 7 (VPS ゲートウェイ管理) は管理パネル側の UI/API を担当しており、
このフェーズは Transit VM 自体のインフラ構築を担当する。

## 現在の判定

❌ 未着手

---

## 出典（設計ドキュメント）

- [gemini-plan-7.md セクション 3](../gemini-plan-7.md) — Transit VM / VRRP / PBR 設計
- [infrastructure-design.md](../infrastructure-design.md) — ネットワーク構成図
- [vpn-setup-guide.md](../vpn-setup-guide.md) — VPN 設定ガイド
- [transit-vm-network-design.md](../transit-vm-network-design.md) — Transit VM ネットワーク設計

---

## チェックポイント

### 15-1. Transit VM 基本構成

- [ ] Transit VM を 2 台 (Active / Standby) として Proxmox 上に作成
  - OS: Alpine Linux (軽量)
  - NIC1: 管理ネットワーク (`vmbr0`, 172.26.26.0/24)
  - NIC2: VM ネットワーク (`vmbr1`, 172.26.27.0/24)
- [ ] ホスト名: `transit-vm-1`, `transit-vm-2`
- [ ] 必要パッケージインストール
  - `keepalived` — VRRP による VIP 管理
  - `frr` — FRRouting (BGP/EVPN)
  - `wireguard-tools` — WireGuard VPN
  - `iptables` — パケットフィルタリング / NAT

### 15-2. Keepalived (VRRP) 設定

- [ ] VRRP インスタンス設定
  - VIP (仮想 IP): 172.26.27.1 (VM ネットワーク側)
  - VRID: 設定
  - 優先度: Active=100, Standby=90
  - Authentication: パスワード認証
- [ ] `keepalived.conf` テンプレート作成 (Active 用 / Standby 用)
- [ ] フェイルオーバー動作確認
  - Active 停止時に Standby が VIP を引き継ぐこと
  - Active 復帰時に VIP が戻ること（preempt 設定）

### 15-3. WireGuard VPN 設定

- [ ] 各 VPS との WireGuard トンネル設定
  - Transit VM 側の秘密鍵/公開鍵生成
  - `wg{N}.conf` の作成（VPS ごとに 1 つ）
  - WireGuard サブネット: `10.255.{vps_id}.0/24`
  - Listen Port: `51820 + N`
- [ ] systemd サービスとしての WireGuard 自動起動
  - `wg-quick@wg0.service` 等
- [ ] Active/Standby 両方に同一 WireGuard 設定を配置
  - VIP でルーティングされるため、アクティブ側のみ通信が到達

### 15-4. FRR (FRRouting) 設定

- [ ] FRR 設定ファイル（BGP/EVPN サブネット広告）
  - Proxmox SDN (EVPN) との経路交換
  - テナント VNet のサブネットを Transit VM 経由で到達可能にする
- [ ] 管理ネットワーク / VM ネットワーク間のルーティング
- [ ] VPS からテナント VM への到達経路確認

### 15-5. ポリシーベースルーティング (PBR)

> 通常の Outbound は自宅ルーター経由（高速）。
> VPS 経由のインバウンドへの返信のみ Transit VM (VIP) へルーティング。

- [ ] ip rule / ip route テーブルの設定
  - VPS 経由のパケットに対してマーキング
  - マーキングされたパケットは Transit VM 経由で返信
  - それ以外のアウトバウンドは自宅ルーター経由
- [ ] iptables マーキングルール
  - `connmark` / `mark` を使用した接続トラッキング
- [ ] PBR 設定の永続化（再起動後も有効）

### 15-6. iptables / NAT 設定

- [ ] MASQUERADE ルール (VPS → テナント VM の SNAT)
- [ ] DNAT ルール (VPS のポート → テナント VM の IP:ポート)
- [ ] フォワーディング許可ルール
  - VPS → テナント VM: 許可ポートのみ
  - テナント VM → VPS: 確立済み接続のみ
- [ ] iptables ルールの永続化

### 15-7. VPS 側 nginx / リバースプロキシ設定

> VPS 側の設定テンプレート（Phase 7 の VpsGatewayService で生成する conf と連携）

- [ ] HTTP/HTTPS リバースプロキシ設定テンプレート
  - `*.containers.example.com` → WireGuard → Transit VM → Traefik (Nomad)
  - 管理パネル → WireGuard → Transit VM → mgmt-app
- [ ] TCP/UDP ストリームプロキシ設定テンプレート（ゲームサーバ等）
- [ ] WireGuard トンネル経由でのヘルスチェック設定

### 15-8. 構成管理スクリプト

- [ ] `infra/transit-vm/` ディレクトリ作成
- [ ] `infra/transit-vm/setup-active.sh` — Active 側セットアップスクリプト
- [ ] `infra/transit-vm/setup-standby.sh` — Standby 側セットアップスクリプト
- [ ] `infra/transit-vm/keepalived/` — Keepalived 設定テンプレート
- [ ] `infra/transit-vm/wireguard/` — WireGuard 設定テンプレート
- [ ] `infra/transit-vm/frr/` — FRR 設定テンプレート
- [ ] `infra/transit-vm/iptables/` — iptables ルールファイル

### 15-9. 運用ドキュメント

- [ ] `docs/deployment-operations.md` に Transit VM 運用手順を追記
  - フェイルオーバー手順
  - WireGuard トンネル追加手順（新 VPS 追加時）
  - VIP 確認・切り替え手順
  - FRR 経路確認手順

### 15-10. テスト & 検証

- [ ] VRRP フェイルオーバーテスト
  - Active VM を停止 → Standby が VIP を引き継ぎ → 外部 VPS からの通信が継続すること
- [ ] WireGuard トンネル接続確認
  - VPS から Transit VM への ping 疎通
  - Transit VM から VPS への ping 疎通
- [ ] PBR テスト
  - VPS 経由のリクエスト → テナント VM → 返信が VPS 経由で戻ること
  - テナント VM → インターネット → 自宅ルーター経由で出ること
- [ ] テナント VM 到達テスト
  - VPS → WireGuard → Transit VM → テナント VM (SDN VNet) への HTTP アクセス
- [ ] iptables ルールの検証
  - 許可ポートのみフォワードされること
  - 不正ポートが拒否されること

---

## 完了条件

- Transit VM 2 台が Active-Standby で稼働し、VRRP フェイルオーバーが動作すること
- VPS から WireGuard トンネル経由でテナント VM にアクセスできること
- PBR により VPS 経由のインバウンドの返信が正しく VPS に戻ること
- 通常のアウトバウンドが自宅ルーター経由であること
- 全設定が永続化され、VM 再起動後も動作すること
