<x-layouts::guest :title="__('新規登録')">
    <div class="w-full max-w-md space-y-6 px-6">
        <div class="text-center">
            <flux:heading size="xl">{{ config('app.name') }}</flux:heading>
            <flux:subheading>新規アカウント作成</flux:subheading>
        </div>

        <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
            @csrf

            <flux:field>
                <flux:label>名前</flux:label>
                <flux:input type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>メールアドレス</flux:label>
                <flux:input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>パスワード</flux:label>
                <flux:input type="password" name="password" required autocomplete="new-password" />
                <flux:error name="password" />
            </flux:field>

            <flux:field>
                <flux:label>パスワード（確認）</flux:label>
                <flux:input type="password" name="password_confirmation" required autocomplete="new-password" />
                <flux:error name="password_confirmation" />
            </flux:field>

            <flux:button type="submit" variant="primary" class="w-full">
                登録する
            </flux:button>
        </form>

        <p class="text-center text-sm text-zinc-500">
            すでにアカウントをお持ちの方は
            <a href="{{ route('login') }}" class="text-zinc-700 underline hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-zinc-100">
                ログイン
            </a>
        </p>
    </div>
</x-layouts::guest>
