<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ isset($title) ? $title . ' - ' . config('app.name') : config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x" />

            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 px-4 py-3">
                <flux:heading>{{ config('app.name') }}</flux:heading>
            </a>

            <flux:navlist variant="outline" class="flex-1">
                <flux:navlist.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')">
                    ダッシュボード
                </flux:navlist.item>

                @if ($authUser?->isAdmin())
                    <flux:navlist.group expandable heading="管理">
                        <flux:navlist.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')">
                            ユーザ管理
                        </flux:navlist.item>
                        <flux:navlist.item icon="building-2" :href="route('tenants.index')" :current="request()->routeIs('tenants.*')">
                            テナント管理
                        </flux:navlist.item>
                        <flux:navlist.item icon="folder-git-2" :href="route('vms.index')" :current="request()->routeIs('vms.*')">
                            VM管理
                        </flux:navlist.item>
                        <flux:navlist.item icon="chevrons-up-down" :href="route('dbaas.index')" :current="request()->routeIs('dbaas.*')">
                            DBaaS管理
                        </flux:navlist.item>
                        <flux:navlist.item icon="cube" :href="route('containers.index')" :current="request()->routeIs('containers.*')">
                            CaaS管理
                        </flux:navlist.item>
                        <flux:navlist.item icon="server" :href="route('networks.index')" :current="request()->routeIs('networks.*')">
                            ネットワーク管理
                        </flux:navlist.item>
                        <flux:navlist.item icon="server" :href="route('proxmox-clusters.index')" :current="request()->routeIs('proxmox-clusters.*')">
                            Proxmox クラスタ
                        </flux:navlist.item>
                        <flux:navlist.item icon="server" :href="route('vps-gateways.index')" :current="request()->routeIs('vps-gateways.*')">
                            VPS ゲートウェイ
                        </flux:navlist.item>
                        <flux:navlist.item icon="globe-alt" :href="route('dns.index')" :current="request()->routeIs('dns.*')">
                            DNS 管理
                        </flux:navlist.item>
                    </flux:navlist.group>
                @endif
            </flux:navlist>

            <flux:separator />

            <flux:navlist variant="outline">
                <flux:navlist.item>
                    <flux:profile
                        :name="$authUser?->getName() ?? ''"
                        :initials="$authUser?->getInitials() ?? ''"
                    />
                </flux:navlist.item>
            </flux:navlist>

            <div x-data>
                <form method="POST" action="{{ route('logout') }}" id="logout-form">
                    @csrf
                </form>
                <flux:navlist variant="outline">
                    <flux:navlist.item
                        icon="log-out"
                        @click.prevent="document.getElementById('logout-form').submit()"
                    >
                        ログアウト
                    </flux:navlist.item>
                </flux:navlist>
            </div>
        </flux:sidebar>

        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="menu" inset="left" />
            <flux:spacer />
        </flux:header>

        <flux:main>
            {{ $slot }}
        </flux:main>

        @fluxScripts
    </body>
</html>
