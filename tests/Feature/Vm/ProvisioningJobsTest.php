<?php

declare(strict_types=1);

use App\Enums\VmStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VmMeta;

it('プロビジョニングジョブ一覧ページは管理者のみアクセス可能', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('vms.provisioning-jobs'))
        ->assertForbidden();
});

it('管理者はプロビジョニングジョブ一覧を表示できる', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create();

    // エラー状態のジョブを作成
    $vmMeta = VmMeta::factory()
        ->for($tenant)
        ->create([
            'provisioning_status' => VmStatus::Error,
            'provisioning_error' => 'Clone failed',
        ]);

    $this->actingAs($admin)
        ->get(route('vms.provisioning-jobs'))
        ->assertSuccessful()
        ->assertSee('プロビジョニングジョブ一覧')
        ->assertSee($vmMeta->label)
        ->assertSee('Clone failed');
});

it('メタデータを削除できる', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create();

    $vmMeta = VmMeta::factory()
        ->for($tenant)
        ->create([
            'provisioning_status' => VmStatus::Error,
        ]);

    $this->actingAs($admin)
        ->delete(route('vms.meta.destroy', $vmMeta->id))
        ->assertRedirect(route('vms.provisioning-jobs'))
        ->assertSessionHas('success', 'VM メタデータを削除しました。');

    // メタデータが硬削除（forceDelete）されていることを確認
    expect(VmMeta::withTrashed()->find($vmMeta->id))->toBeNull();
});

it('error 以外のステータスのメタデータは削除ボタンが表示されない', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create();

    $vmMeta = VmMeta::factory()
        ->for($tenant)
        ->create([
            'provisioning_status' => VmStatus::Ready,
        ]);

    $response = $this->actingAs($admin)
        ->get(route('vms.provisioning-jobs'))
        ->assertSuccessful();

    // error ステータスでないため、削除フォームが表示されない
    $html = $response->getContent();
    expect($html)->not->toContain(route('vms.meta.destroy', $vmMeta->id));
});

it('複数のジョブが最新順表示される', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create();

    $vm1 = VmMeta::factory()->for($tenant)->create();
    sleep(1);  // created_at の差を作る
    $vm2 = VmMeta::factory()->for($tenant)->create();

    $response = $this->actingAs($admin)
        ->get(route('vms.provisioning-jobs'))
        ->assertSuccessful();

    // $vm2 が先に出現することを確認（降順）
    $pos1 = mb_strpos($response->getContent(), (string) $vm1->proxmox_vmid);
    $pos2 = mb_strpos($response->getContent(), (string) $vm2->proxmox_vmid);

    expect($pos2)->toBeLessThan($pos1);
});
