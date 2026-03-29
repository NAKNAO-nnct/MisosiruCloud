<x-layouts::app :title="__('プロビジョニングジョブ一覧')">
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">プロビジョニングジョブ一覧</flux:heading>
            <flux:button href="{{ route('vms.index') }}" variant="ghost">
                VM 一覧へ戻る
            </flux:button>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>VMID</flux:table.column>
                <flux:table.column>ラベル</flux:table.column>
                <flux:table.column>ノード</flux:table.column>
                <flux:table.column>テナント</flux:table.column>
                <flux:table.column>ステータス</flux:table.column>
                <flux:table.column>エラーメッセージ</flux:table.column>
                <flux:table.column>作成日時</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($jobs as $job)
                    <flux:table.row>
                        <flux:table.cell>{{ $job->proxmox_vmid }}</flux:table.cell>
                        <flux:table.cell>{{ $job->label ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $job->proxmox_node }}</flux:table.cell>
                        <flux:table.cell>{{ $job->tenant?->name ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <x-vm-status-badge :status="$job->provisioning_status" />
                        </flux:table.cell>
                        <flux:table.cell class="max-w-md truncate">
                            @if ($job->provisioning_error)
                                <span class="text-red-600" title="{{ $job->provisioning_error }}">
                                    {{ mb_substr($job->provisioning_error, 0, 50) }}{{ mb_strlen($job->provisioning_error) > 50 ? '…' : '' }}
                                </span>
                            @else
                                -
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $job->created_at?->format('Y-m-d H:i:s') ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($job->provisioning_status?->value === 'error')
                                <form method="POST" action="{{ route('vms.meta.destroy', $job->id) }}" onsubmit="return confirm('このメタデータを削除してもよろしいですか？')">
                                    @csrf
                                    @method('DELETE')
                                    <flux:button type="submit" size="sm" variant="danger">
                                        削除
                                    </flux:button>
                                </form>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-zinc-500">
                            ジョブがありません。
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

</x-layouts::app>

