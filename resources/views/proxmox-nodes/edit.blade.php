<x-layouts::app :title="__('Proxmox クラスタ編集')">
    <div class="flex max-w-2xl flex-col gap-6">
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('proxmox-clusters.index') }}" variant="ghost" size="sm" icon="arrow-left">一覧へ戻る</flux:button>
            <flux:heading size="xl">Proxmox クラスタ編集：{{ $node->getName() }}</flux:heading>
        </div>

        <form method="POST" action="{{ route('proxmox-clusters.update', $node->getId()) }}" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            <flux:field>
                <flux:label>名称</flux:label>
                <flux:input name="name" value="{{ old('name', $node->getName()) }}" placeholder="pve01" required />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>ホスト名 / IP</flux:label>
                <flux:input name="hostname" value="{{ old('hostname', $node->getHostname()) }}" placeholder="192.168.1.10:8006" required />
                <flux:description>ポート番号を含む形式（例：192.168.1.10:8006）</flux:description>
                <flux:error name="hostname" />
            </flux:field>

            <flux:field>
                <flux:label>API トークン ID</flux:label>
                <flux:input name="api_token_id" value="{{ old('api_token_id', $node->getApiTokenId()) }}" placeholder="root@pam!mytoken" required />
                <flux:error name="api_token_id" />
            </flux:field>

            <flux:field>
                <flux:label>API トークンシークレット（変更する場合のみ入力）</flux:label>
                <flux:input type="password" name="api_token_secret" autocomplete="off" />
                <flux:description>空白のままにすると変更されません。</flux:description>
                <flux:error name="api_token_secret" />
            </flux:field>

            <flux:field>
                <flux:label>スニペット API URL</flux:label>
                <flux:input name="snippet_api_url" value="{{ old('snippet_api_url', $node->getSnippetApiUrl()) }}" required />
                <flux:error name="snippet_api_url" />
            </flux:field>

            <flux:field>
                <flux:label>スニペット API トークン（変更する場合のみ入力）</flux:label>
                <flux:input type="password" name="snippet_api_token" autocomplete="off" />
                <flux:description>空白のままにすると変更されません。</flux:description>
                <flux:error name="snippet_api_token" />
            </flux:field>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">更新</flux:button>
                <flux:button href="{{ route('proxmox-clusters.index') }}" variant="ghost">キャンセル</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
