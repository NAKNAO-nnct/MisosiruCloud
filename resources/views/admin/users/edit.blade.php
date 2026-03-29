<x-layouts::app :title="__('ユーザ編集')">
    <div class="flex max-w-3xl flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">ユーザ編集</flux:heading>
            <flux:button href="{{ route('users.index') }}" variant="ghost">一覧へ</flux:button>
        </div>

        <form method="POST" action="{{ route('users.update', $user->id) }}" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            <flux:field>
                <flux:label>名前</flux:label>
                <flux:input name="name" value="{{ old('name', $user->name) }}" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Email</flux:label>
                <flux:input name="email" type="email" value="{{ old('email', $user->email) }}" />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>ロール</flux:label>
                <flux:select name="role">
                    <flux:select.option value="admin" :selected="old('role', $user->role->value) === 'admin'">admin</flux:select.option>
                    <flux:select.option value="tenant_admin" :selected="old('role', $user->role->value) === 'tenant_admin'">tenant_admin</flux:select.option>
                    <flux:select.option value="tenant_member" :selected="old('role', $user->role->value) === 'tenant_member'">tenant_member</flux:select.option>
                </flux:select>
                <flux:error name="role" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>新しいパスワード（任意）</flux:label>
                    <flux:input name="password" type="password" />
                    <flux:error name="password" />
                </flux:field>

                <flux:field>
                    <flux:label>新しいパスワード確認</flux:label>
                    <flux:input name="password_confirmation" type="password" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>テナント割り当て（複数選択可）</flux:label>
                <select name="tenant_ids[]" multiple class="w-full rounded-lg border border-zinc-300 p-2 text-sm">
                    @php
                        $selected = old('tenant_ids', $selectedTenantIds);
                    @endphp
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->getId() }}" @selected(in_array($tenant->getId(), $selected, true))>
                            {{ $tenant->getName() }}
                        </option>
                    @endforeach
                </select>
                <flux:error name="tenant_ids" />
            </flux:field>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">更新</flux:button>
                <flux:button href="{{ route('users.index') }}" variant="ghost">キャンセル</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
