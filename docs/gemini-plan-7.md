# おうちクラウドサービス 統合設計書 (v7.0)

## 1. プロジェクトコンセプト
* **API-First & DB-Lite:** Proxmox/Nomad API を正とし、管理 DB は最小限のメタデータのみを保持。
* **Hybrid Networking (PBR):** 自宅回線（高速）と VPS 経由（外部公開）をポリシーベースで自動切り替え。
* **Scalable CaaS:** Nomad + CNI により、Worker VM を汚さずテナントごとの L2 隔離ネットワークでコンテナを稼働。
* **Deep Observability:** Nomad 標準テレメトリと **cAdvisor** を組み合わせ、OTel 経由で Grafana Cloud へ集約。

---

## 2. 監視・可観測性 (OpenTelemetry & cAdvisor)

### 4.1 メトリクス収集スタック
Nomad タスクの監視を「標準メトリクス」と「詳細メトリクス」の二段構えで実装します。



* **Nomad Telemetry (標準):** 各 Worker の `:4646/v1/metrics` から、ジョブやタスクの全体的な CPU/メモリ/ネットワーク統計を取得。
* **cAdvisor (詳細):** * **デプロイ:** Nomad の `system` ジョブとして各 Worker ノードで 1 つずつ常駐稼働。
    * **役割:** コンテナごとの詳細なディスク I/O、メモリの細分化データ（Cache/RSS）、およびネットワークパケット統計を収集。
    * **エンドポイント:** 各 Worker の `:8080/metrics` を OTel がスクレイピング。

### 4.2 OTel Collector 経路
1.  **Agent レイヤー:** 各 Worker 内で OTel Collector (Nomad System Job) が **Nomad API** と **cAdvisor** を同時にスクレイピング。
2.  **集約レイヤー:** 各サブネットから **Transit VM** を経由し、インフラ管理網の **Gateway Collector** へ送信。
3.  **転送:** Gateway Collector が Grafana Cloud へ一括転送。

---

## 3. ネットワーク・ルーティング設計 (Transit VM 方式)

### 3.1 Transit VM の冗長化
* **構成:** 2台の軽量 VM (Alpine + FRR + Keepalived) による Active-Passive 構成。
* **VPN:** VPS と WireGuard で常時接続。VRRP により仮想 IP (VIP) を共有。

### 3.2 ポリシーベースルーティング (PBR)
* **Outbound:** 通常の通信は自宅ルーター経由（高速）。
* **Inbound/Reply:** VPS 経由のアクセスへの返信のみ、強制的に Transit VM (VIP) へルーティング。

---

## 4. サービスレイヤーの実装

### 5.1 DBaaS (MySQL / Redis)
* **初期化:** Sidecar API (Python) 経由で注入された Cloud-init により、起動時に設定を自動適用。

### 5.2 CaaS (Nomad)
* **CNI 隔離:** **Nomad CNI (bridge mode)** を使用。コンテナは起動時に動的に各テナントの VNet ブリッジへアタッチされ、L2 レベルで通信を遮断。
* **監視ジョブ:** `cAdvisor` および `OTel Agent` を `system` ジョブとして全 Worker に自動展開。

---

## 5. アプリケーションスタック
* **管理パネル:** Laravel 11 + Livewire v3。
* **Sidecar API:** Python (FastAPI)。Proxmox 各ノードで Snippet 書き込みを担当。
* **可視化:** Grafana Cloud のダッシュボードを Laravel に埋め込み、テナント ID でフィルタリング。

---

## 6. 実装ロードマップ
1.  **Phase 1:** Transit VM 冗長化、BGP 経路学習の構築。
2.  **Phase 2:** **cAdvisor** と OTel Collector を Nomad にデプロイし、Grafana Cloud へのメトリクス送信を確立。
3.  **Phase 3:** Sidecar API と連動した Cloud-init VM の自動デプロイ実装。
4.  **Phase 4:** PBR の動作検証とマルチテナント運用開始。

---

### 💡 cAdvisor 導入のポイント
cAdvisor はコンテナの `rootfs` や `var/run/docker.sock` 等をマウントする必要があるため、Nomad Job ファイルの `template` や `mount` 設定で適切な権限を付与してデプロイします。これにより、Laravel 側で「コンテナごとのディスク使用量」などの細かいリソース制限や課金計算が可能になります。
