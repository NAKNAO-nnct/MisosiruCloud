<x-layouts::app :title="__('ネットワーク詳細')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('networks.index') }}" variant="ghost" size="sm" icon="arrow-left">一覧へ戻る</flux:button>
            <flux:heading size="xl">{{ $tenant->getName() }} ネットワーク</flux:heading>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <flux:card>
                <flux:heading size="lg">ネットワーク情報</flux:heading>
                <dl class="mt-3 flex flex-col gap-2 text-sm">
                    <div class="flex justify-between"><dt class="text-zinc-500">テナント</dt><dd>{{ $tenant->getName() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">スラッグ</dt><dd>{{ $tenant->getSlug() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">VNet</dt><dd class="font-mono">{{ $tenant->getVnetName() ?? '-' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">VNI</dt><dd>{{ $tenant->getVni() ?? '-' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">CIDR</dt><dd class="font-mono">{{ $tenant->getNetworkCidr() ?? '-' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">Proxmox zone</dt><dd>{{ is_array($network) ? ($network['proxmox_zone'] ?? '-') : '-' }}</dd></div>
                </dl>
            </flux:card>

            <flux:card>
                <flux:heading size="lg">操作</flux:heading>
                <form method="POST" action="{{ route('networks.destroy', $tenant->getId()) }}" class="mt-3" onsubmit="return confirm('このネットワークを削除しますか？')">
                    @csrf
                    @method('DELETE')
                    <flux:button type="submit" variant="danger">削除</flux:button>
                </form>
            </flux:card>
        </div>

        <flux:card>
            <flux:heading size="lg">接続VM</flux:heading>
            <flux:table class="mt-3">
                <flux:table.columns>
                    <flux:table.column>VMID</flux:table.column>
                    <flux:table.column>ラベル</flux:table.column>
                    <flux:table.column>ノード</flux:table.column>
                    <flux:table.column>状態</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($vms as $vm)
                        <flux:table.row>
                            <flux:table.cell class="font-mono">{{ $vm->getProxmoxVmid() }}</flux:table.cell>
                            <flux:table.cell>{{ $vm->getLabel() }}</flux:table.cell>
                            <flux:table.cell>{{ $vm->getProxmoxNode() }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge variant="{{ $vm->getProvisioningStatus()->value === 'ready' ? 'lime' : 'zinc' }}">
                                    {{ $vm->getProvisioningStatus()->value }}
                                </flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center text-zinc-500">接続VMはありません。</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
</x-layouts::app>
