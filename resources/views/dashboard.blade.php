<x-layouts::app :title="__('ダッシュボード')">
    <div class="flex flex-col gap-4">
        <flux:heading size="xl">ダッシュボード</flux:heading>
        <flux:text>{{ config('app.name') }} へようこそ。</flux:text>

        <flux:separator class="my-2" />

        <div class="flex items-center justify-between">
            <flux:heading size="lg">Proxmox クラスタ</flux:heading>
            <flux:button href="{{ route('proxmox-clusters.index') }}" variant="ghost" size="sm" icon="server">
                管理画面を開く
            </flux:button>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-zinc-500">登録クラスタ数</flux:text>
                <flux:heading size="xl">{{ $clusterCount }}</flux:heading>
            </div>

            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-zinc-500">有効クラスタ数</flux:text>
                <flux:heading size="xl">{{ $activeClusterCount }}</flux:heading>
            </div>

            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-zinc-500">有効クラスタ CPU使用率 (平均)</flux:text>
                <flux:heading size="xl">
                    {{ $activeClusterCpuPercent !== null ? number_format($activeClusterCpuPercent, 1) . '%' : '-' }}
                </flux:heading>
            </div>
        </div>

        @if ($clusterFetchErrors !== [])
            <flux:callout variant="warning" icon="exclamation-triangle">
                一部クラスタのメトリクス取得に失敗しました（権限不足やトークン不整合の可能性があります）。
            </flux:callout>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>名称</flux:table.column>
                <flux:table.column>ホスト名 / IP</flux:table.column>
                <flux:table.column>状態</flux:table.column>
                <flux:table.column>CPU使用率</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($clusters as $cluster)
                    <flux:table.row>
                        <flux:table.cell>{{ $cluster->getName() }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $cluster->getHostname() }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($cluster->isActive())
                                <flux:badge color="green" icon="check-circle">アクティブ</flux:badge>
                            @else
                                <flux:badge color="zinc">無効</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ isset($clusterCpuPercents[$cluster->getId()]) ? number_format((float) $clusterCpuPercents[$cluster->getId()], 1) . '%' : '-' }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500">
                            クラスタ接続がまだ登録されていません。
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="mt-2 flex items-center justify-between">
            <flux:heading size="md">ノード別 CPU使用率</flux:heading>
            <flux:text class="text-zinc-500">アクティブクラスタから取得</flux:text>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>クラスタ</flux:table.column>
                <flux:table.column>ノード</flux:table.column>
                <flux:table.column>状態</flux:table.column>
                <flux:table.column>CPU使用率</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($nodeCpuUsages as $node)
                    <flux:table.row>
                        <flux:table.cell>{{ $node['cluster'] }}</flux:table.cell>
                        <flux:table.cell>{{ $node['node'] }}</flux:table.cell>
                        <flux:table.cell>{{ $node['status'] }}</flux:table.cell>
                        <flux:table.cell>{{ number_format((float) $node['cpuPercent'], 1) }}%</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500">
                            ノードCPU情報を取得できませんでした。
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</x-layouts::app>
