<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\DnsRecord;
use App\Models\DnsZone;
use App\Models\User;

test('一般ユーザは phase13 dns zone 管理にアクセスできない', function (): void {
    $user = User::factory()->create(['role' => UserRole::TenantMember]);

    $this->actingAs($user)
        ->get(route('dns-zones.index'))
        ->assertForbidden();
});

test('管理者は dns zone を作成して一覧表示できる', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('dns-zones.store'), [
            'name' => 'local.override',
            'provider' => 'local',
            'description' => 'local split horizon',
            'is_active' => true,
        ])
        ->assertRedirect(route('dns-zones.index'));

    $this->actingAs($admin)
        ->get(route('dns-zones.index'))
        ->assertSuccessful()
        ->assertSee('DNS ゾーン管理')
        ->assertSee('local.override');
});

test('管理者は dns zone を更新できる', function (): void {
    $admin = User::factory()->admin()->create();
    $zone = DnsZone::factory()->create([
        'name' => 'infra.example.com',
        'provider' => 'sakura',
        'external_zone_id' => 'zone-old',
        'description' => 'before update',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('dns-zones.update', $zone->id), [
            'name' => 'infra.example.com',
            'provider' => 'sakura',
            'external_zone_id' => 'zone-new',
            'description' => 'after update',
            'is_active' => false,
        ])
        ->assertRedirect(route('dns-zones.index'));

    $this->assertDatabaseHas('dns_zones', [
        'id' => $zone->id,
        'external_zone_id' => 'zone-new',
        'description' => 'after update',
        'is_active' => false,
    ]);
});

test('管理者は local zone のレコードを作成・更新・削除できる', function (): void {
    $admin = User::factory()->admin()->create();
    $zone = DnsZone::factory()->create([
        'name' => 'local.override',
        'provider' => 'local',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('dns-zones.records.store', $zone->id), [
            'name' => 'registry',
            'type' => 'A',
            'content' => '172.26.26.10',
            'ttl' => 300,
        ])
        ->assertRedirect(route('dns-zones.records.index', $zone->id));

    /** @var DnsRecord $record */
    $record = DnsRecord::query()->where('dns_zone_id', $zone->id)->firstOrFail();

    $this->actingAs($admin)
        ->put(route('dns-zones.records.update', [$zone->id, $record->id]), [
            'name' => 'registry',
            'type' => 'A',
            'content' => '172.26.26.11',
            'ttl' => 600,
        ])
        ->assertRedirect(route('dns-zones.records.index', $zone->id));

    $this->assertDatabaseHas('dns_records', [
        'id' => $record->id,
        'content' => '172.26.26.11',
        'ttl' => 600,
    ]);

    $this->actingAs($admin)
        ->delete(route('dns-zones.records.destroy', [$zone->id, $record->id]))
        ->assertRedirect(route('dns-zones.records.index', $zone->id));

    $this->assertDatabaseMissing('dns_records', ['id' => $record->id]);
});
