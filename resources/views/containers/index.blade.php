<x-layouts::app :title="__('コンテナ一覧')">
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">コンテナ一覧</flux:heading>
            <flux:button href="{{ route('containers.create') }}" variant="primary">新規デプロイ</flux:button>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>ID</flux:table.column>
                <flux:table.column>テナント</flux:table.column>
                <flux:table.column>ジョブID</flux:table.column>
                <flux:table.column>イメージ</flux:table.column>
                <flux:table.column>ドメイン</flux:table.column>
                <flux:table.column>レプリカ</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($jobs as $job)
                    <flux:table.row>
                        <flux:table.cell>{{ $job->getId() }}</flux:table.cell>
                        <flux:table.cell>{{ $tenantNames->get($job->getTenantId()) ?? 'N/A' }}</flux:table.cell>
                        <flux:table.cell>{{ $job->getNomadJobId() }}</flux:table.cell>
                        <flux:table.cell>{{ $job->getImage() }}</flux:table.cell>
                        <flux:table.cell>{{ $job->getDomain() ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $job->getReplicas() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button href="{{ route('containers.show', $job->getId()) }}" size="sm" variant="ghost">詳細</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-zinc-500">コンテナジョブがありません。</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div>{{ $jobs->links() }}</div>
    </div>
</x-layouts::app>
