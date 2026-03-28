<x-layouts::app :title="__('VPS ゲートウェイ詳細')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('vps-gateways.index') }}" variant="ghost" size="sm" icon="arrow-left">一覧へ戻る</flux:button>
            <flux:heading size="xl">{{ $gateway->getName() }} (#{{ $gateway->getId() }})</flux:heading>
            <flux:badge variant="{{ $gateway->getStatus() === 'active' ? 'lime' : ($gateway->getStatus() === 'maintenance' ? 'amber' : 'zinc') }}">
                {{ $gateway->getStatus() }}
            </flux:badge>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <flux:card>
                <flux:heading size="lg">接続情報</flux:heading>
                <dl class="mt-2 flex flex-col gap-2 text-sm">
                    <div class="flex justify-between"><dt class="text-zinc-500">Global IP</dt><dd class="font-mono">{{ $gateway->getGlobalIp() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">WireGuard IP</dt><dd class="font-mono">{{ $gateway->getWireguardIp() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">WireGuard Port</dt><dd>{{ $gateway->getWireguardPort() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">Transit Port</dt><dd>{{ $gateway->getTransitWireguardPort() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">用途</dt><dd>{{ $gateway->getPurpose() ?? '-' }}</dd></div>
                </dl>
            </flux:card>

            <flux:card>
                <flux:heading size="lg">状態同期</flux:heading>
                @php($syncResult = session('sync_result'))
                <div class="mt-2 text-sm text-zinc-600">
                    @if (is_array($syncResult))
                        最終同期: {{ $syncResult['last_synced_at'] ?? '-' }} / reachable: {{ ($syncResult['reachable'] ?? false) ? 'yes' : 'no' }}
                    @else
                        未同期
                    @endif
                </div>
                <form method="POST" action="{{ route('vps-gateways.sync', $gateway->getId()) }}" class="mt-3">
                    @csrf
                    <flux:button type="submit" variant="ghost" icon="arrow-path">同期</flux:button>
                </form>
            </flux:card>
        </div>

        <flux:card>
            <flux:heading size="lg">設定更新</flux:heading>
            <form method="POST" action="{{ route('vps-gateways.update', $gateway->getId()) }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                @csrf
                @method('PUT')
                <flux:input name="name" label="名称" value="{{ old('name', $gateway->getName()) }}" required />
                <flux:input name="global_ip" label="Global IP" value="{{ old('global_ip', $gateway->getGlobalIp()) }}" required />
                <flux:input name="wireguard_port" label="WireGuard Port" type="number" min="1" max="65535" value="{{ old('wireguard_port', $gateway->getWireguardPort()) }}" required />
                <flux:input name="wireguard_public_key" label="WireGuard Public Key" value="{{ old('wireguard_public_key', $gateway->getWireguardPublicKey()) }}" required />
                <flux:select name="status" label="状態">
                    <option value="active" @selected(old('status', $gateway->getStatus()) === 'active')>active</option>
                    <option value="maintenance" @selected(old('status', $gateway->getStatus()) === 'maintenance')>maintenance</option>
                    <option value="inactive" @selected(old('status', $gateway->getStatus()) === 'inactive')>inactive</option>
                </flux:select>
                <flux:input name="purpose" label="用途" value="{{ old('purpose', $gateway->getPurpose()) }}" />

                <div class="md:col-span-2 flex items-center gap-2">
                    <flux:button type="submit">更新</flux:button>
                </div>
            </form>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading size="lg">WireGuard 設定</flux:heading>
                <flux:button
                    variant="ghost"
                    size="sm"
                    onclick="navigator.clipboard.writeText(document.getElementById('wg-config').innerText)">
                    コピー
                </flux:button>
            </div>
            <pre id="wg-config" class="mt-3 max-h-96 overflow-auto rounded bg-zinc-900 p-3 text-xs text-zinc-100">{{ $wireguardConfig }}</pre>
        </flux:card>

        <flux:card>
            <flux:heading size="lg">危険操作</flux:heading>
            <form method="POST" action="{{ route('vps-gateways.destroy', $gateway->getId()) }}" class="mt-3" onsubmit="return confirm('このVPSゲートウェイを削除しますか？')">
                @csrf
                @method('DELETE')
                <flux:button type="submit" variant="danger" icon="trash">削除</flux:button>
            </form>
        </flux:card>
    </div>
</x-layouts::app>
