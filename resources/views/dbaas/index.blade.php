<x-layouts::app :title="__('DBaaS 一覧')">
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">DBaaS 一覧</flux:heading>
            <flux:button href="{{ route('dbaas.create') }}" variant="primary">新規作成</flux:button>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>ID</flux:table.column>
                <flux:table.column>テナント</flux:table.column>
                <flux:table.column>種別</flux:table.column>
                <flux:table.column>バージョン</flux:table.column>
                <flux:table.column>ポート</flux:table.column>
                <flux:table.column>ステータス</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($databases as $database)
                    <flux:table.row>
                        <flux:table.cell>{{ $database->getId() }}</flux:table.cell>
                        <flux:table.cell>{{ $tenantNames->get($database->getTenantId()) ?? 'N/A' }}</flux:table.cell>
                        <flux:table.cell>{{ $database->getDbType()->value }}</flux:table.cell>
                        <flux:table.cell>{{ $database->getDbVersion() }}</flux:table.cell>
                        <flux:table.cell>{{ $database->getPort() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge variant="{{ $database->getStatus() === 'running' ? 'lime' : ($database->getStatus() === 'stopped' ? 'zinc' : 'amber') }}">
                                {{ $database->getStatus() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button href="{{ route('dbaas.show', $database->getId()) }}" size="sm" variant="ghost">詳細</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-zinc-500">DBインスタンスがありません。</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div>{{ $databases->links() }}</div>
    </div>
</x-layouts::app>
