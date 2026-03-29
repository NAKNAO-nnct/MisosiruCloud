<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('dns_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dns_zone_id')->constrained('dns_zones')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['A', 'AAAA', 'CNAME', 'NS', 'TXT', 'MX', 'SRV']);
            $table->string('content', 255);
            $table->unsignedInteger('ttl')->default(300);
            $table->unsignedInteger('priority')->nullable();
            $table->string('external_id')->nullable();
            $table->string('comment')->nullable();
            $table->timestamps();

            $table->unique(['dns_zone_id', 'name', 'type', 'content']);
            $table->index(['dns_zone_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_records');
    }
};
