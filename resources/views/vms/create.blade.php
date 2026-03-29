<x-layouts::app :title="__('VM 作成')">
    @php
        $prefill = [
            'tenant_id' => request()->query('tenant_id'),
            'label' => request()->query('label'),
            'template_vmid' => request()->query('template_vmid'),
            'node' => request()->query('node'),
            'new_vmid' => request()->query('new_vmid'),
            'cpu' => request()->query('cpu'),
            'memory_mb' => request()->query('memory_mb'),
            'disk_gb' => request()->query('disk_gb'),
            'purpose' => request()->query('purpose'),
            'ip_address' => request()->query('ip_address'),
            'gateway' => request()->query('gateway'),
            'vnet_name' => request()->query('vnet_name'),
            'shared_ip_address' => request()->query('shared_ip_address'),
            'ssh_keys' => request()->query('ssh_keys'),
        ];
    @endphp

    <div class="flex flex-col gap-6 max-w-2xl">
        <flux:heading size="xl">VM 作成</flux:heading>

        <form method="POST" action="{{ route('vms.store') }}" class="flex flex-col gap-4">
            @csrf

            <flux:field>
                <flux:label>テナント</flux:label>
                <flux:select name="tenant_id">
                    <flux:select.option value="">-- 選択してください --</flux:select.option>
                    @foreach ($tenants as $tenant)
                        <flux:select.option value="{{ $tenant->getId() }}" :selected="old('tenant_id', $prefill['tenant_id']) == $tenant->getId()">
                            {{ $tenant->getName() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="tenant_id" />
            </flux:field>

            <flux:field>
                <flux:label>ラベル</flux:label>
                <flux:input name="label" value="{{ old('label', $prefill['label']) }}" placeholder="my-vm" />
                <flux:error name="label" />
            </flux:field>

            <flux:field>
                <flux:label>テンプレート VMID</flux:label>
                <flux:select name="template_vmid">
                    <flux:select.option value="">-- 選択してください --</flux:select.option>
                    @foreach ($templates as $tpl)
                        <flux:select.option value="{{ $tpl['vmid'] }}" :selected="old('template_vmid', $prefill['template_vmid']) == $tpl['vmid']">
                            {{ $tpl['vmid'] }} - {{ $tpl['name'] ?? 'unnamed' }} ({{ $tpl['node'] }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="template_vmid" />
            </flux:field>

            <flux:field>
                <flux:label>ノード</flux:label>
                <flux:select name="node">
                    @forelse ($nodes as $nodeName)
                        <flux:select.option value="{{ $nodeName }}" :selected="old('node', $prefill['node']) === $nodeName">{{ $nodeName }}</flux:select.option>
                    @empty
                        <flux:select.option value="">-- ノードが見つかりません --</flux:select.option>
                    @endforelse
                </flux:select>
                <flux:error name="node" />
            </flux:field>

            <flux:field>
                <flux:label>新しい VMID</flux:label>
                <flux:input type="number" name="new_vmid" value="{{ old('new_vmid', $prefill['new_vmid'] ?? $nextVmid) }}" placeholder="200" />
                <flux:error name="new_vmid" />
            </flux:field>

            <div class="grid grid-cols-3 gap-4">
                <flux:field>
                    <flux:label>CPU (コア数)</flux:label>
                    <flux:input type="number" name="cpu" value="{{ old('cpu', $prefill['cpu'] ?? 2) }}" min="1" max="64" />
                    <flux:error name="cpu" />
                </flux:field>

                <flux:field>
                    <flux:label>メモリ (MB)</flux:label>
                    <flux:input type="number" name="memory_mb" value="{{ old('memory_mb', $prefill['memory_mb'] ?? 2048) }}" min="512" />
                    <flux:error name="memory_mb" />
                </flux:field>

                <flux:field>
                    <flux:label>追加ディスク (GB)</flux:label>
                    <flux:input type="number" name="disk_gb" value="{{ old('disk_gb', $prefill['disk_gb']) }}" min="1" />
                    <flux:error name="disk_gb" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>用途 (purpose)</flux:label>
                <flux:input name="purpose" value="{{ old('purpose', $prefill['purpose']) }}" placeholder="mysql, postgres, redis など" />
                <flux:error name="purpose" />
            </flux:field>

            <flux:heading size="lg">ネットワーク設定</flux:heading>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>IP アドレス</flux:label>
                    <flux:input name="ip_address" value="{{ old('ip_address', $prefill['ip_address']) }}" placeholder="10.1.0.10" />
                    <flux:error name="ip_address" />
                </flux:field>

                <flux:field>
                    <flux:label>ゲートウェイ</flux:label>
                    <flux:input name="gateway" value="{{ old('gateway', $prefill['gateway']) }}" placeholder="10.1.0.1" />
                    <flux:error name="gateway" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>VNet 名</flux:label>
                <flux:input name="vnet_name" value="{{ old('vnet_name', $prefill['vnet_name']) }}" placeholder="vnet_1" />
                <flux:error name="vnet_name" />
            </flux:field>

            <flux:field>
                <flux:label>共有 IP アドレス（オプション）</flux:label>
                <flux:input name="shared_ip_address" value="{{ old('shared_ip_address', $prefill['shared_ip_address']) }}" placeholder="203.0.113.5" />
                <flux:error name="shared_ip_address" />
            </flux:field>

            <flux:field>
                <flux:label>SSH 公開鍵（オプション）</flux:label>
                <flux:textarea name="ssh_keys" placeholder="ssh-rsa AAAA...">{{ old('ssh_keys', $prefill['ssh_keys']) }}</flux:textarea>
                <flux:error name="ssh_keys" />
            </flux:field>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">作成</flux:button>
                <flux:button href="{{ route('vms.index') }}" variant="ghost">キャンセル</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
