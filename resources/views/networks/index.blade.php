<x-layouts::app :title="__('ネットワーク管理')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">ネットワーク管理</flux:heading>
            <flux:button href="{{ route('networks.create') }}" variant="primary" icon="plus">作成</flux:button>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>テナント</flux:table.column>
                <flux:table.column>VNet</flux:table.column>
                <flux:table.column>VNI</flux:table.column>
                <flux:table.column>CIDR</flux:table.column>
                <flux:table.column>Proxmox</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($networks as $network)
                    <flux:table.row>
                        <flux:table.cell>{{ $network['tenant_name'] }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $network['vnet_name'] ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $network['vni'] ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $network['network_cidr'] ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge variant="{{ $network['exists_on_proxmox'] ? 'lime' : 'zinc' }}">
                                {{ $network['exists_on_proxmox'] ? 'matched' : 'missing' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button href="{{ route('networks.show', $network['tenant_id']) }}" size="sm" variant="ghost">詳細</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500">ネットワーク情報がありません。</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</x-layouts::app>
