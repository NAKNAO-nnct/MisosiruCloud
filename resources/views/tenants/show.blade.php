<x-layouts::app :title="$tenant->name">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('tenants.index') }}" variant="ghost" size="sm" icon="arrow-left">
                    一覧へ戻る
                </flux:button>
                <flux:heading size="xl">{{ $tenant->name }}</flux:heading>
                <flux:badge variant="{{ $tenant->status->value === 'active' ? 'lime' : 'red' }}">
                    {{ $tenant->status->value }}
                </flux:badge>
            </div>
            <div class="flex gap-2">
                <flux:button href="{{ route('tenants.edit', $tenant) }}" variant="ghost" size="sm">
                    編集
                </flux:button>
                <form method="POST" action="{{ route('tenants.destroy', $tenant) }}"
                    onsubmit="return confirm('このテナントを削除しますか？')">
                    @csrf
                    @method('DELETE')
                    <flux:button type="submit" variant="danger" size="sm">削除</flux:button>
                </form>
            </div>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <flux:card>
                <flux:heading size="lg">基本情報</flux:heading>
                <dl class="mt-2 flex flex-col gap-2 text-sm">
                    <div class="flex justify-between"><dt class="text-zinc-500">UUID</dt><dd>{{ $tenant->uuid }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">スラッグ</dt><dd>{{ $tenant->slug }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">VNet名</dt><dd>{{ $tenant->vnet_name ?? '-' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">VNI</dt><dd>{{ $tenant->vni ?? '-' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">ネットワーク</dt><dd>{{ $tenant->network_cidr ?? '-' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">Nomad名前空間</dt><dd>{{ $tenant->nomad_namespace ?? '-' }}</dd></div>
                </dl>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">S3認証情報</flux:heading>
                    <flux:button href="{{ route('tenants.s3-credentials.index', $tenant) }}" size="sm" variant="ghost">
                        管理
                    </flux:button>
                </div>
                <p class="mt-2 text-sm text-zinc-500">{{ $tenant->s3Credentials->count() }} 件</p>
            </flux:card>
        </div>

        <flux:card>
            <flux:heading size="lg">VM一覧</flux:heading>
            <p class="mt-2 text-sm text-zinc-500">{{ $tenant->vmMetas->count() }} 件</p>
        </flux:card>

        <flux:card>
            <flux:heading size="lg">データベース</flux:heading>
            <p class="mt-2 text-sm text-zinc-500">{{ $tenant->databaseInstances->count() }} 件</p>
        </flux:card>
    </div>
</x-layouts::app>
