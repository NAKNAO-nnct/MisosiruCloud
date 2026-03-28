<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->enum('status', ['active', 'suspended', 'deleted'])->default('active')->index();
            $table->string('vnet_name', 100)->nullable()->unique();
            $table->unsignedInteger('vni')->nullable()->unique();
            $table->string('network_cidr', 18)->nullable();
            $table->string('nomad_namespace', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
