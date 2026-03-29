<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('dns_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->enum('provider', ['cloudflare', 'sakura', 'local']);
            $table->string('external_zone_id')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('provider');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_zones');
    }
};
