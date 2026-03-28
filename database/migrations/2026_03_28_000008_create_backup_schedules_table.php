<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('database_instance_id')->constrained()->cascadeOnDelete();
            $table->string('cron_expression', 100)->default('0 3 * * *');
            $table->unsignedInteger('retention_daily')->default(7);
            $table->unsignedInteger('retention_weekly')->default(4);
            $table->unsignedInteger('retention_monthly')->default(3);
            $table->timestamp('last_backup_at')->nullable();
            $table->enum('last_backup_status', ['success', 'failed', 'running'])->nullable();
            $table->unsignedBigInteger('last_backup_size_bytes')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index('database_instance_id');
            $table->index('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
    }
};
