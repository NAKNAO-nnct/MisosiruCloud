# 実装計画書 - おうちクラウドサービス管理パネル

## プロジェクト概要

Proxmox VE 3ノードクラスタ上のマルチテナント型クラウド管理パネル。  
Laravel + Livewire + Flux UI によるサーバサイドレンダリングベース。外部公開 API なし。

## 現在の実装状況（2026-03-29 反映）

| 領域 | 状態 | 備考 |
|------|------|------|
| Laravel インストール | ✅ 完了 | v13、PHP 8.5 |
| 認証基盤 (Fortify) | ✅ 完了 | Fortify ルートでログイン/2FA 利用 |
| ユーザモデル | ✅ 完了 | role / 権限制御込み |
| Proxmox ライブラリ | ✅ 完了 | `App\\Lib\\Proxmox` 一式 + テストあり |
| テナント管理 | ✅ 完了 | CRUD + S3 認証情報管理 + テストあり |
| VM 管理 | ✅ 完了 | CRUD/操作/内部API/View/テストあり |
| DBaaS | ⚠️ ほぼ実装済み | Service/Controller/View/Route/スケジューラ実装済み。残りは一部テスト |
| CaaS (Nomad 連携) | ⚠️ 部分実装 | `Lib\\Nomad` と `ContainerService` 実装済み、管理画面は Index/Create/Store まで実装 |
| ネットワーク管理 | ✅ 完了 | Network 一覧/作成/詳細/削除、Proxmox SDN 連携、テスト実装済み |
| VPS ゲートウェイ管理 | ✅ 完了 | VPS 登録/詳細/更新/削除/同期、WireGuard conf 生成、テスト実装済み |
| 監視・可観測性 | ❌ 未着手 | Monitoring 機能未実装 |
| スニペットサイドカーAPI | ✅ 完了 | FastAPI sidecar、Laravel `Lib\\Snippet`、ローカル compose、テスト実装済み |
| S3 プロキシサーバ (Go) | ❌ 未着手 | `s3-proxy/` 未作成 |

---

## フェーズ一覧

| フェーズ | ファイル | 内容 | 依存 |
|---------|---------|------|------|
| Phase 1 | [phase-1-foundation.md](phase-1-foundation.md) | 基盤：DB マイグレーション・モデル・認証・ルーティング・UI レイアウト | なし |
| Phase 2 | [phase-2-proxmox-lib.md](phase-2-proxmox-lib.md) | Lib/Proxmox：自作 HTTP クライアント・全リソース操作 | Phase 1 |
| Phase 3 | [phase-3-tenant.md](phase-3-tenant.md) | テナント管理：CRUD・SDN VNet 作成・S3 認証情報自動発行 | Phase 1, 2 |
| Phase 4 | [phase-4-vm.md](phase-4-vm.md) | VM 管理：一覧・詳細・起動/停止・Cloud-init デプロイ・VNC コンソール | Phase 1, 2, 3 |
| Phase 5 | [phase-5-dbaas.md](phase-5-dbaas.md) | DBaaS：DB VM プロビジョニング・バックアップ・バージョンアップグレード | Phase 4 |
| Phase 6 | [phase-6-caas.md](phase-6-caas.md) | CaaS：Nomad ライブラリ・コンテナデプロイ・Traefik 連携 | Phase 3, 4 |
| Phase 7 | [phase-7-network-vps.md](phase-7-network-vps.md) | ネットワーク管理・VPS ゲートウェイ管理 | Phase 2, 3 |
| Phase 8 | [phase-8-monitoring.md](phase-8-monitoring.md) | 監視・可観測性：Grafana Cloud 埋め込み・OTel 連携 | Phase 1 |
| Phase 9 | [phase-9-sidecar-api.md](phase-9-sidecar-api.md) | スニペットサイドカー API (Python / FastAPI)・Snippet クライアント | Phase 2 |
| Phase 10 | [phase-10-s3-proxy.md](phase-10-s3-proxy.md) | S3 プロキシサーバ (Go)：AWS Sig V4 検証・テナント隔離・外部 S3 転送 | Phase 1, 3 |
| Phase 11 | [phase-11-app-layer.md](phase-11-app-layer.md) | アプリケーションレイヤー基盤：Data/Repository/非同期ジョブ (Queue) | Phase 1〜5 |
| Phase 12 | [phase-12-補完.md](phase-12-補完.md) | 既存フェーズ補完：Admin ユーザ管理・Nomad 拡張・Snippet API 修正 | Phase 6, 9 |
| Phase 13 | [phase-13-infra-base.md](phase-13-infra-base.md) | インフラ基盤：Docker Compose (prod)・CoreDNS・Harbor・SSL/TLS | Phase 10 |
| Phase 14 | [phase-14-packer.md](phase-14-packer.md) | Packer VM テンプレート：ベース・DBaaS・Nomad Worker テンプレート自動生成 | Phase 13 |
| Phase 15 | [phase-15-transit-vm.md](phase-15-transit-vm.md) | Transit VM & ネットワーク基盤：VRRP・WireGuard・PBR | Phase 7 |

---

## 優先実装順序（進捗反映後）

```
Phase 1 ✅ → Phase 2 ✅ → Phase 3 ✅ → Phase 4 ✅ → Phase 5 ⚠️
                                           ↓
                                        Phase 6 ⚠️ → Phase 12 ❌ (Nomad 拡張・Admin User)
                               Phase 7 ✅ ────┘          ↓
                                  ↓                   Phase 12 ❌ (Snippet API 修正)
Phase 8 ❌ (Phase 1 後いつでも可)  ↓
Phase 9 ✅ ─────────────────────────┘
Phase 10 ❌ (Phase 3 後) → Phase 13 ❌ (インフラ基盤) → Phase 14 ❌ (Packer)
Phase 11 ❌ (Phase 1〜5 実装後のリファクタリング)
Phase 15 ❌ (Phase 7 後、Transit VM 構築)
```

---

## フェーズ別進捗サマリ（2026-03-29）

| フェーズ | 判定 | 実装状況（要約） |
|---------|------|------------------|
| Phase 1 | ✅ 完了 | マイグレーション/モデル/Enum/認証/ダッシュボード/権限テストまで実装 |
| Phase 2 | ✅ 完了 | Proxmox Client・Resources・DataObjects・例外・バインディング・テスト実装 |
| Phase 3 | ✅ 完了 | Tenant/S3 の Service/Controller/View/Request/Feature テスト実装 |
| Phase 4 | ✅ 完了 | VM の Service/Controller/View/Internal API/Request/Unit+Feature テスト実装 |
| Phase 5 | ⚠️ ほぼ実装済み | DBaaS UI/API/Route とバックアップスケジューラ実装済み。残りは Unit テスト中心 |
| Phase 6 | ⚠️ 部分実装 | Nomad ライブラリ・ContainerService・Deploy系画面/ルート実装済み。詳細操作系は未実装 |
| Phase 7 | ✅ 完了 | Network/VPS/DNS 管理（Service/Controller/View/Route/Test）実装済み |
| Phase 8 | ❌ 未着手 | MonitoringService/画面/OTel ジョブ定義未着手 |
| Phase 9 | ✅ 完了 | Python Sidecar API、`Lib\\Snippet`、`VmService` 連携、ローカル compose、Unit/Feature/Python テスト実装済み |
| Phase 10 | ❌ 未着手 | Go S3 プロキシサーバ・`Lib\\S3Proxy\\CredentialManager` 未着手 |
| Phase 11 | ❌ 未着手 | Data/Repository レイヤー・非同期ジョブ (ProvisionVmJob 等)・Queue 設定 未着手 |
| Phase 12 | ❌ 未着手 | Admin\\User CRUD・Nomad Allocation/Node/Quota・Snippet API 仕様修正 未着手 |
| Phase 13 | ❌ 未着手 | compose.prod.yaml・CoreDNS・Harbor・Let's Encrypt 証明書 未着手 |
| Phase 14 | ❌ 未着手 | Packer テンプレート (base-ubuntu/dbaas-*/nomad-worker) 未着手 |
| Phase 15 | ❌ 未着手 | Transit VM (VRRP/WireGuard/PBR) 構築 未着手 |

---

## 共通チェックポイント（全フェーズ共通）

各フェーズ完了時に以下を確認する：

- [ ] `vendor/bin/pint --dirty --format agent` を実行してコードスタイルを統一
- [ ] 関連するテスト (`php artisan test --compact`) がすべてパスしていること
- [ ] マイグレーションが正常に実行できること (`php artisan migrate:fresh --seed`)
- [ ] ルートが意図通りに登録されていること (`php artisan route:list`)

---

## 参考ドキュメント

- [detailed-design.md](../detailed-design.md) — ディレクトリ構成・Lib設計・フロー図
- [database-design.md](../database-design.md) — テーブル定義・ER図
- [api-design.md](../api-design.md) — Web ルート一覧・画面一覧
- [infrastructure-design.md](../infrastructure-design.md) — インフラ構成
- [やりたいこと.md](../やりたいこと.md) — 機能要件
