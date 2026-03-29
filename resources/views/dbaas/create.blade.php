<x-layouts::app :title="__('DBaaS 作成')">
    <div class="flex max-w-3xl flex-col gap-6" x-data="dbaasForm()">
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('dbaas.index') }}" variant="ghost" size="sm" icon="arrow-left">一覧へ戻る</flux:button>
            <flux:heading size="xl">DBaaS 作成</flux:heading>
        </div>

        @if ($errors->has('error'))
            <flux:callout variant="danger" icon="x-circle">{{ $errors->first('error') }}</flux:callout>
        @endif

        <form method="POST" action="{{ route('dbaas.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @csrf

            <flux:field>
                <flux:label>テナント</flux:label>
                <flux:select name="tenant_id" required>
                    <flux:select.option value="">-- 選択してください --</flux:select.option>
                    @foreach ($tenants as $tenant)
                        <flux:select.option value="{{ $tenant->getId() }}" :selected="old('tenant_id') == $tenant->getId()">{{ $tenant->getName() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="tenant_id" />
            </flux:field>

            <flux:field>
                <flux:label>DB種別</flux:label>
                <flux:select name="db_type" required @change="updateVersions()" x-model="selectedDbType">
                    <flux:select.option value="">-- 選択してください --</flux:select.option>
                    @foreach ($dbTypes as $dbType)
                        <flux:select.option value="{{ $dbType->value }}" :selected="old('db_type') === $dbType->value">{{ $dbType->value }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="db_type" />
            </flux:field>

            <flux:field>
                <flux:label>DBバージョン</flux:label>
                <select name="db_version" required x-model="selectedDbVersion" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="">-- DB種別を先に選択してください --</option>
                    <template x-for="version in availableVersions" :key="version">
                        <option :value="version" x-text="version"></option>
                    </template>
                </select>
                <flux:error name="db_version" />
            </flux:field>

            <flux:field>
                <flux:label>ラベル</flux:label>
                <flux:input name="label" value="{{ old('label') }}" />
                <flux:error name="label" />
            </flux:field>

            <flux:field>
                <flux:label>テンプレートVMID</flux:label>
                <flux:select name="template_vmid" required>
                    <flux:select.option value="">-- 選択してください --</flux:select.option>
                    @foreach ($templates as $tpl)
                        <flux:select.option value="{{ $tpl['vmid'] }}" :selected="(string) old('template_vmid') === (string) $tpl['vmid']">
                            {{ $tpl['vmid'] }} - {{ $tpl['name'] ?? 'unnamed' }} ({{ $tpl['node'] }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="template_vmid" />
            </flux:field>

            <flux:field>
                <flux:label>ノード</flux:label>
                <flux:select name="node" required>
                    @forelse ($nodes as $nodeName)
                        <flux:select.option value="{{ $nodeName }}" :selected="old('node') === $nodeName">{{ $nodeName }}</flux:select.option>
                    @empty
                        <flux:select.option value="">-- ノードが見つかりません --</flux:select.option>
                    @endforelse
                </flux:select>
                <flux:error name="node" />
            </flux:field>

            <flux:field>
                <flux:label>新しいVMID</flux:label>
                <flux:input type="number" name="new_vmid" value="{{ old('new_vmid', $nextVmid) }}" required />
                <flux:error name="new_vmid" />
            </flux:field>

            <flux:field>
                <flux:label>CPU</flux:label>
                <flux:input type="number" name="cpu" value="{{ old('cpu', 2) }}" min="1" required />
                <flux:error name="cpu" />
            </flux:field>

            <flux:field>
                <flux:label>メモリ(MB)</flux:label>
                <flux:input type="number" name="memory_mb" value="{{ old('memory_mb', 2048) }}" min="512" required />
                <flux:error name="memory_mb" />
            </flux:field>

            <flux:field>
                <flux:label>追加ディスク(GB)</flux:label>
                <flux:input type="number" name="disk_gb" value="{{ old('disk_gb') }}" min="1" />
                <flux:error name="disk_gb" />
            </flux:field>

            <div class="md:col-span-2 flex gap-2">
                <flux:button type="submit" variant="primary">作成</flux:button>
                <flux:button href="{{ route('dbaas.index') }}" variant="ghost">キャンセル</flux:button>
            </div>
        </form>

        <script>
            function dbaasForm() {
                return {
                    selectedDbType: '{{ old('db_type', '') }}',
                    selectedDbVersion: '{{ old('db_version', '') }}',
                    availableVersions: [],
                    dbVersions: @json($dbVersions),

                    init() {
                        this.updateVersions();
                    },

                    updateVersions() {
                        if (this.selectedDbType && this.dbVersions[this.selectedDbType]) {
                            this.availableVersions = this.dbVersions[this.selectedDbType];
                            // 前の値が無効になった場合は先頭を選択
                            if (!this.availableVersions.includes(this.selectedDbVersion)) {
                                this.selectedDbVersion = this.availableVersions[0] || '';
                            }
                        } else {
                            this.availableVersions = [];
                            this.selectedDbVersion = '';
                        }
                    }
                };
            }
        </script>
    </div>
</x-layouts::app>
