<?php

declare(strict_types=1);

use App\Models\User;

it('dashboard requires authentication', function (): void {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('authenticated user can access dashboard', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful();
});

it('unverified user is redirected from dashboard', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/email/verify');
});
