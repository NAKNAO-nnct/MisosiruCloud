# Phase 7: ネットワーク管理 & VPS ゲートウェイ管理

## 概要

テナントネットワーク（Proxmox SDN VNet）の可視化・管理と、  
外部 VPS ゲートウェイ（WireGuard VPN）の登録・状態管理を実装する。

## 現在の判定（2026-03-29）

✅ 実装済み

Network/VPS/DNS 管理機能（Service/Controller/View/Route/Test）は実装済み。

---

## チェックポイント

### 7-1. VpsGatewayService

- [x] `App\Services\VpsGatewayService` 作成
  - `register(array $params): VpsGateway`
    - `wireguard_ip` を `10.255.{id}.1` で自動採番
    - `transit_wireguard_port` を `51820 + N` で自動採番
    - WireGuard conf ファイル生成（`wg{N}.conf` の内容をレスポンスに含める）
  - `sync(VpsGateway $gateway): array`（VPS 側の接続状態を確認）
  - `generateWireguardConfig(VpsGateway $gateway): string`（Transit VM 用 wg conf 生成）
  - `destroy(VpsGateway $gateway): void`

### 7-2. ネットワーク管理コントローラ

- [x] `Network\Index` — テナントネットワーク一覧（Proxmox SDN VNet と DB を突合）
- [x] `Network\Create` — ネットワーク作成画面
- [x] `Network\Store` — ネットワーク作成（Proxmox SDN API 経由）
- [x] `Network\Show` — ネットワーク詳細（VNet 情報、接続 VM 一覧）
- [x] `Network\Destroy` — ネットワーク削除（SDN VNet 削除）

### 7-3. VPS ゲートウェイ管理コントローラ（admin のみ）

- [x] `Admin\Vps\Index` — VPS ゲートウェイ一覧
- [x] `Admin\Vps\Store` — VPS 登録（WireGuard conf 生成・表示）
- [x] `Admin\Vps\Show` — VPS 詳細（接続状態・設定確認）
- [x] `Admin\Vps\Update` — VPS 設定更新
- [x] `Admin\Vps\Destroy` — VPS 登録削除
- [x] `Admin\Vps\Sync` — VPS 接続状態同期

### 7-4. Proxmox ノード管理コントローラ（admin のみ）

- [x] `Admin\Node\Index` — Proxmox ノード一覧（API 接続状態バッジ付き）
- [x] `Admin\Node\Store` — ノード登録（API トークン暗号化保存）
- [x] `Admin\Node\Update` — ノード設定更新

### 7-5. DNS 管理コントローラ（admin のみ）

> DNS レコード操作は外部 DNS プロバイダ API 経由（さくらのクラウド DNS 等）

- [x] `Admin\Dns\Index` — DNS レコード一覧（外部 API から取得）
- [x] `Admin\Dns\Store` — レコード追加
- [x] `Admin\Dns\Update` — レコード更新
- [x] `Admin\Dns\Destroy` — レコード削除
- [x] DNS プロバイダ呼び出しを抽象化するインタフェース `App\Lib\Dns\DnsProviderInterface`
- [x] 初期実装：`App\Lib\Dns\SakuraDnsProvider`（さくらのクラウド DNS API）

### 7-6. View

- [x] `resources/views/networks/index.blade.php`
- [x] `resources/views/networks/show.blade.php`
- [x] `resources/views/admin/vps/index.blade.php`（接続状態バッジ）
- [x] `resources/views/admin/vps/show.blade.php`（WireGuard conf 表示 + コピーボタン）
- [x] `resources/views/admin/nodes/index.blade.php`
- [x] `resources/views/admin/dns/index.blade.php`

### 7-7. ルーティング

- [x] ネットワークルートを `routes/web.php` に追加
- [x] VPS・ノード・DNS 管理ルートを追加（`admin` ミドルウェア）

### 7-8. テスト

- [x] Feature: VPS 登録時に `wireguard_ip` と `transit_wireguard_port` が一意に採番されること
- [x] Feature: WireGuard conf が正しいフォーマットで生成されること
- [x] Feature: Proxmox ノード登録時に API トークンが暗号化されて保存されること
- [x] Feature: admin 以外は VPS/ノード管理にアクセスできないこと
- [x] Unit: `VpsGatewayService::generateWireguardConfig()` の出力フォーマット

---

## 完了条件

- VPS を登録すると WireGuard conf が自動生成されて表示されること
- ネットワーク一覧が Proxmox SDN API と DB のデータを突合して表示できること
- `php artisan test --compact` で全テストがパス
- `vendor/bin/pint --dirty --format agent` でスタイル違反なし
