<x-layouts::app :title="__('Proxmox クラスタ管理')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">Proxmox クラスタ管理</flux:heading>
            <flux:button href="{{ route('proxmox-clusters.create') }}" variant="primary" icon="plus">
                クラスタ接続を追加
            </flux:button>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>名称</flux:table.column>
                <flux:table.column>ホスト名</flux:table.column>
                <flux:table.column>API トークン ID</flux:table.column>
                <flux:table.column>状態</flux:table.column>
                <flux:table.column>操作</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($nodes as $node)
                    <flux:table.row>
                        <flux:table.cell>{{ $node->getName() }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $node->getHostname() }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $node->getApiTokenId() }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($node->isActive())
                                <flux:badge color="green" icon="check-circle">アクティブ</flux:badge>
                            @else
                                <flux:badge color="zinc">無効</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                @if (!$node->isActive())
                                    <form method="POST" action="{{ route('proxmox-clusters.activate', $node->getId()) }}">
                                        @csrf
                                        <flux:button type="submit" size="sm" variant="ghost" icon="check">有効化</flux:button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('proxmox-clusters.deactivate', $node->getId()) }}">
                                        @csrf
                                        <flux:button type="submit" size="sm" variant="ghost" icon="x">無効化</flux:button>
                                    </form>
                                @endif

                                <flux:button href="{{ route('proxmox-clusters.edit', $node->getId()) }}" size="sm" variant="ghost" icon="pencil">編集</flux:button>

                                <form method="POST" action="{{ route('proxmox-clusters.destroy', $node->getId()) }}"
                                    onsubmit="return confirm('このノードを削除しますか？')">
                                    @csrf
                                    @method('DELETE')
                                    <flux:button type="submit" size="sm" variant="ghost" icon="trash">削除</flux:button>
                                </form>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-500">
                            Proxmox クラスタ接続が登録されていません。
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</x-layouts::app>
