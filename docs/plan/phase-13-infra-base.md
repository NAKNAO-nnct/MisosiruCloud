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

> CoreDNS はキャッシュ兼フォワーダとして機能し、加えて `file` プラグインでローカルオーバーライドゾーンを権威的にホストする。
> ローカルオーバーライドにより、同一ドメインに対してローカルネットワークからのアクセス時にローカル IP を返す（スプリットホライズン）。

- [ ] `docker/dns/Corefile` 作成（初期テンプレート — ローカルゾーンなし）
  ```
  # ローカルオーバーライドゾーンは DnsService (LocalDnsProvider) が自動生成する。
  # 初期状態ではフォワーダのみ。

  .:53 {
      forward . 8.8.8.8 8.8.4.4
      cache 300
      log
      errors
  }
  ```
- [ ] `docker/dns/zones/` ディレクトリ作成（ゾーンファイル格納先）
- [ ] `docker/dns/Dockerfile` 作成（CoreDNS 公式イメージベース）
- [ ] Docker Compose ボリュームマウント設定
  - `/etc/coredns/Corefile` ← `docker/dns/Corefile` (初期テンプレート)
  - `/etc/coredns/zones/` ← `docker/dns/zones/` (ゾーンファイル格納、LocalDnsProvider が書き込み)
- [ ] CoreDNS 設定値
  - リッスンIP: `172.26.26.10:53` (管理NW)
  - フォワーダ: `8.8.8.8` / `8.8.4.4`
  - キャッシュ TTL: `300` 秒
- [ ] CoreDNS の SIGHUP リロードが正しく動作することを確認
  - `docker compose exec dns kill -SIGHUP 1` or `docker compose kill -s SIGHUP dns`

### 13-3a. DNS 管理基盤（Laravel 側）

> detailed-design.md セクション 10.6〜10.8 に基づく、DNS 管理の Laravel 実装。

#### DB マイグレーション

- [ ] `create_dns_zones_table` マイグレーション作成
  - カラム: id, name, provider(enum: cloudflare/sakura/local), external_zone_id, description, is_active, timestamps
  - ユニーク制約: name
- [ ] `create_dns_records_table` マイグレーション作成
  - カラム: id, dns_zone_id(FK), name, type(enum: A/AAAA/CNAME/NS/TXT/MX/SRV), content, ttl, priority, external_id, comment, timestamps
  - ユニーク制約: (dns_zone_id, name, type, content)

#### Model & Data クラス

- [ ] `App\Models\DnsZone` — Eloquent Model
- [ ] `App\Models\DnsRecord` — Eloquent Model
- [ ] `App\Data\Dns\DnsZoneData` — `final readonly class` (of/make/toArray)
- [ ] `App\Data\Dns\DnsRecordData` — `final readonly class` (of/make/toArray)

#### Repository

- [ ] `App\Repositories\DnsZoneRepository` — findByIdOrFail, findByProvider, all, create, update, delete
- [ ] `App\Repositories\DnsRecordRepository` — findByZoneId, create, update, delete, findByZoneIdAndType

#### Lib\Dns (プロバイダ層)

- [ ] `App\Lib\Dns\DnsProviderInterface` — listRecords, createRecord, updateRecord, deleteRecord
- [ ] `App\Lib\Dns\CloudflareDnsProvider` — Cloudflare API v4 実装
  - HTTP クライアント: `Http::withToken($apiToken)->baseUrl('https://api.cloudflare.com/client/v4')`
  - ゾーン操作: `GET/POST/PUT/DELETE /zones/{zone_id}/dns_records`
  - 認証: API Token (config 経由)
- [ ] `App\Lib\Dns\SakuraDnsProvider` — さくらのクラウド DNS API 実装
  - HTTP クライアント: さくら API v2
  - 認証: Access Token + Access Token Secret (config 経由)
- [ ] `App\Lib\Dns\LocalDnsProvider` — CoreDNS ゾーンファイル生成
  - `regenerateZoneFile(string $domain, Collection $records)`: RFC 1035 準拠ゾーンファイル生成
  - `regenerateCorefile(Collection $localZones)`: ドメインブロック付き Corefile 再生成
  - `reloadCoreDns()`: CoreDNS コンテナに SIGHUP 送信
  - ゾーンファイル出力先: `/etc/coredns/zones/db.{domain}`
  - SOA シリアル: `YYYYMMDDnn` 形式で自動インクリメント
- [ ] `App\Lib\Dns\DnsProviderFactory` — provider 文字列 → インスタンス解決

#### Service

- [ ] `App\Services\DnsService` 作成
  - `createRecord(int $zoneId, array $attributes)`: DB 保存 → Provider 反映
  - `updateRecord(int $zoneId, int $recordId, array $attributes)`: DB 更新 → Provider 反映
  - `deleteRecord(int $zoneId, int $recordId)`: DB 削除 → Provider 反映
  - `syncFromProvider(int $zoneId)`: 外部プロバイダからレコードを取得し DB に同期
  - `regenerateLocalZones()`: `local` プロバイダのゾーンファイル全体を再生成

#### Controller & View

- [ ] `Admin\Dns\ZoneIndex` — ゾーン一覧画面（ゾーン名・プロバイダ・レコード数）
- [ ] `Admin\Dns\ZoneStore` — ゾーン追加
- [ ] `Admin\Dns\ZoneUpdate` — ゾーン更新
- [ ] `Admin\Dns\ZoneDestroy` — ゾーン削除
- [ ] `Admin\Dns\RecordIndex` — レコード一覧画面（ゾーン配下のレコード表示）
- [ ] `Admin\Dns\RecordStore` — レコード追加 (+ DnsRecordStoreRequest FormRequest)
- [ ] `Admin\Dns\RecordUpdate` — レコード更新
- [ ] `Admin\Dns\RecordDestroy` — レコード削除
- [ ] `Admin\Dns\ZoneSync` — 外部プロバイダからレコード同期
- [ ] `Admin\Dns\Reload` — CoreDNS リロード
- [ ] View: `resources/views/admin/dns/zone-index.blade.php`
- [ ] View: `resources/views/admin/dns/record-index.blade.php`

#### Config

- [ ] `config/dns.php` 作成
  ```php
  return [
      'providers' => [
          'cloudflare' => [
              'api_token' => env('CLOUDFLARE_API_TOKEN'),
          ],
          'sakura' => [
              'access_token' => env('SAKURA_DNS_ACCESS_TOKEN'),
              'access_token_secret' => env('SAKURA_DNS_ACCESS_TOKEN_SECRET'),
          ],
          'local' => [
              'zones_path' => env('COREDNS_ZONES_PATH', '/etc/coredns/zones'),
              'corefile_path' => env('COREDNS_COREFILE_PATH', '/etc/coredns/Corefile'),
              'container_name' => env('COREDNS_CONTAINER_NAME', 'dns'),
          ],
      ],
  ];
  ```

#### 初期データ (Seeder)

- [ ] `DnsZoneSeeder` — 初期ゾーン3件を登録
  ```php
  // example.com (Cloudflare)
  // infra.example.com (sakura)
  // local.override (local) — スプリットホライズン用
  ```

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
> 各ゾーンのレコードは DB (dns_zones / dns_records) で管理し、プロバイダ別に反映する。

#### ゾーン一覧

| ゾーン名 | provider | 外部プロバイダ | 用途 |
|---------|----------|-------------|------|
| `example.com` | `cloudflare` | Cloudflare | 公開サービス・CaaS ワイルドカード |
| `infra.example.com` | `sakura` | さくらのクラウド DNS | 内部インフラサービス (プライベート IP) |
| `local.override` | `local` | CoreDNS file プラグイン | スプリットホライズン (ローカルオーバーライド) |

#### グローバルゾーン (`example.com` — Cloudflare)

- [ ] レコード設計確認
  - `example.com` A → VPS Global IP
  - `*.containers.example.com` A → VPS Global IP
  - `infra.example.com` NS → さくら DNS

#### 内部インフラゾーン (`infra.example.com` — さくら DNS)

- [ ] レコード設計確認
  - `mgmt.infra.example.com` A → 172.26.26.10
  - `s3.infra.example.com` A → 172.26.26.10
  - `registry.infra.example.com` A → 172.26.26.10
  - `dns.infra.example.com` A → 172.26.26.10
  - `otel.infra.example.com` A → 172.26.26.10
  - `snippet-pve{1,2,3}.infra.example.com` A → 172.26.26.{11,12,13}

#### ローカルオーバーライドゾーン (`local.override` — CoreDNS)

> 同一ドメインのグローバル/ローカル解決を分岐させる。
> `local.override` ゾーンのレコードは CoreDNS の `file` プラグインで権威応答される。

- [ ] ローカルオーバーライドレコードの初期設計
  - ローカルからアクセス時に VPS Global IP ではなくローカル IP を返したいレコード:
  - `registry.example.com` A → 172.26.26.10 (外部からは VPS IP)
  - `mgmt.example.com` A → 172.26.26.10 (必要な場合)
  - その他、運用中に管理画面から追加可能

### 13-7. テスト & 検証

#### インフラ検証
- [ ] CoreDNS コンテナ起動後、`dig @172.26.26.10 s3.infra.example.com` で正引き確認
- [ ] ローカルオーバーライドテスト: ゾーンファイル生成後、`dig @127.0.0.1 registry.example.com` でローカル IP が返ること
- [ ] Harbor 起動後、`docker login registry.infra.example.com` でログイン確認
- [ ] Let's Encrypt 証明書取得テスト（staging 環境で dry-run）
- [ ] `compose.prod.yaml` の `docker compose -f compose.prod.yaml up -d` で全サービス起動確認

#### Laravel テスト
- [ ] Unit: `DnsZoneData` / `DnsRecordData` の `of()` / `make()` / `toArray()` 変換テスト
- [ ] Unit: `DnsZoneRepository` / `DnsRecordRepository` の CRUD テスト
- [ ] Unit: `LocalDnsProvider` のゾーンファイル生成が RFC 1035 準拠であること
- [ ] Unit: `LocalDnsProvider` の Corefile 生成が正しいドメインブロックを含むこと
- [ ] Feature: `CloudflareDnsProvider` の API 呼び出し (Http::fake)
- [ ] Feature: `SakuraDnsProvider` の API 呼び出し (Http::fake)
- [ ] Feature: `DnsService::createRecord()` が DB 保存 + Provider 反映を行うこと
- [ ] Feature: `DnsService::regenerateLocalZones()` がゾーンファイルを生成すること
- [ ] Feature: DNS 管理画面のゾーン一覧・レコード CRUD が動作すること

---

## 完了条件

- `compose.prod.yaml` で全サービス（app, queue, db, s3-proxy, dns, registry, otel-gateway）が起動すること
- CoreDNS がキャッシュ/フォワーダ + ローカルオーバーライドとして DNS 解決できること
- スプリットホライズン: 同一ドメインに対して外部からはグローバル IP、ローカルからはローカル IP が返ること
- DNS 管理画面からゾーン単位でレコードの CRUD ができ、プロバイダ別に反映されること
- `local` プロバイダのレコード変更が CoreDNS ゾーンファイルに即時反映されること
- Harbor でコンテナイメージの push/pull ができること
- Let's Encrypt 証明書が DNS-01 チャレンジで取得できること（staging dry-run）
- `php artisan test --compact` で DNS 関連テストが全パス
- `vendor/bin/pint --dirty --format agent` でスタイル違反なし
