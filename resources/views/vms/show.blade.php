<x-layouts::app :title="__('VM 詳細')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <flux:heading size="xl">
                    {{ $meta?->label ?? $status?->name ?? "VMID {$meta?->proxmox_vmid}" }}
                </flux:heading>
                @if ($meta)
                    <x-vm-status-badge :status="$meta->provisioning_status" />
                @endif
            </div>
            <flux:button href="{{ route('vms.index') }}" variant="ghost" size="sm">← 一覧へ</flux:button>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="circle-check">
                {{ session('success') }}
            </flux:callout>
        @endif

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            {{-- メタ情報 --}}
            @if ($meta)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">VM 情報</flux:heading>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">テナント</dt>
                            <dd>{{ $meta->tenant?->name ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">ノード</dt>
                            <dd>{{ $meta->proxmox_node }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">VMID</dt>
                            <dd>{{ $meta->proxmox_vmid }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">用途</dt>
                            <dd>{{ $meta->purpose ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">IP アドレス</dt>
                            <dd>{{ $meta->shared_ip_address ?? '-' }}</dd>
                        </div>
                    </dl>
                </flux:card>
            @endif

            {{-- リソース使用状況 --}}
            @if ($status)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">リソース使用状況</flux:heading>
                    <div class="space-y-4">
                        <div>
                            <p class="mb-1 text-sm text-zinc-500">CPU</p>
                            <x-resource-meter
                                :used="round($status->cpu * 100, 1)"
                                :total="100"
                                unit="%"
                            />
                        </div>
                        <div>
                            <p class="mb-1 text-sm text-zinc-500">メモリ</p>
                            <x-resource-meter
                                :used="round($status->mem / 1024 / 1024)"
                                :total="round($status->maxmem / 1024 / 1024)"
                                unit="MB"
                            />
                        </div>
                    </div>
                </flux:card>
            @endif
        </div>

        {{-- 操作ボタン --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">操作</flux:heading>
            <div class="flex flex-wrap gap-2">
                @if ($meta)
                    <form method="POST" action="{{ route('vms.start', $meta->proxmox_vmid) }}">
                        @csrf
                        <flux:button type="submit" variant="primary">起動</flux:button>
                    </form>
                    <form method="POST" action="{{ route('vms.stop', $meta->proxmox_vmid) }}">
                        @csrf
                        <flux:button type="submit" variant="warning">停止</flux:button>
                    </form>
                    <form method="POST" action="{{ route('vms.reboot', $meta->proxmox_vmid) }}">
                        @csrf
                        <flux:button type="submit">再起動</flux:button>
                    </form>
                    <flux:button href="{{ route('vms.console', $meta->proxmox_vmid) }}" variant="ghost">
                        コンソール
                    </flux:button>
                    <form method="POST" action="{{ route('vms.force-stop', $meta->proxmox_vmid) }}">
                        @csrf
                        <flux:button type="submit" variant="danger">強制停止</flux:button>
                    </form>
                    <form method="POST" action="{{ route('vms.destroy', $meta->proxmox_vmid) }}"
                          onsubmit="return confirm('本当に削除しますか？この操作は取り消せません。')">
                        @csrf
                        @method('DELETE')
                        <flux:button type="submit" variant="danger">削除</flux:button>
                    </form>
                @endif
            </div>
        </flux:card>

        {{-- スナップショット作成 --}}
        @if ($meta)
            <flux:card>
                <flux:heading size="lg" class="mb-4">スナップショット作成</flux:heading>
                <form method="POST" action="{{ route('vms.snapshot', $meta->proxmox_vmid) }}" class="flex gap-2">
                    @csrf
                    <flux:input name="name" placeholder="snapshot-name" class="max-w-xs" />
                    <flux:button type="submit" variant="primary">作成</flux:button>
                </form>
            </flux:card>

            {{-- ディスクリサイズ --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">ディスクリサイズ</flux:heading>
                <form method="POST" action="{{ route('vms.resize', $meta->proxmox_vmid) }}" class="flex gap-2">
                    @csrf
                    <flux:input name="disk" value="scsi0" class="max-w-32" />
                    <flux:input name="size" placeholder="+10G" class="max-w-32" />
                    <flux:button type="submit" variant="primary">リサイズ</flux:button>
                </form>
            </flux:card>
        @endif
    </div>
</x-layouts::app>
