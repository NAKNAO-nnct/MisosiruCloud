# API設計書

## 1. 設計方針

- 管理パネル (Laravel) は Blade テンプレートによるサーバサイドレンダリングをメインとする
- Web ルート (フォーム送信 + リダイレクト) を基本とし、非同期が必要な部分のみ内部API化
- 外部公開APIは設けない（管理パネルのみが各バックエンドと通信）
- 認証はセッションベース (Laravel標準)

---

## 2. Webルート一覧

### 2.1 認証

すべての Controller は Single Action Controller (`__invoke`) として実装する。

| メソッド | パス | Controller | 説明 |
|---------|------|-----------|------|
| GET | /login | Auth\ShowLoginForm | ログイン画面 |
| POST | /login | Auth\Login | ログイン処理 |
| POST | /logout | Auth\Logout | ログアウト |
| GET | /2fa/verify | Auth\TwoFactor\Show | 2FA入力画面 (有効時のみ) |
| POST | /2fa/verify | Auth\TwoFactor\Verify | 2FA検証 |
| GET | /2fa/setup | Auth\TwoFactor\Setup | 2FA設定 (任意) |
| POST | /2fa/confirm | Auth\TwoFactor\Confirm | 2FA設定確認 |
| DELETE | /2fa/disable | Auth\TwoFactor\Disable | 2FA無効化 |

### 2.2 ダッシュボード

| メソッド | パス | Controller | 説明 |
|---------|------|-----------|------|
| GET | / | Dashboard\Index | ダッシュボード |

### 2.3 テナント管理

| メソッド | パス | Controller | 説明 | 権限 |
|---------|------|-----------|------|------|
| GET | /tenants | Tenant\Index | テナント一覧 | admin |
| GET | /tenants/create | Tenant\Create | テナント作成画面 | admin |
| POST | /tenants | Tenant\Store | テナント作成処理 | admin |
| GET | /tenants/{tenant} | Tenant\Show | テナント詳細 | admin, tenant_admin(自身) |
| GET | /tenants/{tenant}/edit | Tenant\Edit | テナント編集画面 | admin |
| PUT | /tenants/{tenant} | Tenant\Update | テナント更新処理 | admin |
| DELETE | /tenants/{tenant} | Tenant\Destroy | テナント削除 | admin |

### 2.4 VM管理

| メソッド | パス | Controller | 説明 | 権限 |
|---------|------|-----------|------|------|
| GET | /vms | Vm\Index | VM一覧 | all |
| GET | /vms/create | Vm\Create | VM作成画面 | admin, tenant_admin |
| POST | /vms | Vm\Store | VM作成処理 | admin, tenant_admin |
| GET | /vms/{vmid} | Vm\Show | VM詳細 | all(自テナント) |
| POST | /vms/{vmid}/start | Vm\Start | VM起動 | admin, tenant_admin |
| POST | /vms/{vmid}/stop | Vm\Stop | VM停止 | admin, tenant_admin |
| POST | /vms/{vmid}/reboot | Vm\Reboot | VM再起動 | admin, tenant_admin |
| POST | /vms/{vmid}/force-stop | Vm\ForceStop | VM強制停止 | admin |
| DELETE | /vms/{vmid} | Vm\Destroy | VM削除 | admin |
| GET | /vms/{vmid}/console | Vm\Console | VNCコンソール | admin, tenant_admin |
| POST | /vms/{vmid}/snapshot | Vm\Snapshot | スナップショット作成 | admin, tenant_admin |
| POST | /vms/{vmid}/resize | Vm\Resize | リサイズ | admin |

### 2.5 DBaaS管理

| メソッド | パス | Controller | 説明 | 権限 |
|---------|------|-----------|------|------|
| GET | /dbaas | Dbaas\Index | DB一覧 | all |
| GET | /dbaas/create | Dbaas\Create | DB作成画面 | admin, tenant_admin |
| POST | /dbaas | Dbaas\Store | DB作成処理 | admin, tenant_admin |
| GET | /dbaas/{id} | Dbaas\Show | DB詳細 | all(自テナント) |
| POST | /dbaas/{id}/start | Dbaas\Start | DB起動 | admin, tenant_admin |
| POST | /dbaas/{id}/stop | Dbaas\Stop | DB停止 | admin, tenant_admin |
| DELETE | /dbaas/{id} | Dbaas\Destroy | DB削除 | admin |
| POST | /dbaas/{id}/backup | Dbaas\Backup | 即時バックアップ | admin, tenant_admin |
| GET | /dbaas/{id}/backups | Dbaas\Backups | バックアップ一覧 | all(自テナント) |
| POST | /dbaas/{id}/restore | Dbaas\Restore | バックアップリストア | admin |
| POST | /dbaas/{id}/upgrade | Dbaas\Upgrade | バージョンアップデート | admin |
| GET | /dbaas/{id}/credentials | Dbaas\Credentials | 接続情報表示 | admin, tenant_admin |

### 2.6 コンテナ管理 (CaaS)

| メソッド | パス | Controller | 説明 | 権限 |
|---------|------|-----------|------|------|
| GET | /containers | Container\Index | コンテナ一覧 | all |
| GET | /containers/deploy | Container\Create | デプロイ画面 | admin, tenant_admin |
| POST | /containers | Container\Store | デプロイ処理 (Traefik tags 自動生成) | admin, tenant_admin |
| GET | /containers/{id} | Container\Show | コンテナ詳細 | all(自テナント) |
| POST | /containers/{id}/restart | Container\Restart | 再起動 | admin, tenant_admin |
| POST | /containers/{id}/scale | Container\Scale | スケール変更 | admin, tenant_admin |
| DELETE | /containers/{id} | Container\Destroy | 削除 | admin, tenant_admin |
| GET | /containers/{id}/logs | Container\Logs | ログ表示 | all(自テナント) |

> **Traefik 連携:** `Container\Store` はデプロイ時にユーザが指定したドメイン名を
> Consul サービスタグ (`traefik.http.routers.{name}.rule=Host(...)`) として Nomad Job に埋め込む。
> Traefik が Consul Catalog を watch し、コンテナへのルーティングを自動反映する。

### 2.7 ネットワーク管理

| メソッド | パス | Controller | 説明 | 権限 |
|---------|------|-----------|------|------|
| GET | /networks | Network\Index | ネットワーク一覧 | admin |
| GET | /networks/create | Network\Create | ネットワーク作成画面 | admin |
| POST | /networks | Network\Store | ネットワーク作成 | admin |
| GET | /networks/{id} | Network\Show | ネットワーク詳細 | admin |
| DELETE | /networks/{id} | Network\Destroy | ネットワーク削除 | admin |

### 2.8 監視

| メソッド | パス | Controller | 説明 | 権限 |
|---------|------|-----------|------|------|
| GET | /monitoring | Monitoring\Index | 監視ダッシュボード | all |
| GET | /monitoring/grafana-url | Monitoring\GrafanaUrl | Grafana埋め込みURL生成 | all |

### 2.9 システム管理

| メソッド | パス | Controller | 説明 | 権限 |
|---------|------|-----------|------|------|
| GET | /admin/nodes | Admin\Node\Index | Proxmoxノード一覧 | admin |
| POST | /admin/nodes | Admin\Node\Store | ノード登録 | admin |
| PUT | /admin/nodes/{id} | Admin\Node\Update | ノード設定更新 | admin |
| GET | /admin/users | Admin\User\Index | ユーザ一覧 | admin |
| POST | /admin/users | Admin\User\Store | ユーザ作成 | admin |
| PUT | /admin/users/{id} | Admin\User\Update | ユーザ更新 | admin |

### 2.10 S3 認証情報管理

| メソッド | パス | Controller | 説明 | 権限 |
|---------|------|-----------|------|------|
| GET | /tenants/{tenant}/s3-credentials | S3Credential\Index | S3認証情報一覧 | admin, tenant_admin(自身) |
| POST | /tenants/{tenant}/s3-credentials | S3Credential\Store | S3認証情報作成 | admin, tenant_admin(自身) |
| GET | /tenants/{tenant}/s3-credentials/{id} | S3Credential\Show | S3認証情報詳細 (Secret表示) | admin, tenant_admin(自身) |
| DELETE | /tenants/{tenant}/s3-credentials/{id} | S3Credential\Destroy | S3認証情報削除 (無効化) | admin, tenant_admin(自身) |
| POST | /tenants/{tenant}/s3-credentials/{id}/rotate | S3Credential\Rotate | Secret Key ローテーション | admin, tenant_admin(自身) |

### 2.11 DNS 管理

| メソッド | パス | Controller | 説明 | 権限 |
|---------|------|-----------|------|------|
| GET | /admin/dns | Admin\Dns\Index | DNS レコード一覧 | admin |
| POST | /admin/dns | Admin\Dns\Store | DNS レコード追加 | admin |
| PUT | /admin/dns/{id} | Admin\Dns\Update | DNS レコード更新 | admin |
| DELETE | /admin/dns/{id} | Admin\Dns\Destroy | DNS レコード削除 | admin |
| POST | /admin/dns/reload | Admin\Dns\Reload | CoreDNS 設定リロード | admin |

### 2.12 VPS ゲートウェイ管理

| メソッド | パス | Controller | 説明 | 権限 |
|---------|------|-----------|------|------|
| GET | /admin/vps | Admin\Vps\Index | VPS ゲートウェイ一覧 | admin |
| POST | /admin/vps | Admin\Vps\Store | VPS 追加 (WireGuard設定自動生成) | admin |
| GET | /admin/vps/{id} | Admin\Vps\Show | VPS 詳細・接続状態 | admin |
| PUT | /admin/vps/{id} | Admin\Vps\Update | VPS 設定更新 | admin |
| DELETE | /admin/vps/{id} | Admin\Vps\Destroy | VPS 削除 (WireGuard設定除去) | admin |
| POST | /admin/vps/{id}/sync | Admin\Vps\Sync | VPS nginx設定同期 | admin |

---

## 3. 内部API（非同期操作用）

管理パネル内のJavaScript (Alpine.js等で最小限) から呼ばれる内部API。

| メソッド | パス | Controller | レスポンス |
|---------|------|-----------|----------|
| GET | /api/vms/{vmid}/status | Api\VmStatus | JSON { status, cpu, mem, uptime } |
| GET | /api/nodes/status | Api\NodeStatus | JSON [{ node, status, cpu, mem }] |
| GET | /api/dbaas/{id}/status | Api\DbaasStatus | JSON { status, uptime } |
| GET | /api/containers/{id}/status | Api\ContainerStatus | JSON { status, allocations } |

---

## 4. スニペットAPI (FastAPI) エンドポイント

各 Proxmox ノードで稼働するスニペット保存用API。

### 4.1 認証

```
Authorization: Bearer {SNIPPET_API_TOKEN}
```

- トークンは管理パネルとスニペットAPI間の共有シークレット
- 環境変数で設定

### 4.2 エンドポイント

| メソッド | パス | 説明 | リクエスト | レスポンス |
|---------|------|------|----------|----------|
| GET | /health | ヘルスチェック | - | `{"status": "ok"}` |
| POST | /snippets/{vm_id} | スニペット作成/更新 | SnippetCreateRequest | SnippetResponse |
| GET | /snippets/{vm_id} | スニペット取得 | - | SnippetResponse |
| DELETE | /snippets/{vm_id} | スニペット削除 | - | `{"status": "deleted"}` |
| GET | /snippets | スニペット一覧 | - | `[SnippetResponse]` |

### 4.3 データモデル

**SnippetCreateRequest:**
```json
{
  "user_data": "string (cloud-config YAML)",
  "network_config": "string (network YAML, optional)",
  "meta_data": "string (meta-data YAML, optional)"
}
```

**SnippetResponse:**
```json
{
  "vm_id": "100",
  "files": {
    "user_data": "/var/lib/vz/snippets/100-user.yaml",
    "network_config": "/var/lib/vz/snippets/100-network.yaml",
    "meta_data": "/var/lib/vz/snippets/100-meta.yaml"
  },
  "created_at": "2026-03-22T10:00:00Z",
  "updated_at": "2026-03-22T10:00:00Z"
}
```

### 4.4 バリデーションルール

| パラメータ | ルール |
|----------|--------|
| vm_id | 正の整数のみ (100-999999999) |
| user_data | 必須、最大1MB、YAML構文チェック |
| network_config | 任意、最大256KB、YAML構文チェック |
| meta_data | 任意、最大256KB、YAML構文チェック |

### 4.5 エラーレスポンス

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "user_data is required",
    "details": {}
  }
}
```

| HTTPステータス | コード | 説明 |
|--------------|--------|------|
| 400 | VALIDATION_ERROR | バリデーションエラー |
| 401 | UNAUTHORIZED | 認証失敗 |
| 404 | NOT_FOUND | スニペット not found |
| 500 | INTERNAL_ERROR | サーバエラー |

---

## 5. Proxmox API 利用エンドポイント一覧

管理パネルが利用する Proxmox VE API エンドポイントの完全一覧。

### 5.1 クラスタ

| 操作 | メソッド | エンドポイント |
|------|---------|--------------|
| クラスタ状態 | GET | /cluster/status |
| リソース一覧 | GET | /cluster/resources |

### 5.2 ノード

| 操作 | メソッド | エンドポイント |
|------|---------|--------------|
| ノード一覧 | GET | /nodes |
| ノード状態 | GET | /nodes/{node}/status |
| ストレージ一覧 | GET | /nodes/{node}/storage |
| ストレージ内容 | GET | /nodes/{node}/storage/{storage}/content |
| ネットワーク一覧 | GET | /nodes/{node}/network |

### 5.3 VM (QEMU)

| 操作 | メソッド | エンドポイント |
|------|---------|--------------|
| VM一覧 | GET | /nodes/{node}/qemu |
| VM設定取得 | GET | /nodes/{node}/qemu/{vmid}/config |
| VM作成 | POST | /nodes/{node}/qemu |
| VM設定変更 | PUT | /nodes/{node}/qemu/{vmid}/config |
| VM削除 | DELETE | /nodes/{node}/qemu/{vmid} |
| VM起動 | POST | /nodes/{node}/qemu/{vmid}/status/start |
| VM停止 | POST | /nodes/{node}/qemu/{vmid}/status/stop |
| VM再起動 | POST | /nodes/{node}/qemu/{vmid}/status/reboot |
| VM現在状態 | GET | /nodes/{node}/qemu/{vmid}/status/current |
| VMクローン | POST | /nodes/{node}/qemu/{vmid}/clone |
| VMリサイズ | PUT | /nodes/{node}/qemu/{vmid}/resize |
| VMスナップショット一覧 | GET | /nodes/{node}/qemu/{vmid}/snapshot |
| VMスナップショット作成 | POST | /nodes/{node}/qemu/{vmid}/snapshot |
| VNCプロキシ | POST | /nodes/{node}/qemu/{vmid}/vncproxy |
| Cloud-init再生成 | PUT | /nodes/{node}/qemu/{vmid}/cloudinit |

### 5.4 SDN (テナントネットワーク)

| 操作 | メソッド | エンドポイント |
|------|---------|--------------|
| VNet一覧 | GET | /cluster/sdn/vnets |
| VNet作成 | POST | /cluster/sdn/vnets |
| VNet削除 | DELETE | /cluster/sdn/vnets/{vnet} |
| Zone一覧 | GET | /cluster/sdn/zones |
| SDN適用 | PUT | /cluster/sdn |

---

## 6. Nomad API 利用エンドポイント一覧

| 操作 | メソッド | エンドポイント |
|------|---------|--------------|
| ジョブ一覧 | GET | /v1/jobs |
| ジョブ詳細 | GET | /v1/job/{job_id} |
| ジョブ登録/更新 | PUT | /v1/jobs |
| ジョブ停止 | DELETE | /v1/job/{job_id} |
| アロケーション一覧 | GET | /v1/job/{job_id}/allocations |
| アロケーション詳細 | GET | /v1/allocation/{alloc_id} |
| ログ取得 | GET | /v1/client/fs/logs/{alloc_id} |
| ノード一覧 | GET | /v1/nodes |
| Namespace作成 | PUT | /v1/namespace/{name} |
| Namespace一覧 | GET | /v1/namespaces |
| Quota作成 | PUT | /v1/quota |

---

## 7. S3 プロキシ API

カスタム Go 製 S3 互換プロキシ。テナント VM やサービスが S3 互換クライアント (aws cli, mc 等) 経由でアクセスする。

### 7.1 エンドポイント

| S3 操作 | メソッド | パス | 説明 |
|---------|---------|------|------|
| PutObject | PUT | /{bucket}/{key} | オブジェクトアップロード |
| GetObject | GET | /{bucket}/{key} | オブジェクトダウンロード |
| HeadObject | HEAD | /{bucket}/{key} | オブジェクトメタデータ取得 |
| DeleteObject | DELETE | /{bucket}/{key} | オブジェクト削除 |
| ListObjectsV2 | GET | /{bucket}?list-type=2 | オブジェクト一覧 |
| CreateMultipartUpload | POST | /{bucket}/{key}?uploads | マルチパートアップロード開始 |
| UploadPart | PUT | /{bucket}/{key}?partNumber=N&uploadId=X | パートアップロード |
| CompleteMultipartUpload | POST | /{bucket}/{key}?uploadId=X | マルチパートアップロード完了 |

### 7.2 認証

```
Authorization: AWS4-HMAC-SHA256 Credential={access_key}/{date}/{region}/s3/aws4_request, ...
```

- S3 プロキシが発行した内部 Access Key / Secret Key を使用
- AWS Signature V4 プロトコルに準拠
- `access_key` から管理 DB の `s3_credentials` テーブルを参照

### 7.3 アクセス制御

| チェック項目 | 内容 |
|------------|------|
| 認証情報有効性 | `is_active = true` であること |
| バケット制限 | リクエストのバケットが `allowed_bucket` と一致 |
| プレフィックス制限 | リクエストのキーが `allowed_prefix` で始まること |
| テナント隔離 | 他テナントのプレフィックスへのアクセスを拒否 |

### 7.4 エラーレスポンス

S3 互換の XML エラーレスポンスを返却。

```xml
<?xml version="1.0" encoding="UTF-8"?>
<Error>
  <Code>AccessDenied</Code>
  <Message>Access Denied</Message>
  <RequestId>xxx</RequestId>
</Error>
```

| HTTP ステータス | S3 エラーコード | 説明 |
|--------------|---------------|------|
| 403 | AccessDenied | 認証失敗 / アクセス拒否 |
| 404 | NoSuchKey | オブジェクトが存在しない |
| 400 | InvalidArgument | リクエストパラメータ不正 |
| 500 | InternalError | サーバ内部エラー |

---

## 8. ルーティング定義 (Laravel)

全 Controller は Single Action Controller (`__invoke`) として実装する。

```php
// routes/web.php
use App\Http\Controllers;

// 認証
Route::middleware('guest')->group(function () {
    Route::get('/login', Controllers\Auth\ShowLoginForm::class)->name('login');
    Route::post('/login', Controllers\Auth\Login::class);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', Controllers\Auth\Logout::class)->name('logout');

    // 2FA (任意)
    Route::get('/2fa/verify', Controllers\Auth\TwoFactor\Show::class)->name('2fa.verify');
    Route::post('/2fa/verify', Controllers\Auth\TwoFactor\Verify::class);
    Route::get('/2fa/setup', Controllers\Auth\TwoFactor\Setup::class)->name('2fa.setup');
    Route::post('/2fa/confirm', Controllers\Auth\TwoFactor\Confirm::class);
    Route::delete('/2fa/disable', Controllers\Auth\TwoFactor\Disable::class)->name('2fa.disable');

    Route::middleware('2fa.optional')->group(function () {
        // ダッシュボード
        Route::get('/', Controllers\Dashboard\Index::class)->name('dashboard');

        // テナント管理
        Route::get('/tenants', Controllers\Tenant\Index::class)->name('tenants.index');
        Route::get('/tenants/create', Controllers\Tenant\Create::class)->name('tenants.create');
        Route::post('/tenants', Controllers\Tenant\Store::class)->name('tenants.store');
        Route::get('/tenants/{tenant}', Controllers\Tenant\Show::class)->name('tenants.show');
        Route::get('/tenants/{tenant}/edit', Controllers\Tenant\Edit::class)->name('tenants.edit');
        Route::put('/tenants/{tenant}', Controllers\Tenant\Update::class)->name('tenants.update');
        Route::delete('/tenants/{tenant}', Controllers\Tenant\Destroy::class)->name('tenants.destroy');

        // VM管理
        Route::get('/vms', Controllers\Vm\Index::class)->name('vms.index');
        Route::get('/vms/create', Controllers\Vm\Create::class)->name('vms.create');
        Route::post('/vms', Controllers\Vm\Store::class)->name('vms.store');
        Route::get('/vms/{vmid}', Controllers\Vm\Show::class)->name('vms.show');
        Route::post('/vms/{vmid}/start', Controllers\Vm\Start::class)->name('vms.start');
        Route::post('/vms/{vmid}/stop', Controllers\Vm\Stop::class)->name('vms.stop');
        Route::post('/vms/{vmid}/reboot', Controllers\Vm\Reboot::class)->name('vms.reboot');
        Route::post('/vms/{vmid}/force-stop', Controllers\Vm\ForceStop::class)->name('vms.force-stop');
        Route::delete('/vms/{vmid}', Controllers\Vm\Destroy::class)->name('vms.destroy');
        Route::get('/vms/{vmid}/console', Controllers\Vm\Console::class)->name('vms.console');
        Route::post('/vms/{vmid}/snapshot', Controllers\Vm\Snapshot::class)->name('vms.snapshot');
        Route::post('/vms/{vmid}/resize', Controllers\Vm\Resize::class)->name('vms.resize');

        // DBaaS
        Route::get('/dbaas', Controllers\Dbaas\Index::class)->name('dbaas.index');
        Route::get('/dbaas/create', Controllers\Dbaas\Create::class)->name('dbaas.create');
        Route::post('/dbaas', Controllers\Dbaas\Store::class)->name('dbaas.store');
        Route::get('/dbaas/{id}', Controllers\Dbaas\Show::class)->name('dbaas.show');
        Route::post('/dbaas/{id}/start', Controllers\Dbaas\Start::class)->name('dbaas.start');
        Route::post('/dbaas/{id}/stop', Controllers\Dbaas\Stop::class)->name('dbaas.stop');
        Route::delete('/dbaas/{id}', Controllers\Dbaas\Destroy::class)->name('dbaas.destroy');
        Route::post('/dbaas/{id}/backup', Controllers\Dbaas\Backup::class)->name('dbaas.backup');
        Route::get('/dbaas/{id}/backups', Controllers\Dbaas\Backups::class)->name('dbaas.backups');
        Route::post('/dbaas/{id}/restore', Controllers\Dbaas\Restore::class)->name('dbaas.restore');
        Route::post('/dbaas/{id}/upgrade', Controllers\Dbaas\Upgrade::class)->name('dbaas.upgrade');
        Route::get('/dbaas/{id}/credentials', Controllers\Dbaas\Credentials::class)->name('dbaas.credentials');

        // コンテナ
        Route::get('/containers', Controllers\Container\Index::class)->name('containers.index');
        Route::get('/containers/deploy', Controllers\Container\Create::class)->name('containers.create');
        Route::post('/containers', Controllers\Container\Store::class)->name('containers.store');
        Route::get('/containers/{id}', Controllers\Container\Show::class)->name('containers.show');
        Route::post('/containers/{id}/restart', Controllers\Container\Restart::class)->name('containers.restart');
        Route::post('/containers/{id}/scale', Controllers\Container\Scale::class)->name('containers.scale');
        Route::delete('/containers/{id}', Controllers\Container\Destroy::class)->name('containers.destroy');
        Route::get('/containers/{id}/logs', Controllers\Container\Logs::class)->name('containers.logs');

        // ネットワーク
        Route::get('/networks', Controllers\Network\Index::class)->name('networks.index');
        Route::get('/networks/create', Controllers\Network\Create::class)->name('networks.create');
        Route::post('/networks', Controllers\Network\Store::class)->name('networks.store');
        Route::get('/networks/{id}', Controllers\Network\Show::class)->name('networks.show');
        Route::delete('/networks/{id}', Controllers\Network\Destroy::class)->name('networks.destroy');

        // 監視
        Route::get('/monitoring', Controllers\Monitoring\Index::class)->name('monitoring');
        Route::get('/monitoring/grafana-url', Controllers\Monitoring\GrafanaUrl::class)->name('monitoring.grafana-url');

        // S3 認証情報管理
        Route::prefix('tenants/{tenant}/s3-credentials')->name('s3-credentials.')->group(function () {
            Route::get('/', Controllers\S3Credential\Index::class)->name('index');
            Route::post('/', Controllers\S3Credential\Store::class)->name('store');
            Route::get('/{id}', Controllers\S3Credential\Show::class)->name('show');
            Route::delete('/{id}', Controllers\S3Credential\Destroy::class)->name('destroy');
            Route::post('/{id}/rotate', Controllers\S3Credential\Rotate::class)->name('rotate');
        });

        // 内部API
        Route::prefix('api')->group(function () {
            Route::get('/vms/{vmid}/status', Controllers\Api\VmStatus::class);
            Route::get('/nodes/status', Controllers\Api\NodeStatus::class);
            Route::get('/dbaas/{id}/status', Controllers\Api\DbaasStatus::class);
            Route::get('/containers/{id}/status', Controllers\Api\ContainerStatus::class);
        });

        // 管理者専用
        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/nodes', Controllers\Admin\Node\Index::class)->name('nodes.index');
            Route::post('/nodes', Controllers\Admin\Node\Store::class)->name('nodes.store');
            Route::put('/nodes/{id}', Controllers\Admin\Node\Update::class)->name('nodes.update');

            Route::get('/users', Controllers\Admin\User\Index::class)->name('users.index');
            Route::post('/users', Controllers\Admin\User\Store::class)->name('users.store');
            Route::put('/users/{id}', Controllers\Admin\User\Update::class)->name('users.update');

            // DNS 管理
            Route::prefix('dns')->name('dns.')->group(function () {
                Route::get('/', Controllers\Admin\Dns\Index::class)->name('index');
                Route::post('/', Controllers\Admin\Dns\Store::class)->name('store');
                Route::put('/{id}', Controllers\Admin\Dns\Update::class)->name('update');
                Route::delete('/{id}', Controllers\Admin\Dns\Destroy::class)->name('destroy');
                Route::post('/reload', Controllers\Admin\Dns\Reload::class)->name('reload');
            });
        });
    });
});
```
