<x-layouts::app :title="__('DNS ゾーン管理')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">DNS ゾーン管理</flux:heading>
            <flux:button type="submit" form="dns-reload-form" variant="ghost" icon="arrow-path">CoreDNS リロード</flux:button>
            <form id="dns-reload-form" method="POST" action="{{ route('dns-zones.reload') }}">
                @csrf
            </form>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        @if (session('error'))
            <flux:callout variant="danger" icon="x-circle">{{ session('error') }}</flux:callout>
        @endif

        <flux:card>
            <flux:heading size="lg">ゾーン追加</flux:heading>
            <form method="POST" action="{{ route('dns-zones.store') }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-5">
                @csrf

                <flux:input name="name" label="Zone Name" placeholder="example.com" value="{{ old('name') }}" required />

                <flux:field>
                    <flux:label>Provider</flux:label>
                    <flux:select name="provider" required>
                        <flux:select.option value="cloudflare" :selected="old('provider') === 'cloudflare'">cloudflare</flux:select.option>
                        <flux:select.option value="sakura" :selected="old('provider') === 'sakura'">sakura</flux:select.option>
                        <flux:select.option value="local" :selected="old('provider', 'local') === 'local'">local</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:input name="external_zone_id" label="External Zone ID" value="{{ old('external_zone_id') }}" />
                <flux:input name="description" label="Description" value="{{ old('description') }}" />

                <div class="flex items-end gap-3">
                    <flux:checkbox name="is_active" value="1" :checked="old('is_active', true)">有効</flux:checkbox>
                    <flux:button type="submit" variant="primary" icon="plus">追加</flux:button>
                </div>
            </form>
        </flux:card>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Zone</flux:table.column>
                <flux:table.column>Provider</flux:table.column>
                <flux:table.column>外部ID</flux:table.column>
                <flux:table.column>レコード数</flux:table.column>
                <flux:table.column>状態</flux:table.column>
                <flux:table.column>操作</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($zones as $zone)
                    <flux:table.row>
                        <flux:table.cell class="font-mono text-sm">{{ $zone->name }}</flux:table.cell>
                        <flux:table.cell>{{ $zone->provider }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $zone->external_zone_id ?: '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $zone->records_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge variant="{{ $zone->is_active ? 'lime' : 'zinc' }}">{{ $zone->is_active ? 'active' : 'inactive' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:button href="{{ route('dns-zones.records.index', $zone->id) }}" size="sm" variant="ghost">レコード</flux:button>

                                <form method="POST" action="{{ route('dns-zones.update', $zone->id) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="name" value="{{ $zone->name }}">
                                    <input type="hidden" name="provider" value="{{ $zone->provider }}">
                                    <input type="hidden" name="external_zone_id" value="{{ $zone->external_zone_id }}">
                                    <input type="hidden" name="description" value="{{ $zone->description }}">
                                    <input type="hidden" name="is_active" value="{{ $zone->is_active ? 0 : 1 }}">
                                    <flux:button type="submit" size="sm" variant="subtle">
                                        {{ $zone->is_active ? '無効化' : '有効化' }}
                                    </flux:button>
                                </form>

                                <form method="POST" action="{{ route('dns-zones.sync', $zone->id) }}">
                                    @csrf
                                    <flux:button type="submit" size="sm" variant="ghost" icon="arrow-path">同期</flux:button>
                                </form>

                                <form method="POST" action="{{ route('dns-zones.destroy', $zone->id) }}" onsubmit="return confirm('このゾーンを削除しますか？')">
                                    @csrf
                                    @method('DELETE')
                                    <flux:button type="submit" size="sm" variant="danger">削除</flux:button>
                                </form>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500">DNSゾーンがありません。</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</x-layouts::app>
