<x-layouts::guest :title="__('メールアドレスの確認')">
    <div class="w-full max-w-md space-y-6 px-6">
        <div class="text-center">
            <flux:heading size="xl">メールアドレスの確認</flux:heading>
            <flux:subheading>
                登録いただいたメールアドレスに確認メールを送信しました。メール内のリンクをクリックして確認を完了してください。
            </flux:subheading>
        </div>

        @if (session('status') === 'verification-link-sent')
            <flux:callout variant="success" icon="check-circle">
                確認メールを再送しました。受信トレイをご確認ください。
            </flux:callout>
        @endif

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <flux:button type="submit" variant="primary" class="w-full">
                確認メールを再送する
            </flux:button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:button type="submit" variant="ghost" class="w-full">
                ログアウト
            </flux:button>
        </form>
    </div>
</x-layouts::guest>
