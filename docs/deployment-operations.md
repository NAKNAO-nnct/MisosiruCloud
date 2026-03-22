# デプロイ・運用設計書

## 1. 実装ロードマップ

### Phase 0: 基盤準備（前提条件の整備）

| # | タスク | 詳細 | 成果物 |
|---|--------|------|--------|
| 0-1 | Proxmox クラスタ構成 | 3ノードクラスタの構築・確認 | 動作するクラスタ |
| 0-2 | ネットワーク基盤 | vmbr0(管理)/vmbr1(VM) の設定 | ブリッジ設定 |
| 0-3 | Cloud-init テンプレートVM | Ubuntu 24.04 cloud image をテンプレート化 | テンプレートVM |
| 0-4 | 外部 S3 バケット準備 | AWS S3 / Wasabi でバケット作成・IAM設定 | S3 バケット・認証情報 |
| 0-5 | VPS-WireGuard接続確認 | 各 VPS ↔ Transit VM 間のVPN確認 (VPSごとに個別トンネル) | WireGuard設定 |
| 0-6 | Docker 環境整備 | mgmt-docker VM に Docker + Docker Compose インストール | Docker 基盤 |
| 0-7 | 各ノード Docker 準備 | 各 Proxmox ノードに Docker インストール (スニペットAPI用) | Docker on Proxmox |

### Phase 1: 管理パネル MVP

| # | タスク | 詳細 | 成果物 |
|---|--------|------|--------|
| 1-1 | Docker Compose 構成 | mgmt-docker VM に docker-compose.yml 作成 | Compose ファイル |
| 1-2 | mgmt-app コンテナ構築 | Laravel 12 + PHP-FPM + Nginx の Dockerfile 作成 | mgmt-app イメージ |
| 1-3 | mgmt-db 起動 | MySQL 8.4 コンテナ起動 | DB コンテナ |
| 1-4 | 内部 DNS (CoreDNS) 起動 | Corefile 作成 → コンテナ起動 (キャッシュ/フォワーダ) | DNS コンテナ |
| 1-5 | S3 プロキシ構築 | Go S3 プロキシ Dockerfile 作成 + ビルド | s3-proxy イメージ |
| 1-6 | 認証機能 | ログイン + 2FA (TOTP, 任意) | 認証画面 |
| 1-7 | Lib/Proxmox 実装 | Proxmox API クライアント | ライブラリ |
| 1-8 | テナント管理 CRUD | テナント作成・一覧・詳細 + S3 認証情報自動発行 | テナント画面 |
| 1-9 | ノード状態表示 | ダッシュボードにノード状態表示 | ダッシュボード |
| 1-10 | DB マイグレーション | 全テーブル作成 (s3_credentials, vps_gateways 含む) | マイグレーションファイル |
| 1-11 | スニペットAPI コンテナ化 | FastAPI の Dockerfile + 各ノードにデプロイ | snippet-api コンテナ |
| 1-12 | Queue Worker 設定 | database ドライバ + jobs テーブル + mgmt-queue コンテナ (php artisan queue:work) | キューワーカー |

### Phase 2: VM管理

| # | タスク | 詳細 | 成果物 |
|---|--------|------|--------|
| 2-1 | Cloud-init テンプレート実装 | CloudInitBuilder + 各テンプレート | Cloud-initヘルパー |
| 2-2 | VM作成フロー | フォーム → スニペット → Proxmox API → VM起動 | VM作成画面 |
| 2-3 | VM操作 (起動/停止/削除) | 各操作ボタン + 処理実装 | VM操作UI |
| 2-4 | VM一覧・詳細 | Proxmox APIから情報取得して表示 | VM一覧画面 |
| 2-5 | VNCコンソール | noVNC 埋め込み | コンソール画面 |

### Phase 3: DBaaS

| # | タスク | 詳細 | 成果物 |
|---|--------|------|--------|
| 3-1 | MySQL テンプレート | Cloud-init + MySQL自動構成 | MySQLテンプレート |
| 3-2 | PostgreSQL テンプレート | Cloud-init + PostgreSQL自動構成 | PostgreSQLテンプレート |
| 3-3 | Redis テンプレート | Cloud-init + Redis自動構成 | Redisテンプレート |
| 3-4 | DBaaS管理画面 | 作成・一覧・詳細・接続情報 | DBaaS画面 |
| 3-5 | バックアップ機能 | VM内dump + S3プロキシ経由でアップロード | バックアップスクリプト+Cloud-init設定 |
| 3-6 | リストア機能 | S3プロキシから取得→VMに転送→復号・リストア | リストア処理 |
| 3-7 | バージョンアップデート | in-placeアップグレード | アップデート処理 |

### Phase 4: CaaS (Nomad)

| # | タスク | 詳細 | 成果物 |
|---|--------|------|--------|
| 4-1 | Nomad クラスタ構築 | Server 3台 + Worker VM | Nomadクラスタ |
| 4-2 | Consul クラスタ構築 | サービスディスカバリ | Consulクラスタ |
| 4-3 | Traefik デプロイ | Nomad system job、Consul Catalog連携 | Traefik Ingress |
| 4-4 | Harbor デプロイ | コンテナレジストリ構築(mgmt-docker VM の Docker Compose) | Harbor |
| 4-4 | Lib/Nomad 実装 | Nomad API クライアント | ライブラリ |
| 4-5 | コンテナデプロイ画面 | イメージ指定・ドメイン指定 → Job生成(Traefik tags付き) → 登録 | デプロイ画面 |
| 4-6 | テナント隔離 | Namespace + CNI bridge + EVPN/VXLAN | ネットワーク隔離 |
| 4-7 | コンテナ管理画面 | 一覧・詳細・ログ・スケール | 管理画面 |

### Phase 5: 監視・可観測性

| # | タスク | 詳細 | 成果物 |
|---|--------|------|--------|
| 5-1 | OTel Collector Agent | 各ノードにデプロイ | Agent設定 |
| 5-2 | OTel Collector Gateway | 集約用Collector | Gateway設定 |
| 5-3 | node_exporter 導入 | 全VMに配置 | メトリクス収集 |
| 5-4 | cAdvisor 導入 | Nomad system job | コンテナメトリクス |
| 5-5 | Grafana Cloud接続 | メトリクス/ログ送信 | ダッシュボード |
| 5-6 | ログ収集基盤 | Alloy/Fluent Bit → Loki | ログパイプライン |
| 5-7 | ログアーカイブ | 古いログ → 外部S3 圧縮保存 | アーカイブ処理 |
| 5-8 | Grafana 埋め込み | Laravel にダッシュボード埋め込み | 監視画面 |

### Phase 6: 外部アクセス・運用整備

| # | タスク | 詳細 | 成果物 |
|---|--------|------|--------|
| 6-1 | VPS リバースプロキシ | 各 VPS に nginx ドメインベースルーティング、VPS管理API配置 | VPS設定 |
| 6-2 | SSL証明書 (Let's Encrypt DNS-01) | Cloudflare API (`*.containers.example.com` 等) + さくら DNS API (`*.infra.example.com`) | 証明書管理 |
| 6-3 | ファイアウォール整備 | nftables ルール整備 | FWルール |
| 6-4 | 監査ログ | 全操作の記録 | 監査機能 |
| 6-5 | アラート設定 | Grafana Cloud アラート | アラートルール |

---

## 2. 管理VM デプロイ手順

### 2.1 mgmt-docker VM 構築

```bash
# 1. VM作成 (Cloud-init: base-ubuntu テンプレート)
# IP: 管理NW (172.26.26.10/24)

# 2. Docker + Docker Compose インストール
curl -fsSL https://get.docker.com | sh
apt install -y docker-compose-plugin

# 3. プロジェクトクローン
cd /opt
git clone <repository> misosiru-cloud
cd misosiru-cloud

# 4. 環境変数設定
cp .env.example .env
# DB_PASSWORD, S3_ACCESS_KEY, S3_SECRET_KEY 等を設定

# 5. Docker Compose 起動
docker compose up -d

# 6. Laravel 初期設定
docker compose exec mgmt-app php artisan key:generate
docker compose exec mgmt-app php artisan migrate --force

# 7. 動作確認
curl -k https://172.26.26.10/login
```

### 2.2 snippet-api (各Proxmoxノード/Docker)

```bash
# 1. 各Proxmoxノードに Docker をインストール (未インストールの場合)
curl -fsSL https://get.docker.com | sh

# 2. snippet-api コンテナイメージのビルドまたは取得
# (mgmt-docker VM のレジストリから pull、またはローカルビルド)
cd /opt/misosiru-cloud/snippet-api
docker build -t misosiru-cloud/snippet-api:latest .

# 3. コンテナ起動
docker run -d \
  --name snippet-api \
  --restart always \
  -p 172.26.26.11:8100:8100 \
  -v /var/lib/vz/snippets:/app/snippets \
  -e SNIPPET_DIR=/app/snippets \
  -e API_TOKEN=${SNIPPET_API_TOKEN} \
  -e BIND_HOST=0.0.0.0 \
  -e BIND_PORT=8100 \
  misosiru-cloud/snippet-api:latest

# 4. 動作確認
curl http://172.26.26.11:8100/health
```

### 2.3 S3 プロキシ (Docker Compose 内)

S3 プロキシは mgmt-docker VM の Docker Compose に含まれるため、`docker compose up -d` で自動起動する。

```bash
# 動作確認
curl http://172.26.26.10:9000/  # S3互換エンドポイント

# テスト (aws cli)
aws --endpoint-url http://s3.infra.example.com:9000 \
    s3 ls s3://dbaas-backups/ \
    --access-key INTERNAL_ACCESS_KEY \
    --secret-key INTERNAL_SECRET_KEY
```

### 2.4 内部 DNS (Docker Compose 内)

CoreDNS は mgmt-docker VM の Docker Compose に含まれるため、`docker compose up -d` で自動起動する。

```bash
# 動作確認 (内部インフラドメイン — 外部 DNS 経由で解決)
dig @172.26.26.10 s3.infra.example.com
# Expected: 172.26.26.10

dig @172.26.26.10 registry.infra.example.com
# Expected: 172.26.26.10

# 外部ドメインのフォワード確認
dig @172.26.26.10 google.com
# Expected: フォワーダ経由で正常解決
```

---

## 3. バックアップ運用

### 3.1 DBaaS バックアップ (S3 プロキシ経由)

テナント VM は内部 S3 プロキシ経由でバックアップをアップロードする。
**外部 S3 の認証情報はテナント VM に配布しない。**

#### Step 1: DBaaS VM 内のバックアップ + S3 プロキシアップロード (Cloud-init でデプロイ)

```bash
#!/bin/bash
# /opt/backup/db-backup.sh (DBaaS VM 内で cron 実行)

set -euo pipefail

DB_TYPE="${DB_TYPE}"           # mysql / postgres / redis
GPG_PASSPHRASE="${GPG_PASSPHRASE}"
S3_PROXY_ENDPOINT="${S3_PROXY_ENDPOINT}"   # http://s3.infra.example.com:9000
S3_ACCESS_KEY="${S3_ACCESS_KEY}"            # S3プロキシ発行の内部キー
S3_SECRET_KEY="${S3_SECRET_KEY}"            # S3プロキシ発行の内部シークレット
S3_BUCKET="${S3_BUCKET}"                   # dbaas-backups
DB_ID="${DB_ID}"                           # database_instance.id

DATE=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR="/tmp/backup"
mkdir -p "$BACKUP_DIR"

case "$DB_TYPE" in
  mysql)
    mysqldump --single-transaction --all-databases | \
      gzip | \
      gpg --batch --yes --symmetric --cipher-algo AES256 \
          --passphrase "$GPG_PASSPHRASE" \
          -o "$BACKUP_DIR/${DATE}.sql.gz.gpg"
    ;;
  postgres)
    pg_dumpall | \
      gzip | \
      gpg --batch --yes --symmetric --cipher-algo AES256 \
          --passphrase "$GPG_PASSPHRASE" \
          -o "$BACKUP_DIR/${DATE}.sql.gz.gpg"
    ;;
  redis)
    redis-cli BGSAVE
    sleep 5
    cp /var/lib/redis/dump.rdb "$BACKUP_DIR/${DATE}.rdb"
    gzip "$BACKUP_DIR/${DATE}.rdb"
    gpg --batch --yes --symmetric --cipher-algo AES256 \
        --passphrase "$GPG_PASSPHRASE" \
        -o "$BACKUP_DIR/${DATE}.rdb.gz.gpg" \
        "$BACKUP_DIR/${DATE}.rdb.gz"
    rm -f "$BACKUP_DIR/${DATE}.rdb.gz"
    ;;
esac

# S3 プロキシにアップロード (aws cli)
aws --endpoint-url "$S3_PROXY_ENDPOINT" \
    s3 cp "$BACKUP_DIR/${DATE}"*.gpg \
    "s3://${S3_BUCKET}/${DB_ID}/${DATE}.gpg" \
    --no-verify-ssl

# ローカルの一時ファイルを削除
rm -f "$BACKUP_DIR/${DATE}"*.gpg

echo "Backup uploaded to S3 proxy: ${S3_BUCKET}/${DB_ID}/${DATE}.gpg"
```

> **ポイント:**
> - VM は S3プロキシの内部認証情報のみを持つ (外部 S3 の認証情報は不要)
> - S3 プロキシがテナントプレフィックスを自動付与 → 外部 S3 では `/{tenant_id}/{db_id}/` 以下に保存
> - aws cli は Cloud-init で自動インストール、認証情報は Cloud-init の環境変数で設定

#### Step 2: 管理パネルによるバックアップ状態管理

```
1. mgmt-app の BackupService (Laravel スケジューラ) が定期的に S3 プロキシの ListObjects を実行
2. 各 DBaaS インスタンスの最新バックアップの存在を確認
3. backup_schedules テーブルの last_backup_at / last_backup_status を更新
4. 保持ポリシーに従い古い S3 オブジェクトを削除 (S3 プロキシ経由で DeleteObject)
```

### 3.2 管理DB バックアップ

```bash
# 管理DBのバックアップ (mgmt-docker VM 内の Docker Compose 環境から実行)
# Laravel スケジューラ: 毎日 02:00

# mgmt-db コンテナで dump を実行
docker compose exec mgmt-db mysqldump --single-transaction misosiru_cloud | gzip \
  > /tmp/mgmt-backup-$(date +%Y%m%d).sql.gz

# S3 プロキシ経由で外部 S3 にアップロード
aws --endpoint-url http://s3.infra.example.com:9000 \
  s3 cp /tmp/mgmt-backup-$(date +%Y%m%d).sql.gz \
  s3://mgmt-backups/$(date +%Y%m%d).sql.gz
rm /tmp/mgmt-backup-*.sql.gz
```

### 3.3 バックアップ保持ポリシー

```
日次: 7世代保持 (毎日 03:00 実行)
週次: 4世代保持 (日曜の日次を週次としてマーク)
月次: 3世代保持 (1日の日次を月次としてマーク)
```

---

## 4. 監視アラートルール

### 4.1 インフラアラート

| アラート名 | 条件 | 重要度 | 通知先 |
|-----------|------|--------|--------|
| NodeDown | node_exporter が 5分間無応答 | Critical | Slack / Email |
| HighCpuUsage | CPU使用率 > 90% が 10分間継続 | Warning | Slack |
| HighMemoryUsage | メモリ使用率 > 85% が 10分間継続 | Warning | Slack |
| DiskSpaceLow | ディスク使用率 > 80% | Warning | Slack |
| DiskSpaceCritical | ディスク使用率 > 95% | Critical | Slack / Email |
| ProxmoxNodeUnhealthy | Proxmoxクラスタノード離脱 | Critical | Slack / Email |

### 4.2 DBaaS アラート

| アラート名 | 条件 | 重要度 | 通知先 |
|-----------|------|--------|--------|
| DatabaseDown | DBプロセスが停止 | Critical | Slack / Email |
| BackupFailed | バックアップが24時間以上未成功 | Critical | Slack / Email |
| SlowQueries | スロークエリ > 100/時 | Warning | Slack |
| ConnectionsHigh | 接続数 > max_connections * 0.8 | Warning | Slack |

### 4.3 CaaS アラート

| アラート名 | 条件 | 重要度 | 通知先 |
|-----------|------|--------|--------|
| JobFailed | Nomadジョブがdead状態 | Warning | Slack |
| AllocationPending | アロケーションが5分以上pending | Warning | Slack |
| NomadServerUnhealthy | Nomad Serverノード離脱 | Critical | Slack / Email |
| RegistryStorageLow | Harborストレージ > 80% | Warning | Slack |

---

## 5. セキュリティ運用

### 5.1 パッチ管理

| 対象 | 頻度 | 方法 |
|------|------|------|
| Proxmox VE | 月次 | apt update + reboot (ローリング) |
| Ubuntu VM | 月次 | unattended-upgrades (セキュリティのみ) |
| Laravel | 随時 | composer update (セキュリティアドバイザリ) |
| DBaaS | 随時 | 管理パネルからバージョンアップ操作 |

### 5.2 シークレット管理

| シークレット | 保存場所 | ローテーション |
|-------------|---------|--------------|
| Proxmox API Token | 管理DB (暗号化) | 6ヶ月 |
| Snippet API Token | 管理DB (暗号化) + 各ノード環境変数 | 6ヶ月 |
| DBaaS パスワード | 管理DB (暗号化) | テナント任意 |
| バックアップ暗号化キー | 管理DB (暗号化) | 変更不可 (キーリカバリ重要) |
| Laravel APP_KEY | .env ファイル | 初回のみ |
| S3 Access Key (AWS/Wasabi) | s3-proxy コンテナ .env のみ (テナントVMには配布しない) | 6ヶ月 |
| S3 プロキシ内部認証情報 | 管理DB (s3_credentials テーブル) | 管理画面からローテーション可 |
| MySQL Root パスワード | Docker secrets / .env | 初回のみ |

### 5.3 ログ監査

- 当面は audit_logs テーブルは設けない（将来必要に応じて追加）
- アプリケーションログ (Laravel Log) で操作履歴を確認可能

---

## 6. 障害対応手順

### 6.1 Proxmox ノード障害

```
1. Proxmox HA によるVM自動マイグレーションを確認
2. マイグレーションされない場合は手動でqm migrateを実行
3. 管理パネルのVM一覧でノード情報を確認
4. 障害ノードの復旧作業
```

### 6.2 DBaaS インスタンス障害

```
1. 管理パネルでDBステータスを確認
2. VMが起動している場合: SSH接続してDBプロセスを確認・再起動
3. VMが停止している場合: 管理パネルからVM起動
4. データ破損の場合: 最新バックアップからリストア
   a. 管理パネル → DBaaS詳細 → バックアップ一覧
   b. 対象バックアップを選択してリストア実行
```

### 6.3 Nomad ジョブ障害

```
1. 管理パネルでジョブステータスを確認
2. アロケーションのイベントログを確認
3. リソース不足の場合: Worker VMのスケールアップまたはWorker追加
4. イメージ取得エラーの場合: レジストリの状態を確認
5. 管理パネルからジョブを再起動
```

---

## 7. 環境変数一覧

### 7.1 Docker Compose (.env)

mgmt-docker VM のプロジェクトルートに配置する `.env` ファイル。

```env
# === Laravel (mgmt-app) ===
APP_NAME=MisosiruCloud
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://管理パネルURL

DB_CONNECTION=mysql
DB_HOST=mgmt-db
DB_PORT=3306
DB_DATABASE=misosiru_cloud
DB_USERNAME=misosiru
DB_PASSWORD=

# Proxmox (ノード情報はDBで管理、初期ノードのみ.envで指定)
PROXMOX_DEFAULT_NODE=pve1

# S3 プロキシ (内部 S3 エンドポイント)
S3_PROXY_ENDPOINT=http://s3-proxy:9000
S3_BACKUP_BUCKET=dbaas-backups
S3_LOG_ARCHIVE_BUCKET=log-archives

# Nomad
NOMAD_URL=http://nomad-server:4646
NOMAD_TOKEN=

# Queue (非同期ジョブ)
QUEUE_CONNECTION=database

# Grafana Cloud
GRAFANA_CLOUD_URL=https://xxx.grafana.net
GRAFANA_CLOUD_TOKEN=

# === S3 プロキシ (s3-proxy) ===
S3_BACKEND_ENDPOINT=https://s3.wasabisys.com
S3_BACKEND_REGION=ap-northeast-1
S3_BACKEND_ACCESS_KEY=
S3_BACKEND_SECRET_KEY=

# === MySQL (mgmt-db) ===
MYSQL_ROOT_PASSWORD=
MYSQL_DATABASE=misosiru_cloud
MYSQL_USER=misosiru
MYSQL_PASSWORD=
```

### 7.2 Snippet API (各 Proxmox ノード)

```env
SNIPPET_DIR=/app/snippets       # コンテナ内パス (ホストの /var/lib/vz/snippets をマウント)
API_TOKEN=<shared_secret>
BIND_HOST=0.0.0.0
BIND_PORT=8100
LOG_LEVEL=info
```

### 7.3 DBaaS VM (Cloud-init で設定)

```env
DB_TYPE=mysql                    # mysql / postgres / redis
GPG_PASSPHRASE=<backup_encryption_key>
S3_PROXY_ENDPOINT=http://s3.infra.example.com:9000
S3_ACCESS_KEY=<S3プロキシ発行の内部キー>
S3_SECRET_KEY=<S3プロキシ発行の内部シークレット>
S3_BUCKET=dbaas-backups
DB_ID=<database_instance_id>
```

---

## 8. Docker 運用

### 8.1 サービス再起動

```bash
# mgmt-docker VM 上
cd /opt/misosiru-cloud

# 全サービス再起動
docker compose restart

# 特定サービスのみ再起動
docker compose restart mgmt-app
docker compose restart mgmt-queue
docker compose restart s3-proxy

# 設定変更を反映して再作成
docker compose up -d --build mgmt-app
```

> **mgmt-queue コンテナ:** mgmt-app と同じイメージを使い、entrypoint を
> `php artisan queue:work --queue=provisioning --timeout=600 --tries=1 --sleep=3`
> に差し替えたコンテナ。DB/環境変数は mgmt-app と共有する。

### 8.2 ログ確認

```bash
# 全サービスのログ
docker compose logs -f

# 特定サービスのログ
docker compose logs -f mgmt-app
docker compose logs -f mgmt-queue
docker compose logs -f s3-proxy
docker compose logs -f dns
```

### 8.3 アップデート手順

```bash
cd /opt/misosiru-cloud
git pull origin main

# イメージ再ビルド + 再起動
docker compose build
docker compose up -d

# Laravel マイグレーション
docker compose exec mgmt-app php artisan migrate --force
```

### 8.4 mgmt-docker VM 障害時

```
1. mgmt-docker VM を Proxmox HA で自動復旧 (Ceph ディスクのため可能)
2. VM起動後、Docker Compose が自動起動 (systemd + restart: always)
3. 管理パネル、S3 プロキシ、DNS が自動復旧
4. 復旧しない場合:
   a. ssh mgmt-docker
   b. cd /opt/misosiru-cloud && docker compose up -d
```
