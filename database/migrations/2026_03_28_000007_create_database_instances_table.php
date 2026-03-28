<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('database_instances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vm_meta_id')->constrained()->cascadeOnDelete();
            $table->enum('db_type', ['mysql', 'postgres', 'redis']);
            $table->string('db_version', 20);
            $table->unsignedInteger('port');
            $table->string('admin_user', 100);
            $table->text('admin_password_encrypted');
            $table->string('tenant_user', 100)->nullable();
            $table->text('tenant_password_encrypted')->nullable();
            $table->text('backup_encryption_key_encrypted')->nullable();
            $table->enum('status', ['provisioning', 'running', 'stopped', 'error', 'upgrading'])->default('provisioning');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('vm_meta_id');
            $table->index('db_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_instances');
    }
};
