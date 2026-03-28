<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('vm_metas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('proxmox_vmid')->unique();
            $table->string('proxmox_node', 50);
            $table->string('purpose', 50);
            $table->string('label')->nullable();
            $table->string('shared_ip_address', 45)->nullable();
            $table->enum('provisioning_status', ['pending', 'cloning', 'configuring', 'starting', 'ready', 'error'])->default('pending');
            $table->text('provisioning_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('purpose');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_metas');
    }
};
