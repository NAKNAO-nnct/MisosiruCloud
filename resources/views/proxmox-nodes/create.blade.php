<x-layouts::app :title="__('Proxmox クラスタ追加')">
    <div class="flex max-w-2xl flex-col gap-6">
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('proxmox-clusters.index') }}" variant="ghost" size="sm" icon="arrow-left">一覧へ戻る</flux:button>
            <flux:heading size="xl">Proxmox クラスタ追加</flux:heading>
        </div>

        <form method="POST" action="{{ route('proxmox-clusters.store') }}" class="flex flex-col gap-4">
            @csrf

            <flux:field>
                <flux:label>名称</flux:label>
                <flux:input name="name" value="{{ old('name') }}" placeholder="pve01" required />
                <flux:description>一意の識別名（例：pve01）</flux:description>
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>ホスト名 / IP</flux:label>
                <flux:input name="hostname" value="{{ old('hostname') }}" placeholder="192.168.1.10:8006" required />
                <flux:description>ポート番号を含む形式（例：192.168.1.10:8006）</flux:description>
                <flux:error name="hostname" />
            </flux:field>

            <flux:field>
                <flux:label>API トークン ID</flux:label>
                <flux:input name="api_token_id" value="{{ old('api_token_id') }}" placeholder="root@pam!mytoken" required />
                <flux:description>Proxmox の API トークン ID（例：user@realm!tokenname）</flux:description>
                <flux:error name="api_token_id" />
            </flux:field>

            <flux:field>
                <flux:label>API トークンシークレット</flux:label>
                <flux:input type="password" name="api_token_secret" autocomplete="off" required />
                <flux:description>Proxmox で生成したトークンシークレット（UUID 形式）</flux:description>
                <flux:error name="api_token_secret" />
            </flux:field>

            <flux:field>
                <flux:label>スニペット API URL</flux:label>
                <flux:input name="snippet_api_url" value="{{ old('snippet_api_url') }}" placeholder="http://192.168.1.10:8080" required />
                <flux:error name="snippet_api_url" />
            </flux:field>

            <flux:field>
                <flux:label>スニペット API トークン</flux:label>
                <flux:input type="password" name="snippet_api_token" autocomplete="off" required />
                <flux:error name="snippet_api_token" />
            </flux:field>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">登録</flux:button>
                <flux:button href="{{ route('proxmox-clusters.index') }}" variant="ghost">キャンセル</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
