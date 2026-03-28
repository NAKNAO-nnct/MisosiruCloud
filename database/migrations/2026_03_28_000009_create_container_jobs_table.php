<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('container_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nomad_job_id')->unique();
            $table->string('name');
            $table->string('image', 500);
            $table->string('domain')->nullable();
            $table->unsignedInteger('replicas')->default(1);
            $table->unsignedInteger('cpu_mhz');
            $table->unsignedInteger('memory_mb');
            $table->json('port_mappings')->nullable();
            $table->text('env_vars_encrypted')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_jobs');
    }
};
