# Phase 13: インフラ基盤構築 (Docker Compose・CoreDNS・Harbor・SSL/TLS)

## 概要

mgmt-docker VM 上で稼働するインフラサービス群の Docker Compose 構成、CoreDNS キャッシュ/フォワーダ、
Harbor コンテナレジストリ、Let's Encrypt (DNS-01) による SSL/TLS 証明書の取得・更新を整備する。

これらは infrastructure-design.md で定義されているが、既存の実装計画にはどれも含まれていなかった。

## 現在の判定

❌ 未着手

---

## 出典（設計ドキュメント）

- [infrastructure-design.md セクション 4](../infrastructure-design.md) — Docker Compose サービス構成
- [infrastructure-design.md セクション 6.5.4](../infrastructure-design.md) — SSL/TLS 証明書 (Let's Encrypt DNS-01)
- [infrastructure-design.md セクション 6.5.5](../infrastructure-design.md) — CoreDNS 設計
- [やりたいこと.md](../やりたいこと.md) — コンテナレジストリ（全テナントアクセス可）

---

## チェックポイント

### 13-1. 本番用 Docker Compose ファイル (`compose.prod.yaml`)

> 開発用 `compose.yaml` は既存。本番用にサービスを追加・分離する。

- [ ] `compose.prod.yaml` 作成（以下のサービスを定義）
  - `mgmt-app` — Laravel アプリケーション (Nginx + PHP-FPM)
  - `mgmt-queue` — Laravel Queue Worker (`php artisan queue:work --queue=provisioning`)
  - `mgmt-db` — MySQL 8.4
  - `s3-proxy` — Go S3 プロキシ（ポート 9000）
  - `dns` — CoreDNS（ポート 53）
  - `registry` — Harbor コンテナレジストリ（ポート 443）
  - `otel-gateway` — OTel Collector Gateway（ポート 4317/4318）
- [ ] `.env.prod.example` — 本番用環境変数テンプレート
- [ ] Docker ネットワーク定義
  - `mgmt-net` — 管理サービス間の内部通信
- [ ] ボリューム定義
  - `db-data` — MySQL データ永続化
  - `registry-data` — Harbor データ永続化
  - `certs` — Let's Encrypt 証明書

### 13-2. 開発用 Docker Compose 更新 (`compose.yaml`)

- [ ] `queue` サービスを追加（Phase 11 で定義した Queue Worker）
- [ ] `dns` サービスを追加（開発用 CoreDNS — 任意）

### 13-3. CoreDNS 構築

> CoreDNS はキャッシュ兼フォワーダとして機能。独自ゾーンは持たない。

- [ ] `docker/dns/Corefile` 作成
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
- [ ] `docker/dns/override.hosts` 作成（初期は空、スプリットホライズン用）
- [ ] `docker/dns/Dockerfile` 作成（CoreDNS 公式イメージベース）
- [ ] CoreDNS 設定値
  - リッスンIP: `172.26.26.10:53` (管理NW)
  - フォワーダ: `8.8.8.8` / `8.8.4.4`
  - キャッシュ TTL: `300` 秒

### 13-4. Harbor コンテナレジストリ

- [ ] Harbor のデプロイ方式決定（Docker Compose or Helm）
- [ ] `docker/registry/` ディレクトリ作成
- [ ] Harbor 設定ファイル (`harbor.yml`) のテンプレート作成
  - HTTPS 有効 (`registry.infra.example.com`)
  - 認証方式: DB 認証 (初期、将来 OIDC 検討)
  - ストレージ: ローカルファイルシステム or S3
- [ ] Harbor 管理ユーザの初期設定
- [ ] テナント共有プロジェクトの設計（全テナントが pull 可能）
- [ ] CaaS (Nomad Worker) からの pull 設定
  - Docker daemon の `insecure-registries` or TLS 証明書配布

### 13-5. SSL/TLS 証明書 (Let's Encrypt DNS-01)

> HTTP-01 チャレンジは内部サービスに使用不可のため、DNS-01 を全面採用。

#### 内部インフラ証明書 (`*.infra.example.com`)

- [ ] `certbot` + `dns-sakuracloud` プラグインのインストール手順
- [ ] certbot 設定ファイル作成
  ```bash
  certbot certonly \
    --dns-sakuracloud \
    --dns-sakuracloud-credentials /etc/letsencrypt/sakura.ini \
    -d "*.infra.example.com" \
    -d "infra.example.com"
  ```
- [ ] さくらのクラウド DNS API 認証情報ファイル (`sakura.ini`) テンプレート
- [ ] 証明書自動更新 cron ジョブ設定

#### CaaS / VPS 証明書 (`*.containers.example.com`)

- [ ] VPS 側での certbot + cloudflare プラグイン設定
  ```bash
  certbot certonly \
    --dns-cloudflare \
    --dns-cloudflare-credentials /etc/letsencrypt/cloudflare.ini \
    -d "*.containers.example.com" \
    -d "*.example.com" \
    -d "example.com"
  ```
- [ ] Cloudflare API 認証情報ファイル (`cloudflare.ini`) テンプレート

#### 証明書一覧

| 証明書 | ドメイン | DNS プロバイダ | 取得場所 |
|--------|---------|--------------|---------|
| 内部ワイルドカード | `*.infra.example.com` | さくら DNS | mgmt-docker VM |
| CaaS ワイルドカード | `*.containers.example.com` | Cloudflare | VPS |
| VPS サイト | `*.example.com`, `example.com` | Cloudflare | VPS |

### 13-6. DNS ゾーン設計の反映

> infrastructure-design.md セクション 6.5.2-6.5.3 の DNS レコード一覧を反映。

- [ ] グローバルゾーン (`example.com` — Cloudflare) のレコード設計確認
  - `example.com` A → VPS Global IP
  - `*.containers.example.com` A → VPS Global IP
  - `infra.example.com` NS → さくら DNS
- [ ] 内部インフラゾーン (`infra.example.com` — さくら DNS) のレコード設計確認
  - `mgmt.infra.example.com` A → 172.26.26.10
  - `s3.infra.example.com` A → 172.26.26.10
  - `registry.infra.example.com` A → 172.26.26.10
  - `dns.infra.example.com` A → 172.26.26.10
  - `otel.infra.example.com` A → 172.26.26.10
  - `snippet-pve{1,2,3}.infra.example.com` A → 172.26.26.{11,12,13}

### 13-7. テスト & 検証

- [ ] CoreDNS コンテナ起動後、`dig @172.26.26.10 s3.infra.example.com` で正引き確認
- [ ] Harbor 起動後、`docker login registry.infra.example.com` でログイン確認
- [ ] Let's Encrypt 証明書取得テスト（staging 環境で dry-run）
- [ ] `compose.prod.yaml` の `docker compose -f compose.prod.yaml up -d` で全サービス起動確認

---

## 完了条件

- `compose.prod.yaml` で全サービス（app, queue, db, s3-proxy, dns, registry, otel-gateway）が起動すること
- CoreDNS がキャッシュ/フォワーダとして DNS 解決できること
- Harbor でコンテナイメージの push/pull ができること
- Let's Encrypt 証明書が DNS-01 チャレンジで取得できること（staging dry-run）
- 全 DNS レコードが設計通りに解決されること
