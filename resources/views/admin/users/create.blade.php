<x-layouts::app :title="__('ユーザ作成')">
    <div class="flex max-w-3xl flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">ユーザ作成</flux:heading>
            <flux:button href="{{ route('users.index') }}" variant="ghost">一覧へ</flux:button>
        </div>

        <form method="POST" action="{{ route('users.store') }}" class="flex flex-col gap-4">
            @csrf

            <flux:field>
                <flux:label>名前</flux:label>
                <flux:input name="name" value="{{ old('name') }}" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Email</flux:label>
                <flux:input name="email" type="email" value="{{ old('email') }}" />
                <flux:error name="email" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>パスワード</flux:label>
                    <flux:input name="password" type="password" />
                    <flux:error name="password" />
                </flux:field>

                <flux:field>
                    <flux:label>パスワード確認</flux:label>
                    <flux:input name="password_confirmation" type="password" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>ロール</flux:label>
                <flux:select name="role">
                    <flux:select.option value="admin" :selected="old('role') === 'admin'">admin</flux:select.option>
                    <flux:select.option value="tenant_admin" :selected="old('role') === 'tenant_admin'">tenant_admin</flux:select.option>
                    <flux:select.option value="tenant_member" :selected="old('role', 'tenant_member') === 'tenant_member'">tenant_member</flux:select.option>
                </flux:select>
                <flux:error name="role" />
            </flux:field>

            <flux:field>
                <flux:label>テナント割り当て（複数選択可）</flux:label>
                <select name="tenant_ids[]" multiple class="w-full rounded-lg border border-zinc-300 p-2 text-sm">
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->getId() }}" @selected(in_array($tenant->getId(), old('tenant_ids', []), true))>
                            {{ $tenant->getName() }}
                        </option>
                    @endforeach
                </select>
                <flux:error name="tenant_ids" />
            </flux:field>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">作成</flux:button>
                <flux:button href="{{ route('users.index') }}" variant="ghost">キャンセル</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
