# Phase 9: スニペットサイドカー API & Lib/Snippet クライアント

## 概要

Proxmox VE の API は Cloud-init スニペットファイルをストレージに直接書き込む機能を持たない。  
各 Proxmox ノードに Python (FastAPI) 製のサイドカー API コンテナを稼働させて、  
スニペットファイルの書き込みを代行させる。Laravel 側からは `Lib/Snippet/SnippetClient` で呼び出す。

---

## チェックポイント

### 9-1. スニペットサイドカー API (Python / FastAPI)

> コードは `snippet-api/` ディレクトリに配置する

- [ ] `snippet-api/main.py` — FastAPI アプリ本体
  - `POST /snippets` — スニペットファイル書き込み
    - リクエスト：`{ "filename": "vm-100-user-data.yaml", "content": "..." }`
    - 認証：Bearer トークン（`SNIPPET_API_TOKEN` 環境変数で検証）
    - 書き込み先：Proxmox スニペットストレージパス（`SNIPPET_PATH` 環境変数）
  - `DELETE /snippets/{filename}` — スニペットファイル削除
  - `GET /health` — ヘルスチェック（認証不要）
- [ ] `snippet-api/requirements.txt` — `fastapi`, `uvicorn`, `python-dotenv`
- [ ] `snippet-api/Dockerfile` — Python 3.12 slim ベース
- [ ] `snippet-api/.env.example` — `SNIPPET_API_TOKEN`, `SNIPPET_PATH`
- [ ] セキュリティ：ファイル名にパストラバーサル対策（`..` 含む名前を拒否）

### 9-2. Docker Compose 統合

- [ ] `snippet-api/docker-compose.yml`（開発用スタンドアロン起動確認用）
- [ ] 各 Proxmox ノードへのデプロイ手順を `docs/deployment-operations.md` に追記

### 9-3. Lib/Snippet クライアント (Laravel)

- [ ] `App\Lib\Snippet\SnippetClient` 作成
  - コンストラクタ：`string $baseUrl, string $token`
  - `upload(string $filename, string $content): void`
    - `POST /snippets` を呼び出す
    - 失敗時は `SnippetApiException` をスロー
  - `delete(string $filename): void`
    - `DELETE /snippets/{filename}`
  - `App\Lib\Snippet\Exceptions\SnippetApiException`

### 9-4. サービスプロバイダ統合

- [ ] `AppServiceProvider` に `SnippetClient` のバインディング追加
  - `ProxmoxNode` モデルから `snippet_api_url`・`snippet_api_token_encrypted` を取得して注入
  - ノードごとに異なるクライアントを返すファクトリメソッド形式

### 9-5. VmService との統合

- [ ] Phase 4 の `VmService::provisionVm()` 内の「スニペットアップロード」ステップを実装
  - `SnippetClient::upload()` で user-data ファイルをアップロード
  - クローン完了後、VM 削除時に `SnippetClient::delete()` でクリーンアップ

### 9-6. テスト

- [ ] Unit: `SnippetClient::upload()` が正しいエンドポイント・ヘッダで HTTP リクエストを送ること（Http::fake）
- [ ] Unit: パストラバーサル攻撃パターン（`../etc/passwd`）のファイル名を拒否すること（Python 側テスト）
- [ ] Feature: VM プロビジョニングフローでスニペットアップロードが呼ばれること

---

## 完了条件

- FastAPI サーバが起動し、`POST /snippets` で指定パスにファイルが書き込まれること
- ファイル名に `..` を含むリクエストが 400 で拒否されること
- Laravel `SnippetClient` から `Http::fake` で動作確認できること
- `php artisan test --compact` で全テストがパス
- `vendor/bin/pint --dirty --format agent` でスタイル違反なし
