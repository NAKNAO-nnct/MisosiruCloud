<x-layouts::app :title="__('コンテナデプロイ')">
    <div class="flex max-w-3xl flex-col gap-6">
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('containers.index') }}" variant="ghost" size="sm" icon="arrow-left">一覧へ戻る</flux:button>
            <flux:heading size="xl">コンテナデプロイ</flux:heading>
        </div>

        @if ($errors->has('error'))
            <flux:callout variant="danger" icon="x-circle">{{ $errors->first('error') }}</flux:callout>
        @endif

        <form method="POST" action="{{ route('containers.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                <flux:label>名前</flux:label>
                <flux:input name="name" value="{{ old('name') }}" required />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>イメージ</flux:label>
                <flux:input name="image" value="{{ old('image', 'nginx:stable') }}" required />
                <flux:error name="image" />
            </flux:field>

            <flux:field>
                <flux:label>ドメイン</flux:label>
                <flux:input name="domain" value="{{ old('domain') }}" placeholder="app.example.com" />
                <flux:error name="domain" />
            </flux:field>

            <flux:field>
                <flux:label>レプリカ数</flux:label>
                <flux:input type="number" name="replicas" value="{{ old('replicas', 1) }}" min="1" required />
                <flux:error name="replicas" />
            </flux:field>

            <flux:field>
                <flux:label>CPU (MHz)</flux:label>
                <flux:input type="number" name="cpu_mhz" value="{{ old('cpu_mhz', 500) }}" min="100" required />
                <flux:error name="cpu_mhz" />
            </flux:field>

            <flux:field>
                <flux:label>メモリ (MB)</flux:label>
                <flux:input type="number" name="memory_mb" value="{{ old('memory_mb', 256) }}" min="64" required />
                <flux:error name="memory_mb" />
            </flux:field>

            <flux:field>
                <flux:label>Port Label</flux:label>
                <flux:input name="port_mappings[0][label]" value="{{ old('port_mappings.0.label', 'http') }}" />
                <flux:error name="port_mappings.0.label" />
            </flux:field>

            <flux:field>
                <flux:label>Container Port</flux:label>
                <flux:input type="number" name="port_mappings[0][to]" value="{{ old('port_mappings.0.to', 80) }}" min="1" max="65535" />
                <flux:error name="port_mappings.0.to" />
            </flux:field>

            <flux:field>
                <flux:label>Host Port (任意)</flux:label>
                <flux:input type="number" name="port_mappings[0][value]" value="{{ old('port_mappings.0.value') }}" min="1" max="65535" />
                <flux:error name="port_mappings.0.value" />
            </flux:field>

            <flux:field class="md:col-span-2">
                <flux:label>環境変数 APP_ENV (任意)</flux:label>
                <flux:input name="env_vars[APP_ENV]" value="{{ old('env_vars.APP_ENV') }}" />
                <flux:error name="env_vars.APP_ENV" />
            </flux:field>

            <div class="md:col-span-2 flex gap-2">
                <flux:button type="submit" variant="primary">デプロイ</flux:button>
                <flux:button href="{{ route('containers.index') }}" variant="ghost">キャンセル</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
