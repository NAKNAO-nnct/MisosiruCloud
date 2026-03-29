<x-layouts::app :title="__('VM 詳細')">
    @php
        $provisioningStatus = $meta?->getProvisioningStatus();
        $isProvisioning = in_array($provisioningStatus?->value, ['pending', 'cloning', 'configuring', 'starting'], true);
        $isProvisioningError = $provisioningStatus?->value === 'error';
    @endphp

    @if ($isProvisioning)
        <script>
            setTimeout(function () {
                window.location.reload();
            }, 5000);
        </script>
    @endif

    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <flux:heading size="xl">
                    {{ $meta?->getLabel() ?? data_get($status, 'name') ?? "VMID {$meta?->getProxmoxVmid()}" }}
                </flux:heading>
                @if ($meta)
                    <x-vm-status-badge :status="$meta->getProvisioningStatus()" />
                @endif
            </div>
            <flux:button href="{{ route('vms.index') }}" variant="ghost" size="sm">← 一覧へ</flux:button>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="circle-check">
                {{ session('success') }}
            </flux:callout>
        @endif

        @if ($isProvisioning)
            <flux:callout variant="warning" icon="arrow-path">
                プロビジョニング中です。5秒ごとに自動更新しています。
            </flux:callout>
        @endif

        @if ($isProvisioningError)
            <flux:callout variant="danger" icon="x-circle">
                VM のプロビジョニングに失敗しました。
                @if ($meta?->getProvisioningError())
                    {{ $meta->getProvisioningError() }}
                @endif
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
                            <dd>{{ $meta->getTenantName() ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">ノード</dt>
                            <dd>{{ $meta->getProxmoxNode() }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">VMID</dt>
                            <dd>{{ $meta->getProxmoxVmid() }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">用途</dt>
                            <dd>{{ $meta->getPurpose() ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">IP アドレス</dt>
                            <dd>{{ $meta->getSharedIpAddress() ?? '-' }}</dd>
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
                                :used="round((float) data_get($status, 'cpu', 0) * 100, 1)"
                                :total="100"
                                unit="%"
                            />
                        </div>
                        <div>
                            <p class="mb-1 text-sm text-zinc-500">メモリ</p>
                            <x-resource-meter
                                :used="round((float) data_get($status, 'mem', 0) / 1024 / 1024)"
                                :total="round((float) data_get($status, 'maxmem', 0) / 1024 / 1024)"
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
                    @if ($isProvisioningError)
                        <flux:button
                            href="{{ route('vms.create', [
                                'tenant_id' => $meta->getTenantId(),
                                'label' => $meta->getLabel(),
                                'node' => $meta->getProxmoxNode(),
                                'new_vmid' => $meta->getProxmoxVmid(),
                                'purpose' => $meta->getPurpose(),
                            ]) }}"
                            variant="primary"
                        >
                            再試行
                        </flux:button>
                    @endif
                    <form method="POST" action="{{ route('vms.start', $meta->getProxmoxVmid()) }}">
                        @csrf
                        <flux:button type="submit" variant="primary">起動</flux:button>
                    </form>
                    <form method="POST" action="{{ route('vms.stop', $meta->getProxmoxVmid()) }}">
                        @csrf
                        <flux:button type="submit" variant="primary" color="amber">停止</flux:button>
                    </form>
                    <form method="POST" action="{{ route('vms.reboot', $meta->getProxmoxVmid()) }}">
                        @csrf
                        <flux:button type="submit">再起動</flux:button>
                    </form>
                    <flux:button href="{{ route('vms.console', $meta->getProxmoxVmid()) }}" variant="ghost">
                        コンソール
                    </flux:button>
                    <form method="POST" action="{{ route('vms.force-stop', $meta->getProxmoxVmid()) }}">
                        @csrf
                        <flux:button type="submit" variant="danger">強制停止</flux:button>
                    </form>
                    <form method="POST" action="{{ route('vms.destroy', $meta->getProxmoxVmid()) }}"
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
                <form method="POST" action="{{ route('vms.snapshot', $meta->getProxmoxVmid()) }}" class="flex gap-2">
                    @csrf
                    <flux:input name="name" placeholder="snapshot-name" class="max-w-xs" />
                    <flux:button type="submit" variant="primary">作成</flux:button>
                </form>
            </flux:card>

            {{-- ディスクリサイズ --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">ディスクリサイズ</flux:heading>
                <form method="POST" action="{{ route('vms.resize', $meta->getProxmoxVmid()) }}" class="flex gap-2">
                    @csrf
                    <flux:input name="disk" value="scsi0" class="max-w-32" />
                    <flux:input name="size" placeholder="+10G" class="max-w-32" />
                    <flux:button type="submit" variant="primary">リサイズ</flux:button>
                </form>
            </flux:card>
        @endif
    </div>
</x-layouts::app>
