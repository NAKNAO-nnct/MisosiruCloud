<x-layouts::app :title="__('VPS ゲートウェイ管理')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">VPS ゲートウェイ管理</flux:heading>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        <flux:card>
            <flux:heading size="lg">新規登録</flux:heading>
            <form method="POST" action="{{ route('vps-gateways.store') }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                @csrf
                <flux:input name="name" label="名称" value="{{ old('name') }}" required />
                <flux:input name="global_ip" label="Global IP" value="{{ old('global_ip') }}" required />
                <flux:input name="wireguard_port" label="WireGuard Port" type="number" min="1" max="65535" value="{{ old('wireguard_port', 51820) }}" required />
                <flux:input name="wireguard_public_key" label="WireGuard Public Key" value="{{ old('wireguard_public_key') }}" required />
                <flux:select name="status" label="状態">
                    <option value="active" @selected(old('status', 'active') === 'active')>active</option>
                    <option value="maintenance" @selected(old('status') === 'maintenance')>maintenance</option>
                    <option value="inactive" @selected(old('status') === 'inactive')>inactive</option>
                </flux:select>
                <flux:input name="purpose" label="用途" value="{{ old('purpose') }}" />

                <div class="md:col-span-2">
                    <flux:button type="submit" variant="primary" icon="plus">登録</flux:button>
                </div>
            </form>
        </flux:card>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>名称</flux:table.column>
                <flux:table.column>Global IP</flux:table.column>
                <flux:table.column>WireGuard IP</flux:table.column>
                <flux:table.column>Transit Port</flux:table.column>
                <flux:table.column>状態</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($gateways as $gateway)
                    <flux:table.row>
                        <flux:table.cell>{{ $gateway->getName() }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $gateway->getGlobalIp() }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $gateway->getWireguardIp() }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $gateway->getTransitWireguardPort() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge variant="{{ $gateway->getStatus() === 'active' ? 'lime' : ($gateway->getStatus() === 'maintenance' ? 'amber' : 'zinc') }}">
                                {{ $gateway->getStatus() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button href="{{ route('vps-gateways.show', $gateway->getId()) }}" size="sm" variant="ghost">詳細</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500">VPS ゲートウェイが登録されていません。</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</x-layouts::app>
