<x-layouts::app :title="__('DNS レコード管理')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('dns-zones.index') }}" variant="ghost" size="sm" icon="arrow-left">ゾーン一覧へ戻る</flux:button>
                <flux:heading size="xl">DNS レコード管理</flux:heading>
            </div>
            <flux:badge>{{ $zone->getName() }} ({{ $zone->getProvider() }})</flux:badge>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        <flux:card>
            <flux:heading size="lg">レコード追加</flux:heading>
            <form method="POST" action="{{ route('dns-zones.records.store', $zone->getId()) }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-6">
                @csrf
                <flux:input name="name" label="Name" value="{{ old('name', '@') }}" required />

                <flux:field>
                    <flux:label>Type</flux:label>
                    <flux:select name="type" required>
                        @foreach (['A', 'AAAA', 'CNAME', 'NS', 'TXT', 'MX', 'SRV'] as $type)
                            <flux:select.option value="{{ $type }}" :selected="old('type', 'A') === $type">{{ $type }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:input name="content" label="Content" value="{{ old('content') }}" required />
                <flux:input name="ttl" label="TTL" type="number" min="60" max="86400" value="{{ old('ttl', 300) }}" required />
                <flux:input name="priority" label="Priority" type="number" min="0" max="65535" value="{{ old('priority') }}" />
                <div class="flex items-end">
                    <flux:button type="submit" variant="primary" icon="plus">追加</flux:button>
                </div>
            </form>
        </flux:card>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Content</flux:table.column>
                <flux:table.column>TTL</flux:table.column>
                <flux:table.column>Priority</flux:table.column>
                <flux:table.column>操作</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($records as $record)
                    <flux:table.row>
                        <flux:table.cell>{{ $record->getName() }}</flux:table.cell>
                        <flux:table.cell>{{ $record->getType() }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $record->getContent() }}</flux:table.cell>
                        <flux:table.cell>{{ $record->getTtl() }}</flux:table.cell>
                        <flux:table.cell>{{ $record->getPriority() ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('dns-zones.records.update', [$zone->getId(), $record->getId()]) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="name" value="{{ $record->getName() }}">
                                    <input type="hidden" name="type" value="{{ $record->getType() }}">
                                    <input type="hidden" name="content" value="{{ $record->getContent() }}">
                                    <input type="hidden" name="ttl" value="{{ $record->getTtl() }}">
                                    <input type="hidden" name="priority" value="{{ $record->getPriority() }}">
                                    <flux:button type="submit" size="sm" variant="ghost">再保存</flux:button>
                                </form>

                                <form method="POST" action="{{ route('dns-zones.records.destroy', [$zone->getId(), $record->getId()]) }}" onsubmit="return confirm('このレコードを削除しますか？')">
                                    @csrf
                                    @method('DELETE')
                                    <flux:button type="submit" size="sm" variant="danger">削除</flux:button>
                                </form>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500">DNSレコードがありません。</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</x-layouts::app>
