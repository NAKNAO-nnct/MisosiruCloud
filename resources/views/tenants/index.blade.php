<x-layouts::app :title="__('テナント一覧')">
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">テナント一覧</flux:heading>
            <flux:button href="{{ route('tenants.create') }}" variant="primary">
                新規作成
            </flux:button>
        </div>

        <form method="GET" action="{{ route('tenants.index') }}">
            <flux:input
                name="search"
                value="{{ request('search') }}"
                placeholder="名前またはスラッグで検索..."
                class="max-w-sm"
            />
        </form>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>名前</flux:table.column>
                <flux:table.column>スラッグ</flux:table.column>
                <flux:table.column>ステータス</flux:table.column>
                <flux:table.column>VNet</flux:table.column>
                <flux:table.column>作成日</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($tenants as $tenant)
                    <flux:table.row>
                        <flux:table.cell>{{ $tenant->getName() }}</flux:table.cell>
                        <flux:table.cell>{{ $tenant->getSlug() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge variant="{{ $tenant->getStatus()->value === 'active' ? 'lime' : 'red' }}">
                                {{ $tenant->getStatus()->value }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $tenant->getVnetName() ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $tenant->getCreatedAt()?->format('Y/m/d') ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button href="{{ route('tenants.show', $tenant->getId()) }}" size="sm" variant="ghost">
                                詳細
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500">
                            テナントが存在しません。
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div>
            {{ $tenants->links() }}
        </div>
    </div>
</x-layouts::app>
