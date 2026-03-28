<?php

declare(strict_types=1);

use App\Http\Controllers\Dashboard\IndexController as DashboardIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardIndex::class)->name('dashboard');
});

require __DIR__ . '/settings.php';
