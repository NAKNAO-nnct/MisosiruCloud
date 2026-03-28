<x-layouts::app :title="__('VM コンソール')">
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ $meta->label }} — コンソール</flux:heading>
            <flux:button href="{{ route('vms.show', $meta->proxmox_vmid) }}" variant="ghost" size="sm">
                ← VM 詳細へ
            </flux:button>
        </div>

        <flux:card class="p-0">
            <div
                id="novnc-container"
                class="relative w-full bg-black"
                style="min-height: 600px;"
                data-host="{{ $vncProxy['host'] ?? '' }}"
                data-port="{{ $vncProxy['port'] ?? '' }}"
                data-ticket="{{ $vncProxy['ticket'] ?? '' }}"
                data-vmid="{{ $meta->proxmox_vmid }}"
            >
                <p class="p-4 text-sm text-zinc-400">
                    VNC コンソールに接続中... JavaScript が有効である必要があります。
                </p>
            </div>
        </flux:card>
    </div>

    <script>
        // noVNC クライアント統合のプレースホルダー
        // 実際の運用では noVNC ライブラリを使用して WebSocket プロキシ経由で接続します
        const container = document.getElementById('novnc-container');
        if (container) {
            const host = container.dataset.host;
            const port = container.dataset.port;
            const ticket = container.dataset.ticket;
            console.log('VNC Proxy:', { host, port, ticket });
        }
    </script>
</x-layouts::app>
