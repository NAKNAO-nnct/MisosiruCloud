<x-layouts::app :title="__('VM 作成')">
    <div class="flex flex-col gap-6 max-w-2xl">
        <flux:heading size="xl">VM 作成</flux:heading>

        <form method="POST" action="{{ route('vms.store') }}" class="flex flex-col gap-4">
            @csrf

            <flux:field>
                <flux:label>テナント</flux:label>
                <flux:select name="tenant_id">
                    <flux:select.option value="">-- 選択してください --</flux:select.option>
                    @foreach ($tenants as $tenant)
                        <flux:select.option value="{{ $tenant->id }}" :selected="old('tenant_id') == $tenant->id">
                            {{ $tenant->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="tenant_id" />
            </flux:field>

            <flux:field>
                <flux:label>ラベル</flux:label>
                <flux:input name="label" value="{{ old('label') }}" placeholder="my-vm" />
                <flux:error name="label" />
            </flux:field>

            <flux:field>
                <flux:label>テンプレート VMID</flux:label>
                <flux:select name="template_vmid">
                    <flux:select.option value="">-- 選択してください --</flux:select.option>
                    @foreach ($templates as $tpl)
                        <flux:select.option value="{{ $tpl['vmid'] }}" :selected="old('template_vmid') == $tpl['vmid']">
                            {{ $tpl['vmid'] }} - {{ $tpl['name'] ?? 'unnamed' }} ({{ $tpl['node'] }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="template_vmid" />
            </flux:field>

            <flux:field>
                <flux:label>ノード</flux:label>
                <flux:input name="node" value="{{ old('node') }}" placeholder="pve" />
                <flux:error name="node" />
            </flux:field>

            <flux:field>
                <flux:label>新しい VMID</flux:label>
                <flux:input type="number" name="new_vmid" value="{{ old('new_vmid') }}" placeholder="200" />
                <flux:error name="new_vmid" />
            </flux:field>

            <div class="grid grid-cols-3 gap-4">
                <flux:field>
                    <flux:label>CPU (コア数)</flux:label>
                    <flux:input type="number" name="cpu" value="{{ old('cpu', 2) }}" min="1" max="64" />
                    <flux:error name="cpu" />
                </flux:field>

                <flux:field>
                    <flux:label>メモリ (MB)</flux:label>
                    <flux:input type="number" name="memory_mb" value="{{ old('memory_mb', 2048) }}" min="512" />
                    <flux:error name="memory_mb" />
                </flux:field>

                <flux:field>
                    <flux:label>追加ディスク (GB)</flux:label>
                    <flux:input type="number" name="disk_gb" value="{{ old('disk_gb') }}" min="1" />
                    <flux:error name="disk_gb" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>用途 (purpose)</flux:label>
                <flux:input name="purpose" value="{{ old('purpose') }}" placeholder="mysql, postgres, redis など" />
                <flux:error name="purpose" />
            </flux:field>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">作成</flux:button>
                <flux:button href="{{ route('vms.index') }}" variant="ghost">キャンセル</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
