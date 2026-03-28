<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ProxmoxNode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('非ログインユーザは Proxmox クラスタ一覧にアクセスできない', function (): void {
    $this->get(route('proxmox-clusters.index'))->assertRedirect('/login');
});

test('一般ユーザは Proxmox クラスタ一覧にアクセスできない', function (): void {
    $user = User::factory()->create(['role' => UserRole::TenantMember]);

    $this->actingAs($user)
        ->get(route('proxmox-clusters.index'))
        ->assertForbidden();
});

test('管理者は Proxmox クラスタ一覧を表示できる', function (): void {
    $admin = User::factory()->admin()->create();
    $node = ProxmoxNode::factory()->create(['name' => 'pve-main']);

    $this->actingAs($admin)
        ->get(route('proxmox-clusters.index'))
        ->assertSuccessful()
        ->assertSee('Proxmox クラスタ管理')
        ->assertSee($node->name);
});

test('管理者は Proxmox クラスタ接続を登録できる', function (): void {
    $admin = User::factory()->admin()->create();

    $payload = [
        'name' => 'pve-new',
        'hostname' => '192.168.10.20:8006',
        'api_token_id' => 'root@pam!pve-new',
        'api_token_secret' => 'secret-uuid',
        'snippet_api_url' => 'http://192.168.10.20:8080',
        'snippet_api_token' => 'snippet-token',
    ];

    $this->actingAs($admin)
        ->post(route('proxmox-clusters.store'), $payload)
        ->assertRedirect(route('proxmox-clusters.index'));

    $this->assertDatabaseHas('proxmox_nodes', [
        'name' => 'pve-new',
        'hostname' => '192.168.10.20:8006',
        'api_token_id' => 'root@pam!pve-new',
        'snippet_api_url' => 'http://192.168.10.20:8080',
        'is_active' => false,
    ]);
});

test('有効化すると他ノードは無効化される', function (): void {
    $admin = User::factory()->admin()->create();

    $first = ProxmoxNode::factory()->active()->create();
    $second = ProxmoxNode::factory()->create();

    $this->actingAs($admin)
        ->post(route('proxmox-clusters.activate', $second->id))
        ->assertRedirect(route('proxmox-clusters.index'));

    expect($first->fresh()->is_active)->toBeFalse();
    expect($second->fresh()->is_active)->toBeTrue();
});

test('Proxmox ノード登録時に API トークンが暗号化保存される', function (): void {
    $admin = User::factory()->admin()->create();

    $payload = [
        'name' => 'pve-secure',
        'hostname' => '192.168.10.30:8006',
        'api_token_id' => 'root@pam!pve-secure',
        'api_token_secret' => 'very-secret-token',
        'snippet_api_url' => 'http://192.168.10.30:8080',
        'snippet_api_token' => 'snippet-secret',
    ];

    $this->actingAs($admin)
        ->post(route('proxmox-clusters.store'), $payload)
        ->assertRedirect(route('proxmox-clusters.index'));

    $node = ProxmoxNode::query()->where('name', 'pve-secure')->firstOrFail();

    expect($node->api_token_secret_encrypted)->toBe('very-secret-token');

    $rawCiphertext = DB::table('proxmox_nodes')
        ->where('id', $node->id)
        ->value('api_token_secret_encrypted');

    expect($rawCiphertext)
        ->toBeString()
        ->not->toBe('very-secret-token');
});
