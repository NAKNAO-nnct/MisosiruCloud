<x-layouts::app :title="__('ユーザ管理')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">ユーザ管理</flux:heading>
            <flux:button href="{{ route('users.create') }}" variant="primary">新規作成</flux:button>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>名前</flux:table.column>
                <flux:table.column>Email</flux:table.column>
                <flux:table.column>ロール</flux:table.column>
                <flux:table.column>テナント</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($users as $user)
                    <flux:table.row>
                        <flux:table.cell>{{ $user->getName() }}</flux:table.cell>
                        <flux:table.cell>{{ $user->getEmail() }}</flux:table.cell>
                        <flux:table.cell>
                            @php
                                $roleVariant = match ($user->getRole()->value) {
                                    'admin' => 'lime',
                                    'tenant_admin' => 'blue',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge variant="{{ $roleVariant }}">{{ $user->getRole()->value }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ empty($user->getTenantNames()) ? '-' : implode(', ', $user->getTenantNames()) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button href="{{ route('users.edit', $user->getId()) }}" size="sm" variant="ghost">編集</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-500">
                            ユーザが存在しません。
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</x-layouts::app>
