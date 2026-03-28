<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('vps_gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('global_ip', 45)->unique();
            $table->string('wireguard_ip', 45)->unique();
            $table->unsignedInteger('wireguard_port')->default(51820);
            $table->string('wireguard_public_key', 44);
            $table->unsignedInteger('transit_wireguard_port');
            $table->enum('status', ['active', 'maintenance', 'inactive'])->default('active')->index();
            $table->string('purpose')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vps_gateways');
    }
};
