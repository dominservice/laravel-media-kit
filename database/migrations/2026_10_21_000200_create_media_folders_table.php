<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('media_folders')) {
            return;
        }

        Schema::create('media_folders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('media_library_id');
            $table->string('key', 100);
            $table->string('name');
            $table->timestamps();

            $table->foreign('media_library_id', 'media_folders_library_foreign')
                ->references('id')
                ->on('media_libraries')
                ->cascadeOnDelete();
            $table->unique(['media_library_id', 'key'], 'media_folders_library_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_folders');
    }
};
