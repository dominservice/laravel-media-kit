<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('media_assets')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE media_assets MODIFY model_id VARCHAR(64) NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('media_assets')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE media_assets MODIFY model_id BIGINT UNSIGNED NULL');
        }
    }
};
