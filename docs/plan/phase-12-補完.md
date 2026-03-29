# Phase 12: 既存フェーズ補完 (Admin ユーザ管理・Nomad 拡張・Snippet API 修正)

## 概要

差分監査で発見された、既存フェーズに不足している機能を補完する。

1. **Admin ユーザ管理 (CRUD)** — api-design.md セクション 2.9 に定義されているが、どのフェーズにも含まれていなかった
2. **Nomad Allocation / Node / Quota リソース** — Phase 6 から欠落していたリソースクラス
3. **Snippet API 仕様修正** — Phase 9 と api-design.md の不一致を解消

## 現在の判定

✅ 完了（12-1 〜 12-8 すべて実装・テスト済み）

---

## 出典（設計ドキュメント）

- [api-design.md セクション 2.9](../api-design.md) — Admin\User ルート定義
- [detailed-design.md](../detailed-design.md) — `Lib/Nomad/Resources/Allocation.php`, `Node.php` の記載
- [api-design.md セクション 6](../api-design.md) — Nomad API 一覧（Quota 含む）
- [api-design.md セクション 4](../api-design.md) — スニペット API 正確な仕様

---

## チェックポイント

### 12-1. Admin ユーザ管理

#### コントローラ（admin のみ）

- [x] `Admin\User\Index` — ユーザ一覧（ロールバッジ、テナント紐付け表示）
- [x] `Admin\User\Store` — ユーザ作成（ロール選択、テナント割り当て）
- [x] `Admin\User\Update` — ユーザ更新（ロール変更、テナント変更）

#### Form Request

- [x] `App\Http\Requests\Admin\CreateUserRequest`
  - `name`: required, string, max:255
  - `email`: required, email, unique:users
  - `password`: required, min:8, confirmed
  - `role`: required, in:admin,tenant_admin,tenant_member
  - `tenant_ids`: nullable, array（テナント割り当て）
- [x] `App\Http\Requests\Admin\UpdateUserRequest`
  - `name`, `email`, `role`, `tenant_ids`（password は任意）

#### View

- [x] `resources/views/admin/users/index.blade.php`（ユーザ一覧、ロールバッジ、テナント紐付け）
- [x] `resources/views/admin/users/create.blade.php`（ユーザ作成フォーム）
- [x] `resources/views/admin/users/edit.blade.php`（ユーザ編集フォーム）

#### ルーティング

- [x] `GET /admin/users` → `Admin\User\Index` (admin ミドルウェア)
- [x] `POST /admin/users` → `Admin\User\Store`
- [x] `PUT /admin/users/{id}` → `Admin\User\Update`

#### テスト

- [x] Feature: ユーザ一覧にロール・テナント情報が表示されること
- [x] Feature: ユーザ作成時のバリデーション（email 一意性等）
- [x] Feature: admin 以外のユーザがアクセスできないこと
- [x] Feature: ユーザのロール変更が正しく反映されること

---

### 12-2. Nomad Allocation リソース（Phase 6 補完）

- [x] `App\Lib\Nomad\Resources\Allocation` 作成
  - `getAllocation(string $allocId): array`
    - `GET /v1/allocation/{alloc_id}`
  - `getAllocationLogs(string $allocId, string $taskName, string $logType = 'stdout'): string`
    - `GET /v1/client/fs/logs/{alloc_id}`

### 12-3. Nomad Node リソース（Phase 6 補完）

- [x] `App\Lib\Nomad\Resources\Node` 作成
  - `listNodes(): array`
    - `GET /v1/nodes`

### 12-4. Nomad Quota リソース（Phase 6 補完）

- [x] Quota 管理メソッドの追加（`Resources\Quota`）
  - `createQuota(array $spec): array`
    - `PUT /v1/quota`
- [x] Namespace 初回作成時に Nomad Quota を設定する処理を `ContainerService` に追加

### 12-5. NomadApi 統合エントリポイント更新

- [x] `NomadApi` に `allocation()`, `node()` エントリポイントを追加
  - `allocation(): Allocation`
  - `node(): Node`

### 12-6. Nomad テスト

- [x] Unit/Feature: `Allocation::getAllocation()` の HTTP リクエスト構造テスト（Http::fake）
- [x] Unit/Feature: `Node::listNodes()` のレスポンスマッピングテスト
- [x] Unit/Feature: `Quota::createQuota()` のリクエスト構造テスト

---

### 12-7. Snippet API 仕様修正 (Phase 9 補完)

> Phase 9 の仕様を api-design.md の正しい仕様に合わせて修正する。

#### エンドポイント修正

Phase 9 (現行):
```
POST /snippets  → { "filename": "...", "content": "..." }
DELETE /snippets/{filename}
```

api-design.md (正):
```
POST /snippets/{vm_id}   → { "user_data": "...", "network_config": "...", "meta_data": "..." }
GET  /snippets/{vm_id}   → SnippetResponse
DELETE /snippets/{vm_id}  → { "status": "deleted" }
GET  /snippets            → [SnippetResponse]
```

- [x] `snippet-api/main.py` のエンドポイントを `POST /snippets/{vm_id}` 形式に修正
- [x] リクエストモデルを `SnippetCreateRequest` に修正
  - `user_data`: 必須、最大 1MB、YAML 構文チェック
  - `network_config`: 任意、最大 256KB、YAML 構文チェック
  - `meta_data`: 任意、最大 256KB、YAML 構文チェック
- [x] レスポンスモデルを `SnippetResponse` に修正
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
- [x] `GET /snippets/{vm_id}` — スニペット取得エンドポイント追加
- [x] `GET /snippets` — スニペット一覧エンドポイント追加
- [x] `vm_id` バリデーション: 正の整数のみ (100-999999999)
- [x] エラーレスポンス形式の統一
  ```json
  { "error": { "code": "VALIDATION_ERROR", "message": "...", "details": {} } }
  ```

#### Snippet API 内部構成（detailed-design.md セクション 4.2 準拠）

- [x] `snippet-api/config.py` — 設定管理
- [x] `snippet-api/models/snippet.py` — リクエスト/レスポンスモデル
- [x] `snippet-api/routes/snippets.py` — ルート定義
- [x] `snippet-api/services/file_service.py` — ファイル入出力サービス

#### Laravel 側 SnippetClient 修正

- [x] `App\Lib\Snippet\SnippetClient::upload()` を新仕様に合わせて修正
  - `upload(int $vmId, string $userData, ?string $networkConfig = null, ?string $metaData = null): void`
  - `POST /snippets/{vm_id}` に `{ "user_data": ..., "network_config": ..., "meta_data": ... }` を送信
- [x] `App\Lib\Snippet\SnippetClient::get()` 追加
  - `get(int $vmId): array` — `GET /snippets/{vm_id}`
- [x] `App\Lib\Snippet\SnippetClient::delete()` を `vm_id` ベースに修正

#### Snippet テスト

- [x] Unit (Python): YAML 構文チェックの正常系・異常系
- [x] Unit (Python): `vm_id` が範囲外 (0, 負数, 1000000000) の場合に 400 エラー
- [x] Unit (Python): `user_data` サイズ超過 (>1MB) の場合に 400 エラー
- [x] Unit (Laravel): `SnippetClient::upload()` が新しいエンドポイント・ペイロードで HTTP リクエストを送ること

---

### 12-8. Blade コンポーネント補完

- [x] `resources/views/components/action-button.blade.php` 作成
  - detailed-design.md のディレクトリ構成に記載あり

---

## 完了条件

- ユーザ管理 CRUD が管理画面から利用でき、admin のみアクセス可能なこと
- Nomad Allocation / Node / Quota の API コールが正しいエンドポイントに送られること（Http::fake）
- スニペット API が api-design.md の仕様に準拠していること
- `php artisan test --compact` で全テストがパス
- `vendor/bin/pint --dirty --format agent` でスタイル違反なし
