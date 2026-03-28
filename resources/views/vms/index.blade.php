<x-layouts::app :title="__('VM 一覧')">
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">VM 一覧</flux:heading>
            <flux:button href="{{ route('vms.create') }}" variant="primary">
                新規作成
            </flux:button>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>VMID</flux:table.column>
                <flux:table.column>名前</flux:table.column>
                <flux:table.column>ノード</flux:table.column>
                <flux:table.column>テナント</flux:table.column>
                <flux:table.column>ステータス</flux:table.column>
                <flux:table.column>CPU</flux:table.column>
                <flux:table.column>メモリ</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($vms as $vm)
                    @php
                        $meta = $metas->get($vm['vmid']);
                        $memUsed = isset($vm['mem']) ? round($vm['mem'] / 1024 / 1024) : 0;
                        $memTotal = isset($vm['maxmem']) ? round($vm['maxmem'] / 1024 / 1024) : 0;
                    @endphp
                    <flux:table.row>
                        <flux:table.cell>{{ $vm['vmid'] }}</flux:table.cell>
                        <flux:table.cell>{{ $vm['name'] ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $vm['node'] ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $meta?->getTenantName() ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($meta)
                                <x-vm-status-badge :status="$meta->getProvisioningStatus()" />
                            @else
                                <flux:badge variant="zinc">{{ $vm['status'] ?? '不明' }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $vm['cpu'] ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($memTotal > 0)
                                <x-resource-meter :used="$memUsed" :total="$memTotal" unit="MB" />
                            @else
                                -
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button href="{{ route('vms.show', $vm['vmid']) }}" size="sm" variant="ghost">
                                詳細
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-zinc-500">
                            VMが存在しません。
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</x-layouts::app>
