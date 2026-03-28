<x-layouts::app :title="__('DNS 管理')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">DNS 管理</flux:heading>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        <flux:card>
            <flux:heading size="lg">レコード追加</flux:heading>
            <form method="POST" action="{{ route('dns.store') }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                @csrf
                <flux:input name="name" label="Name" value="{{ old('name') }}" required />
                <flux:select name="type" label="Type">
                    <option value="A" @selected(old('type', 'A') === 'A')>A</option>
                    <option value="AAAA" @selected(old('type') === 'AAAA')>AAAA</option>
                    <option value="CNAME" @selected(old('type') === 'CNAME')>CNAME</option>
                    <option value="TXT" @selected(old('type') === 'TXT')>TXT</option>
                </flux:select>
                <flux:input name="value" label="Value" value="{{ old('value') }}" required />
                <flux:input name="ttl" label="TTL" type="number" min="60" max="86400" value="{{ old('ttl', 300) }}" required />
                <div class="md:col-span-4">
                    <flux:button type="submit" variant="primary" icon="plus">追加</flux:button>
                </div>
            </form>
        </flux:card>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>ID</flux:table.column>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Value</flux:table.column>
                <flux:table.column>TTL</flux:table.column>
                <flux:table.column>操作</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($records as $record)
                    <flux:table.row>
                        <flux:table.cell class="font-mono text-xs">{{ (string) ($record['id'] ?? '-') }}</flux:table.cell>
                        <flux:table.cell>{{ (string) ($record['name'] ?? '-') }}</flux:table.cell>
                        <flux:table.cell>{{ (string) ($record['type'] ?? '-') }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ (string) ($record['value'] ?? '-') }}</flux:table.cell>
                        <flux:table.cell>{{ (string) ($record['ttl'] ?? '-') }}</flux:table.cell>
                        <flux:table.cell>
                            @if (isset($record['id']))
                                <div class="flex items-center gap-2">
                                    <form method="POST" action="{{ route('dns.update', (string) $record['id']) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="name" value="{{ (string) ($record['name'] ?? '') }}">
                                        <input type="hidden" name="type" value="{{ (string) ($record['type'] ?? 'A') }}">
                                        <input type="hidden" name="value" value="{{ (string) ($record['value'] ?? '') }}">
                                        <input type="hidden" name="ttl" value="{{ (string) ($record['ttl'] ?? 300) }}">
                                        <flux:button type="submit" size="sm" variant="ghost">再保存</flux:button>
                                    </form>

                                    <form method="POST" action="{{ route('dns.destroy', (string) $record['id']) }}" onsubmit="return confirm('このDNSレコードを削除しますか？')">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button type="submit" size="sm" variant="danger">削除</flux:button>
                                    </form>
                                </div>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500">DNS レコードがありません。</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</x-layouts::app>
