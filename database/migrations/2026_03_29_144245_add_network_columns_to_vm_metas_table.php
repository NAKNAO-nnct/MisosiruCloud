<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('vm_metas', function (Blueprint $table): void {
            $table->string('ip_address', 45)->nullable()->after('shared_ip_address');
            $table->string('gateway', 45)->nullable()->after('ip_address');
            $table->string('vnet_name', 64)->nullable()->after('gateway');
            $table->text('ssh_keys')->nullable()->after('vnet_name');
        });

        DB::statement("ALTER TABLE vm_metas MODIFY COLUMN provisioning_status ENUM('pending','uploading','cloning','configuring','starting','ready','error') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE vm_metas MODIFY COLUMN provisioning_status ENUM('pending','cloning','configuring','starting','ready','error') DEFAULT 'pending'");

        Schema::table('vm_metas', function (Blueprint $table): void {
            $table->dropColumn(['ip_address', 'gateway', 'vnet_name', 'ssh_keys']);
        });
    }
};
