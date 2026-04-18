# Phase 14: Packer VM テンプレート

## 概要

Proxmox VE 上で VM のクローン元として使用する **テンプレート VM** を **Packer** で自動生成する仕組みを構築する。
Ubuntu 24.04 cloud image をベースに、用途別のプロビジョニングスクリプトを実行し、Proxmox テンプレートとして保存する。

Phase 4 (VM 管理) および Phase 5 (DBaaS) のクローン操作はこれらのテンプレートが前提条件となる。

## 現在の判定

❌ 未着手

---

## 出典（設計ドキュメント）

- [infrastructure-design.md セクション 7](../infrastructure-design.md) — テンプレート VM 設計 (Packer + Cloud-init)
- [infrastructure-design.md セクション 8](../infrastructure-design.md) — Cloud-init (デプロイ時設定)

---

## チェックポイント

### 14-1. ディレクトリ構成

- [ ] `packer/` ディレクトリ作成
  ```
  packer/
  ├── base-ubuntu.pkr.hcl          # ベーステンプレート定義 (proxmox-clone)
  ├── dbaas-mysql.pkr.hcl          # MySQL DBaaS テンプレート
  ├── dbaas-postgres.pkr.hcl       # PostgreSQL DBaaS テンプレート
  ├── dbaas-redis.pkr.hcl          # Redis DBaaS テンプレート
  ├── nomad-worker.pkr.hcl         # Nomad Worker テンプレート
  ├── variables.pkr.hcl            # 共通変数定義
  └── scripts/
      ├── import-cloud-image.sh    # Cloud image ダウンロード＆シードテンプレート作成
      ├── base.sh                  # 共通セットアップ
      ├── cleanup.sh               # テンプレート化前クリーンアップ
      ├── mysql.sh                 # MySQL インストール
      ├── postgres.sh              # PostgreSQL インストール
      ├── redis.sh                 # Redis インストール
      └── nomad-worker.sh          # Docker + Nomad agent + cAdvisor
  ```

### 14-2. 共通変数定義 (`variables.pkr.hcl`)

- [ ] `proxmox_url` — Proxmox API URL (デフォルト: `https://pve1.infra.example.com:8006/api2/json`)
- [ ] `proxmox_username` — API Token ID (例: `packer@pve!packer-token`)
- [ ] `proxmox_token` — API Token Secret (sensitive)
- [ ] `proxmox_node` — ビルド先ノード (デフォルト: `pve1`)
- [ ] `proxmox_storage_pool` — VM ディスク・Cloud-init ストレージプール (デフォルト: `ceph-pool`)
- [ ] `seed_vmid` — シードテンプレート VMID (デフォルト: `8999`)
- [ ] `cloud_image_url` — Ubuntu 24.04 cloud image URL (デフォルト: `https://cloud-images.ubuntu.com/noble/current/noble-server-cloudimg-amd64.img`)
- [ ] `ssh_username` — Packer SSH ユーザ名 (デフォルト: `ubuntu`, cloud image のデフォルトユーザ)
- [ ] `ssh_password` — Packer SSH パスワード (sensitive, cloud-init で一時的に設定)

### 14-3. シードテンプレート作成スクリプト (`scripts/import-cloud-image.sh`)

> Packer は既存テンプレートのクローンしかできないため、まず cloud image をインポートした「シードテンプレート」を作成する。
> このスクリプトは初回のみ、または cloud image 更新時に実行する。

> **ストレージに関する注意:** ディスク・Cloud-init ドライブは全て `ceph-pool` (Ceph RBD) に配置する。
> Ceph は全ノード共有ストレージのため、テンプレートをどのノードで作成しても、任意のノードでクローン・起動が可能。
> `local-lvm` などノードローカルストレージを使うと、別ノードでのクローンが失敗するため注意。

- [ ] Ubuntu 24.04 cloud image のダウンロード
  ```bash
  wget -O /tmp/noble-server-cloudimg-amd64.img "$CLOUD_IMAGE_URL"
  ```
- [ ] シードテンプレート VM 作成 (VMID: `8999`)
  ```bash
  qm create 8999 \
    --name tmpl-seed-ubuntu \
    --ostype l26 \
    --cpu cputype=host \
    --cores 2 \
    --memory 2048 \
    --agent enabled=1 \
    --net0 virtio,bridge=vmbr0 \
    --scsihw virtio-scsi-single \
    --serial0 socket \
    --vga serial0
  ```
- [ ] cloud image をディスクとしてインポート
  ```bash
  qm importdisk 8999 /tmp/noble-server-cloudimg-amd64.img ceph-pool
  ```
- [ ] インポートしたディスクをアタッチ + Cloud-init ドライブ追加
  ```bash
  qm set 8999 --scsi0 ceph-pool:vm-8999-disk-0,discard=on,iothread=1
  qm set 8999 --ide2 ceph-pool:cloudinit
  qm set 8999 --boot order=scsi0
  ```
- [ ] テンプレートに変換
  ```bash
  qm template 8999
  ```
- [ ] 冪等性: 既に VMID 8999 が存在する場合はスキップまたはエラー終了

### 14-4. ベーステンプレート (`base-ubuntu.pkr.hcl`)

- [ ] `proxmox-clone` ソース定義（シードテンプレート 8999 からクローン）
  - clone_vm_id: `8999` (シードテンプレート)
  - VMID: `9000`
  - VM名: `tmpl-base-ubuntu`
  - qemu-agent: 有効
  - cloud-init: 有効 (`cloud_init_storage_pool = "ceph-pool"`)
  - ネットワーク: `vmbr0`, virtio
- [ ] cloud-init で一時的に SSH パスワード認証を設定（Packer プロビジョニング用）
- [ ] プロビジョナ: `scripts/base.sh` → `scripts/cleanup.sh`

### 14-5. 共通セットアップスクリプト (`scripts/base.sh`)

- [ ] パッケージ更新 (`apt-get update && upgrade`)
- [ ] 共通パッケージ: `qemu-guest-agent`, `curl`, `jq`, `gpg`, `awscli`
- [ ] タイムゾーン: `Asia/Tokyo`
- [ ] node_exporter インストール + systemd サービス登録
  - バージョン: v1.8.2
  - ユーザ: `node_exporter` (nologin)
  - ポート: デフォルト (9100)

### 14-6. クリーンアップスクリプト (`scripts/cleanup.sh`)

- [ ] ログクリア (`truncate -s 0 /var/log/*.log`, `journalctl --vacuum-time=1s`)
- [ ] machine-id リセット（クローン時に再生成）
- [ ] cloud-init クリーンアップ（次回起動時に再実行）
- [ ] apt キャッシュ削除
- [ ] 一時ファイル・Bash history 削除
- [ ] `sync`

### 14-7. DBaaS MySQL テンプレート (`dbaas-mysql.pkr.hcl`)

- [ ] `proxmox-clone` ソース定義
  - clone_vm_id: `9000` (base-ubuntu)
  - VMID: `9010`
  - VM名: `tmpl-dbaas-mysql`
- [ ] `scripts/mysql.sh`
  - MySQL 8.4 パッケージインストール
  - サービス無効化（cloud-init で設定後に起動するため）

### 14-8. DBaaS PostgreSQL テンプレート (`dbaas-postgres.pkr.hcl`)

- [ ] `proxmox-clone` ソース定義
  - clone_vm_id: `9000`
  - VMID: `9011`
  - VM名: `tmpl-dbaas-postgres`
- [ ] `scripts/postgres.sh`
  - PostgreSQL 17 パッケージインストール
  - サービス無効化

### 14-9. DBaaS Redis テンプレート (`dbaas-redis.pkr.hcl`)

- [ ] `proxmox-clone` ソース定義
  - clone_vm_id: `9000`
  - VMID: `9012`
  - VM名: `tmpl-dbaas-redis`
- [ ] `scripts/redis.sh`
  - Redis 7.x パッケージインストール
  - サービス無効化

### 14-10. Nomad Worker テンプレート (`nomad-worker.pkr.hcl`)

- [ ] `proxmox-clone` ソース定義
  - clone_vm_id: `9000`
  - VMID: `9020`
  - VM名: `tmpl-nomad-worker`
- [ ] `scripts/nomad-worker.sh`
  - Docker Engine インストール
  - Nomad agent バイナリ配置 + systemd サービス登録
  - cAdvisor コンテナ設定（Docker ソケット・rootfs マウント）

### 14-11. テンプレート VMID 一覧

| テンプレート名 | VMID | ベース | インストール内容 |
|-------------|------|--------|---------------|
| seed-ubuntu | 8999 | Ubuntu 24.04 cloud image (.img) | なし（素の cloud image） |
| base-ubuntu | 9000 | seed-ubuntu クローン | qemu-guest-agent, node_exporter |
| dbaas-mysql | 9010 | base-ubuntu クローン | MySQL 8.4 |
| dbaas-postgres | 9011 | base-ubuntu クローン | PostgreSQL 17 |
| dbaas-redis | 9012 | base-ubuntu クローン | Redis 7.x |
| nomad-worker | 9020 | base-ubuntu クローン | Docker, Nomad agent, cAdvisor |

### 14-12. Cloud-init テンプレート連携

> Packer でインストール（時間がかかる共通処理）、Cloud-init でインスタンス固有設定を注入。

- [ ] Cloud-init で注入する設定一覧の確認
  - IP アドレス: `network-config.yaml`
  - ホスト名: `meta-data.yaml`
  - SSH 公開鍵: `user-data.yaml`
  - DNS 参照先 (CoreDNS): `user-data.yaml`
  - DB ユーザ/パスワード: `user-data.yaml` (DBaaS のみ)
  - DB 設定ファイル: `user-data.yaml` (DBaaS のみ)
  - バックアップ cron: `user-data.yaml` (DBaaS のみ)
- [ ] 全 Cloud-init user-data に DNS 設定を含める確認
  ```yaml
  manage_resolv_conf: true
  resolv_conf:
    nameservers:
      - 172.26.26.10    # CoreDNS
  ```

### 14-13. テンプレート更新運用手順

- [ ] `docs/deployment-operations.md` にテンプレート更新手順を追記
  1. `packer build` で新テンプレートを一時 VMID に作成
  2. 動作確認（手動クローンテスト）
  3. 旧テンプレートを削除 or 管理 DB の `template_vmid` を更新
  4. 既存 VM は影響なし（フルクローン）

### 14-14. テスト & 検証

- [ ] `scripts/import-cloud-image.sh` でシードテンプレート (8999) が正常に作成されること
- [ ] `packer validate` で全 HCL ファイルの構文チェック
- [ ] `packer build base-ubuntu.pkr.hcl` でベーステンプレートが正常に作成されること
- [ ] ベーステンプレートからクローンした VM で `qemu-guest-agent` と `node_exporter` が稼働すること
- [ ] DBaaS テンプレートからクローンした VM で対応 DB パッケージがインストール済みであること
- [ ] Nomad Worker テンプレートで Docker + Nomad agent が起動可能なこと

---

## 完了条件

- `scripts/import-cloud-image.sh` でシードテンプレート (8999) が作成できること
- `packer build` で全 5 テンプレート（base-ubuntu, dbaas-mysql, dbaas-postgres, dbaas-redis, nomad-worker）が作成できること
- 各テンプレートからクローンした VM が Cloud-init で正しく初期設定されること
- テンプレート更新運用手順がドキュメント化されていること
