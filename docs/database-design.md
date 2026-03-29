# データベース設計書

## 1. 設計方針

- **DB-Lite原則:** Proxmox/Nomad が持つ情報は管理DBに重複保存しない
- 管理DBには「テナントと外部リソースの紐付け」「認証情報」「設定メタデータ」のみを保存
- VM の状態・リソース情報は常に Proxmox API から取得する

---

## 2. ER図

```
┌──────────┐     ┌──────────────┐     ┌──────────────────┐
│  users   │────<│ tenant_users │>────│    tenants        │
└──────────┘     └──────────────┘     └────────┬─────────┘
                                               │
                        ┌──────────────────────┼──────────────────────────┐
                        │                      │                          │
                 ┌──────┴───────┐   ┌──────────┴──────┐   ┌─────────────┴───────┐
                 │   vm_metas   │   │ database_instances│   │   container_jobs    │
                 └──────┬───────┘   └──────────┬──────┘   └─────────────────────┘
                        │                      │
                        │           ┌──────────┴──────┐
                        │           │ backup_schedules │
                        │           └─────────────────┘


     ┌──────────────────┐
     │    tenants        │
     └────────┬─────────┘
              │
     ┌────────┴─────────┐
     │  s3_credentials   │
     └──────────────────┘


     ┌──────────────────┐       ┌──────────────────┐
     │  proxmox_nodes    │       │  vps_gateways     │
     └──────────────────┘       └──────────────────┘
```

---

## 3. テーブル定義

### 3.1 tenants（テナント）

| カラム | 型 | NULL | デフォルト | 説明 |
|--------|------|------|----------|------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| uuid | CHAR(36) | NO | - | テナント識別用UUID |
| name | VARCHAR(255) | NO | - | テナント名 |
| slug | VARCHAR(100) | NO | - | URLスラッグ (ユニーク) |
| status | ENUM('active','suspended','deleted') | NO | 'active' | テナント状態 |
| vnet_name | VARCHAR(100) | YES | NULL | Proxmox SDN VNet名 (例: tenant-1) |
| vni | INT UNSIGNED | YES | NULL | VXLAN Network Identifier (例: 10001) |
| network_cidr | VARCHAR(18) | YES | NULL | テナントNW CIDR (例: 10.1.0.0/24) |
| nomad_namespace | VARCHAR(100) | YES | NULL | Nomad Namespace名 |
| metadata | JSON | YES | NULL | 拡張メタデータ |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |
| updated_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 更新日時 |

**インデックス:**
- UNIQUE(uuid)
- UNIQUE(slug)
- UNIQUE(vnet_name)
- UNIQUE(vni)
- INDEX(status)

---

### 3.2 users（ユーザ）

| カラム | 型 | NULL | デフォルト | 説明 |
|--------|------|------|----------|------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| name | VARCHAR(255) | NO | - | ユーザ名 |
| email | VARCHAR(255) | NO | - | メールアドレス (ユニーク) |
| password | VARCHAR(255) | NO | - | bcryptハッシュ |
| role | ENUM('admin','tenant_admin','tenant_member') | NO | 'tenant_member' | グローバルロール |
| two_factor_secret | VARCHAR(255) | YES | NULL | TOTP シークレット (暗号化, 任意) |
| two_factor_confirmed_at | TIMESTAMP | YES | NULL | 2FA有効化日時 (NULL = 未設定) |
| remember_token | VARCHAR(100) | YES | NULL | Remember Me トークン |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |
| updated_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 更新日時 |

**インデックス:**
- UNIQUE(email)
- INDEX(role)

---

### 3.3 tenant_users（テナント-ユーザ紐付け）

| カラム | 型 | NULL | デフォルト | 説明 |
|--------|------|------|----------|------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| tenant_id | BIGINT UNSIGNED | NO | - | FK → tenants.id |
| user_id | BIGINT UNSIGNED | NO | - | FK → users.id |
| role | ENUM('admin','member') | NO | 'member' | テナント内ロール |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |

**インデックス:**
- UNIQUE(tenant_id, user_id)
- INDEX(user_id)

---

### 3.4 vm_metas（VMメタデータ）

Proxmox上のVMと管理情報を紐付けるための最小限のメタデータテーブル。

| カラム | 型 | NULL | デフォルト | 説明 |
|--------|------|------|----------|------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| tenant_id | BIGINT UNSIGNED | NO | - | FK → tenants.id |
| proxmox_vmid | INT UNSIGNED | NO | - | Proxmox VM ID |
| proxmox_node | VARCHAR(50) | NO | - | 配置ノード名 (pve1等) |
| purpose | VARCHAR(50) | NO | - | 用途 ('general','dbaas','nomad_worker') |
| label | VARCHAR(255) | YES | NULL | 表示用ラベル |
| shared_ip_address | VARCHAR(45) | YES | NULL | VM Network IP (172.26.27.x, Dual NIC時) |
| provisioning_status | ENUM('pending','cloning','configuring','starting','ready','error') | NO | 'pending' | プロビジョニング状態 |
| provisioning_error | TEXT | YES | NULL | エラー時のメッセージ |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |
| updated_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 更新日時 |
| deleted_at | TIMESTAMP | YES | NULL | 論理削除日時 |

**インデックス:**
- UNIQUE(proxmox_vmid)
- INDEX(tenant_id)
- INDEX(purpose)
- INDEX(deleted_at)

**注記:** VMのCPU・メモリ・ディスク・状態等の情報は Proxmox API から都度取得する。`shared_ip_address` が NULL の場合はテナント VNet のみの Single NIC 構成。

---

### 3.5 database_instances（DBaaSインスタンス）

| カラム | 型 | NULL | デフォルト | 説明 |
|--------|------|------|----------|------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| tenant_id | BIGINT UNSIGNED | NO | - | FK → tenants.id |
| vm_meta_id | BIGINT UNSIGNED | NO | - | FK → vm_metas.id |
| db_type | ENUM('mysql','postgres','redis') | NO | - | DB種別 |
| db_version | VARCHAR(20) | NO | - | DBバージョン (例: '8.4', '17') |
| port | INT UNSIGNED | NO | - | 接続ポート |
| admin_user | VARCHAR(100) | NO | - | 管理ユーザ名 |
| admin_password_encrypted | TEXT | NO | - | 管理パスワード (暗号化) |
| tenant_user | VARCHAR(100) | YES | NULL | テナント用ユーザ名 |
| tenant_password_encrypted | TEXT | YES | NULL | テナント用パスワード (暗号化) |
| backup_encryption_key_encrypted | TEXT | YES | NULL | バックアップ暗号化キー (暗号化) |
| status | ENUM('provisioning','running','stopped','error','upgrading') | NO | 'provisioning' | 状態 |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |
| updated_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 更新日時 |

**インデックス:**
- INDEX(tenant_id)
- INDEX(vm_meta_id)
- INDEX(db_type)
- INDEX(status)

**注記:** パスワード・暗号化キーは Laravel の `encrypt()` で暗号化して保存。

---

### 3.6 backup_schedules（バックアップスケジュール）

| カラム | 型 | NULL | デフォルト | 説明 |
|--------|------|------|----------|------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| database_instance_id | BIGINT UNSIGNED | NO | - | FK → database_instances.id |
| cron_expression | VARCHAR(100) | NO | '0 3 * * *' | cron式 |
| retention_daily | INT UNSIGNED | NO | 7 | 日次バックアップ保持日数 |
| retention_weekly | INT UNSIGNED | NO | 4 | 週次バックアップ保持週数 |
| retention_monthly | INT UNSIGNED | NO | 3 | 月次バックアップ保持月数 |
| last_backup_at | TIMESTAMP | YES | NULL | 最終バックアップ日時 |
| last_backup_status | ENUM('success','failed','running') | YES | NULL | 最終バックアップ結果 |
| last_backup_size_bytes | BIGINT UNSIGNED | YES | NULL | 最終バックアップサイズ |
| is_enabled | BOOLEAN | NO | TRUE | 有効/無効 |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |
| updated_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 更新日時 |

**インデックス:**
- INDEX(database_instance_id)
- INDEX(is_enabled)

---

### 3.7 container_jobs（コンテナジョブ）

Nomad ジョブとテナントの紐付けメタデータ。

| カラム | 型 | NULL | デフォルト | 説明 |
|--------|------|------|----------|------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| tenant_id | BIGINT UNSIGNED | NO | - | FK → tenants.id |
| nomad_job_id | VARCHAR(255) | NO | - | Nomad Job ID |
| name | VARCHAR(255) | NO | - | 表示名 |
| image | VARCHAR(500) | NO | - | コンテナイメージ |
| domain | VARCHAR(255) | YES | NULL | 公開ドメイン名 (Traefik ルーティング用) |
| replicas | INT UNSIGNED | NO | 1 | レプリカ数 |
| cpu_mhz | INT UNSIGNED | NO | - | CPU制限 (MHz) |
| memory_mb | INT UNSIGNED | NO | - | メモリ制限 (MB) |
| port_mappings | JSON | YES | NULL | ポートマッピング |
| env_vars_encrypted | TEXT | YES | NULL | 環境変数 (暗号化) |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |
| updated_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 更新日時 |

**インデックス:**
- UNIQUE(nomad_job_id)
- INDEX(tenant_id)

**注記:** ジョブの状態・アロケーション情報は Nomad API から都度取得する。

---

### 3.8 s3_credentials（S3 プロキシ認証情報）

S3 プロキシが発行する内部認証情報。テナントごとにアクセス可能なバケット・プレフィックスを制限する。

| カラム | 型 | NULL | デフォルト | 説明 |
|--------|------|------|----------|------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| tenant_id | BIGINT UNSIGNED | NO | - | FK → tenants.id |
| access_key | VARCHAR(64) | NO | - | 内部 Access Key (ランダム生成) |
| secret_key_encrypted | TEXT | NO | - | 内部 Secret Key (暗号化保存) |
| allowed_bucket | VARCHAR(255) | NO | - | アクセス許可バケット名 (例: dbaas-backups) |
| allowed_prefix | VARCHAR(255) | NO | - | アクセス許可プレフィックス (例: tenant-1/) |
| description | VARCHAR(255) | YES | NULL | 用途説明 (例: "DBaaS バックアップ用") |
| is_active | BOOLEAN | NO | TRUE | 有効/無効 |
| last_used_at | TIMESTAMP | YES | NULL | 最終使用日時 |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |
| updated_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 更新日時 |

**インデックス:**
- UNIQUE(access_key)
- INDEX(tenant_id)
- INDEX(is_active)

**注記:**
- `access_key` は 20文字のランダム英数字 (例: `MSIR1A2B3C4D5E6F7G8H`)
- `secret_key` は 40文字のランダム英数字、Laravel `encrypt()` で暗号化して保存
- S3 プロキシは `access_key` をキーにして認証情報をキャッシュ参照する
- テナント作成時にデフォルトの認証情報を自動生成する

---

### 3.9 proxmox_nodes（Proxmoxノード設定）

管理パネルが各ノードに接続するための設定情報。

| カラム | 型 | NULL | デフォルト | 説明 |
|--------|------|------|----------|------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| name | VARCHAR(50) | NO | - | ノード名 (pve1等) |
| hostname | VARCHAR(255) | NO | - | 接続先ホスト名/IP |
| api_token_id | VARCHAR(255) | NO | - | API Token ID |
| api_token_secret_encrypted | TEXT | NO | - | API Token Secret (暗号化) |
| snippet_api_url | VARCHAR(255) | NO | - | スニペットAPI URL |
| snippet_api_token_encrypted | TEXT | NO | - | スニペットAPI Token (暗号化) |
| is_active | BOOLEAN | NO | TRUE | 有効/無効 |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |
| updated_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 更新日時 |

**インデックス:**
- UNIQUE(name)

---

### 3.10 vps_gateways（VPS ゲートウェイ）

外部 VPS (Gateway) の管理情報。各 VPS は固有の WireGuard サブネットを持ち、Transit VM との間にトンネルを張る。

| カラム | 型 | NULL | デフォルト | 説明 |
|--------|------|------|----------|------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| name | VARCHAR(100) | NO | - | VPS 識別名 (例: vps-tokyo-1) |
| global_ip | VARCHAR(45) | NO | - | グローバル IP アドレス |
| wireguard_ip | VARCHAR(45) | NO | - | WireGuard IP (10.255.{id}.1) |
| wireguard_port | INT UNSIGNED | NO | 51820 | WireGuard ListenPort |
| wireguard_public_key | VARCHAR(44) | NO | - | WireGuard 公開鍵 |
| transit_wireguard_port | INT UNSIGNED | NO | - | Transit VM 側 ListenPort (51820+N) |
| status | ENUM('active','maintenance','inactive') | NO | 'active' | VPS 状態 |
| purpose | VARCHAR(255) | YES | NULL | 用途 (例: HTTP/HTTPS, Game Server) |
| metadata | JSON | YES | NULL | 拡張メタデータ (nginx設定等) |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |
| updated_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 更新日時 |

**インデックス:**
- UNIQUE(name)
- UNIQUE(global_ip)
- UNIQUE(wireguard_ip)
- INDEX(status)

**注記:**
- `wireguard_ip` は VPS 追加時に `10.255.{id}.1` を自動採番
- Transit VM 側の WireGuard 設定 (wg{N}.conf) は VPS 追加時に自動生成
- VPS 上の WireGuard 秘密鍵は VPS 側で管理（管理パネルには公開鍵のみ保存）

---

### 3.11 dns_zones（DNS ゾーン）

ゾーン単位の DNS 管理情報。ゾーンごとに異なるプロバイダ（Cloudflare / さくら DNS / ローカル CoreDNS）を指定できる。

| カラム | 型 | NULL | デフォルト | 説明 |
|--------|------|------|----------|------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| name | VARCHAR(253) | NO | - | ゾーン名 (例: example.com, infra.example.com, local.override) |
| provider | ENUM('cloudflare','sakura','local') | NO | - | DNS プロバイダ種別 |
| external_zone_id | VARCHAR(255) | YES | NULL | 外部プロバイダのゾーンID (Cloudflare zone_id 等) |
| description | VARCHAR(500) | YES | NULL | ゾーンの説明 |
| is_active | BOOLEAN | NO | true | ゾーンの有効/無効 |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |
| updated_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 更新日時 |

**インデックス:**
- UNIQUE(name)
- INDEX(provider)
- INDEX(is_active)

**注記:**
- `provider=local` の場合、`external_zone_id` は NULL (CoreDNS ゾーンファイルで管理)
- `provider=cloudflare` の場合、`external_zone_id` は Cloudflare の zone ID
- `provider=sakura` の場合、`external_zone_id` はさくら DNS のゾーン ID

---

### 3.12 dns_records（DNS レコード）

各ゾーンに属する個別の DNS レコード。外部プロバイダとの同期状態を管理する。

| カラム | 型 | NULL | デフォルト | 説明 |
|--------|------|------|----------|------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| dns_zone_id | BIGINT UNSIGNED | NO | - | FK → dns_zones.id |
| name | VARCHAR(253) | NO | - | レコード名 (例: registry, *.containers, @) |
| type | ENUM('A','AAAA','CNAME','NS','TXT','MX','SRV') | NO | - | レコードタイプ |
| content | VARCHAR(1000) | NO | - | レコード値 (IP アドレス、ドメイン名等) |
| ttl | INT UNSIGNED | NO | 300 | TTL (秒) |
| priority | INT UNSIGNED | YES | NULL | 優先度 (MX/SRV 用) |
| external_id | VARCHAR(255) | YES | NULL | 外部プロバイダのレコード ID (Cloudflare record_id 等) |
| comment | VARCHAR(500) | YES | NULL | 管理用コメント |
| created_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 作成日時 |
| updated_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 更新日時 |

**インデックス:**
- INDEX(dns_zone_id)
- INDEX(type)
- UNIQUE(dns_zone_id, name, type, content)

**注記:**
- `external_id` は `cloudflare` / `sakura` プロバイダのレコードに対して、外部 API のレコード ID を保持
- `provider=local` のレコードは `external_id` が NULL (ゾーンファイルから直接生成)
- 同一 name + type で複数レコード (ラウンドロビン等) を許容するため、content を含めた複合ユニーク制約

---

## 4. マイグレーション順序

```
1. create_tenants_table
2. create_users_table (Laravel標準を拡張)
3. create_tenant_users_table
4. create_proxmox_nodes_table
5. create_vps_gateways_table
6. create_vm_metas_table
7. create_database_instances_table
8. create_backup_schedules_table
9. create_container_jobs_table
10. create_s3_credentials_table
11. create_dns_zones_table
12. create_dns_records_table
```

---

## 5. Eloquent リレーション概要

```php
// Tenant
hasMany(VmMeta::class)
hasMany(DatabaseInstance::class)
hasMany(ContainerJob::class)
hasMany(S3Credential::class)
belongsToMany(User::class, 'tenant_users')

// User
belongsToMany(Tenant::class, 'tenant_users')

// VmMeta
belongsTo(Tenant::class)
hasOne(DatabaseInstance::class)

// DatabaseInstance
belongsTo(Tenant::class)
belongsTo(VmMeta::class)
hasOne(BackupSchedule::class)

// ContainerJob
belongsTo(Tenant::class)

// S3Credential
belongsTo(Tenant::class)

// BackupSchedule
belongsTo(DatabaseInstance::class)

// DnsZone
hasMany(DnsRecord::class)

// DnsRecord
belongsTo(DnsZone::class)
```
