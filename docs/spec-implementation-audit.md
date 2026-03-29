# 仕様書 vs 実装 差異監査レポート

## 概要

設計書（detailed-design.md, database-design.md, api-design.md, infrastructure-design.md）と実際のコードベースを網羅的に比較し、差異を洗い出した。

---

## 1. ルーティング / API 設計の差異

### 1-1. URL プレフィックスの全面変更 【重要度: 高】

| 機能 | 設計書 | 実装 |
|------|--------|------|
| テナント管理 | `/tenants` | `/admin/tenants` |
| VM 管理 | `/vms` | `/admin/vms` |
| DBaaS 管理 | `/dbaas` | `/admin/dbaas` |
| コンテナ管理 | `/containers` | `/admin/containers` |
| ネットワーク | `/networks` | `/admin/networks` |
| S3 認証情報 | `/tenants/{tenant}/s3-credentials` | `/admin/tenants/{tenant}/s3-credentials` |

**影響:** 設計書ではマルチテナント（tenant_admin/tenant_member がアクセス可能）を想定しているが、実装は全ルートが `admin` ミドルウェア配下で admin 専用になっている。

### 1-2. ミドルウェア構成の差異

| 項目 | 設計書 | 実装 |
|------|--------|------|
| 基本グループ | `auth` → `2fa.optional` | `auth` + `verified` |
| 管理者制限 | `/admin/*` のみ `admin` MW | 全機能ルートに `admin` MW |
| `2fa.optional` | あり | なし |
| `verified` | なし | あり |

### 1-3. 認証ルート — 完全に Fortify 委譲 【重要度: 中】

設計書では 8 つの独自認証ルート（login, logout, 2FA verify/setup/confirm/disable）を定義しているが、実装は Laravel Fortify に完全委譲。2FA 関連のカスタムコントローラは存在しない。

### 1-4. 監視 (Monitoring) ルート — 未実装 【重要度: 中】

設計書の `GET /monitoring`, `GET /monitoring/grafana-url` が未実装。関連する Controller/Service もなし。

### 1-5. DNS 管理ルート — 大幅簡略化 【重要度: 高】

| 設計書 (10 ルート) | 実装 (4 ルート) |
|-------------------|----------------|
| ゾーン CRUD 4 ルート | なし |
| レコード CRUD 4 ルート（ゾーン配下） | フラットな CRUD 3 ルート |
| 同期・リロード 2 ルート | なし |

設計書のゾーン/レコード階層構造が、フラットな 4 ルート（index/store/update/destroy）に簡略化されている。

### 1-6. Proxmox ノード管理 — パスと Controller 名の差異

| 項目 | 設計書 | 実装 |
|------|--------|------|
| URL | `/admin/nodes` | `/admin/proxmox-clusters` |
| Controller | `Admin\Node\*` | `ProxmoxNode\*` |
| ルート名 | `admin.nodes.*` | `proxmox-clusters.*` |
| ルート数 | 3 ルート | 9 ルート（create/edit/destroy/activate/deactivate/test-snippet-api 追加） |

### 1-7. VPS ゲートウェイ管理 — パスの差異

| 項目 | 設計書 | 実装 |
|------|--------|------|
| URL | `/admin/vps` | `/admin/vps-gateways` |
| Controller | `Admin\Vps\*` | `VpsGateway\*` |

### 1-8. その他のルーティング差異

- **ダッシュボード:** 設計書 `GET /` → 実装 `GET /dashboard`（`/` はリダイレクト）
- **ルートパラメータ:** 設計書 `{id}` → 実装ではモデルバインディング名（`{database}`, `{container}` 等）
- **S3 Rotate HTTP メソッド:** 設計書 `POST` → 実装 `PUT`
- **ルート名プレフィックス:** 設計書 `admin.users.*` → 実装 `users.*`（admin. なし）

---

## 2. データベーススキーマの差異

### 2-1. `s3_credentials` テーブル — 大幅乖離 【重要度: 高】

| カラム | 設計書 | 実装（マイグレーション適用後） |
|--------|--------|-------------------------------|
| `secret_key_encrypted` | `TEXT, NOT NULL` | **削除済み** |
| `secret_key_plain` | なし | `TEXT, nullable`（追加） |
| `name` | なし | `VARCHAR(255), nullable`（追加） |

設計書では `secret_key_encrypted`（Laravel `encrypt()` で暗号化）を想定しているが、実装では暗号化カラムを削除し `secret_key_plain`（平文保存）に変更されている。

### 2-2. `vm_metas.purpose` — NULL 制約の差異 【重要度: 低】

| 項目 | 設計書 | 実装 |
|------|--------|------|
| `purpose` | `NOT NULL` | `nullable()` |

### 2-3. `users` テーブル — Fortify 由来の差異 【重要度: 低】

- `email_verified_at`: 設計書に記載なし → 実装にあり（Laravel デフォルト）
- `two_factor_secret`: 設計書 `VARCHAR(255)` → 実装 `TEXT`（Fortify 生成）
- `two_factor_recovery_codes`: 設計書に記載なし → 実装にあり（Fortify 生成）

### 2-4. `dns_zones` / `dns_records` テーブル — 未実装 【重要度: 高】

設計書で定義済みの 2 テーブルに対するマイグレーションが存在しない（Phase 13 で実装予定）。

---

## 3. サービス層の差異

### 3-1. TenantService — テナント作成フロー 【重要度: 高】

| # | 差異 | 設計書 | 実装 |
|---|------|--------|------|
| a | Nomad Namespace 未作成 | `PUT /v1/namespace/tenant-{id}` で自動作成 | Nomad 呼び出しなし、slug をそのまま設定 |
| b | VNet 名形式 | `tenant-{id}`（ハイフン付き） | `tenant{id}`（ハイフンなし） |
| c | S3 prefix 形式 | `tenant-{id}/` | `{slug}/`（slug ベース） |
| d | Subnet の gateway | `gateway: "10.{id}.0.1"` を含む | gateway パラメータなし |
| e | 引数型 | `TenantData $data`（Data オブジェクト） | `array $params`（配列） |
| f | ProxmoxSdnService | 設計書で独立サービス | ProxmoxApi を直接使用 |

### 3-2. VmService — VM プロビジョニング 【重要度: 高】

| # | 差異 | 設計書 | 実装 |
|---|------|--------|------|
| a | NIC 設定 (net0/net1) | `net0: virtio,bridge=vnet_{tenant}` + `net1` (shared_ip 時) | **NIC 設定なし** |
| b | network-config.yaml | CloudInitBuilder が user-data + network-config + meta-data を生成 | user-data (hostname のみ) だけ生成 |
| c | Cloud-init 内容 | パッケージ、ユーザ、SSH 鍵、DNS、S3 設定 | hostname と fqdn のみ |
| d | shared_ip_address | eth1 に 172.26.27.x/24 を追加 | カラムは存在するが NIC 設定に未反映 |
| e | CloudInitBuilder | 専用クラス | VmService 内の private メソッド |
| f | IP アドレス設定 | ip_address/gateway を cloud-init で設定 | IP/gateway パラメータを受け取らない |
| g | snippet 保存 | user_data + network_config を SnippetAPI に POST | user_data のみ |

### 3-3. DbaasService — DBaaS プロビジョニング 【重要度: 高】

| # | 差異 | 設計書 | 実装 |
|---|------|--------|------|
| a | DB 設定テンプレート | MysqlTemplate/PostgresTemplate/RedisTemplate で my.cnf 等を生成 | テンプレートクラス・生成ロジックなし |
| b | テナントユーザー | admin + tenant の 2 ユーザーを作成 | admin のみ |
| c | バックアップ cron | cloud-init に cron 設定を注入 | cloud-init は hostname のみ |
| d | retention デフォルト値 | `daily=7, weekly=4, monthly=3` | retention パラメータを渡していない |
| e | バージョンアップデート | backup → 停止 → update → 起動 → ヘルスチェック | メソッドなし |

### 3-4. ContainerService — CaaS 【重要度: 中】

| # | 差異 | 設計書 | 実装 |
|---|------|--------|------|
| a | レジストリ URL | `registry.infra.example.com/${image}` でプレフィックス付与 | image をそのまま使用 |
| b | Nomad ネットワーク | DynamicPorts 想定 | ReservedPorts を使用 |
| c | Traefik entrypoints | `web` | `websecure` |
| d | Harbor テナントプロジェクト | テナントごとに `tenant-{id}` プロジェクト作成 | Harbor 関連コードなし |
| e | Consul provider | `provider = "consul"` でサービス登録 | provider フィールドなし |

### 3-5. BackupService — バックアップ方式の根本的差異 【重要度: 高】

| 項目 | 設計書 | 実装 |
|------|--------|------|
| 実行場所 | DBaaS VM 内の cron | 管理パネル側の BackupService |
| dump 方式 | mysqldump/pg_dump → gzip → gpg(AES-256) | HTTP PUT で JSON 送信（実際の dump なし） |
| S3 への送信 | VM 内 aws cli → S3 プロキシ | Laravel から直接 HTTP PUT |

### 3-6. DnsManagementService — DNS マルチプロバイダ未対応 【重要度: 高】

| # | 差異 | 設計書 | 実装 |
|---|------|--------|------|
| a | クラス名 | `DnsService` | `DnsManagementService` |
| b | ゾーン管理 | `dns_zones` テーブル + `DnsZoneRepository` | ゾーン概念なし |
| c | プロバイダ切替 | `DnsProviderFactory` で cloudflare/sakura/local を動的切替 | `SakuraDnsProvider` 固定バインド |
| d | DB 保存 | DB にレコード保存 → 外部 API に反映 | DB 保存なし、プロバイダに直接委譲 |
| e | LocalDnsProvider | CoreDNS ゾーンファイル生成 + Corefile 動的生成 + SIGHUP | 未実装 |
| f | CloudflareDnsProvider | Cloudflare API 実装 | 未実装 |
| g | Interface シグネチャ | `listRecords(string $zoneName)` | `listRecords()`（ゾーン名引数なし） |

### 3-7. 監視サービス — 未実装 【重要度: 中】

設計書で言及されている OTel Collector + Grafana Cloud 連携、および監視ダッシュボード機能が未実装。

### 3-8. VpsGatewayService — 設計書に未記載 【重要度: 低】

実装には `VpsGatewayService`（register/sync/generateWireguardConfig/destroy）が存在するが、詳細設計書に Service 設計の記述がない。

### 3-9. NetworkService — 設計書に未記載 【重要度: 低】

設計書ではネットワーク管理は TenantService 内でインラインだが、実装では独立した `NetworkService` として分離。また `resolveSdnZone()` が TenantService と NetworkService に重複実装されている。

---

## 4. AppServiceProvider — サービスバインディング

| 項目 | 設計書 | 実装 |
|------|--------|------|
| DNS プロバイダ | `DnsProviderFactory` で動的切替 | `DnsProviderInterface` → `SakuraDnsProvider` 固定 |
| NomadService | 独立サービスクラス | `NomadApi` を直接 singleton |
| ProxmoxSdnService | 独立サービスクラス | `ProxmoxApi` を直接 singleton |

---

## 5. 対応方針の提案

### 設計書を実装に合わせて更新すべき項目（実装が正しい）

1. **URL プレフィックス(`/admin/`):** 現時点で admin 専用は妥当。マルチテナント対応時に再設計
2. **Fortify 委譲:** 認証は Fortify のまま維持し、設計書を更新
3. **Proxmox ノード / VPS ゲートウェイのパス名:** 実装の方が分かりやすい
4. **ルートパラメータ名:** Laravel 慣習（モデルバインディング名）に準拠しており実装が正しい
5. **NetworkService / VpsGatewayService の分離:** 責務分離は良い設計。設計書に追記
6. **ダッシュボード `/dashboard`:** 実装の方が適切

### 実装を設計書に合わせて修正すべき項目（設計が正しい）

1. **NIC 設定 (net0/net1):** VM がネットワークに接続できない致命的な欠落
2. **Cloud-init の充実:** network-config, パッケージ, ユーザ, SSH 鍵が必要
3. **DBaaS テンプレート:** my.cnf/postgresql.conf 等の設定生成が必要
4. **Nomad Namespace 自動作成:** テナント作成時に必須
5. **DNS マルチプロバイダ:** ゾーン管理 + Provider 切替が Phase 13 で必要
6. **dns_zones / dns_records マイグレーション:** Phase 13 で作成

### 設計書・実装の両方を更新して擦り合わせるべき項目

1. **s3_credentials の暗号化方針:** `secret_key_plain` vs `secret_key_encrypted` — セキュリティ方針の再確認
2. **バックアップ方式:** VM 内 cron vs 管理パネル — アーキテクチャ方針の再確認
3. **Subnet gateway パラメータ:** 設計書のフローに含めるか判断
4. **VNet 名形式 / S3 prefix 形式:** 統一ルールの策定
