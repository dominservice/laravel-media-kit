<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Główna tabela plików
        Schema::create('media_assets', function (Blueprint $table) {
            // UUID jako klucz główny (kompatybilnie dla L9–L12)
            $table->uuid()->primary();

            // Polimorficzne powiązanie z modelem (model_type, model_id NULLable)
            $table->nullableMorphs('model');

            // Dodatkowe meta-dane
            $table->string('collection')->default('default');
            $table->string('disk');
            $table->string('original_path');
            $table->string('original_mime')->nullable();
            $table->string('original_ext', 12)->nullable();
            $table->unsignedBigInteger('original_size')->nullable();

            // Wymiary obrazu (jeśli dotyczy)
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Hash zawartości (ułatwia deduplikację)
            $table->string('hash', 64)->index();

            // Dodatkowe informacje (EXIF, IPTC, focal point itp.)
            $table->json('meta')->nullable();

            $table->timestamps();

            // Indeksy pomocnicze
            $table->index(['collection']);
            $table->index(['disk']);
            $table->index(['model_type', 'model_id'], 'media_assets_model_index');
        });

        // Tabela wariantów (format, rozmiar, ścieżka)
        Schema::create('media_variants', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->uuid('asset_uuid');                 // FK do media_assets.id
            $table->string('name');                   // np. thumb, sm, md, lg, xl
            $table->string('format', 8);              // avif|webp|jpeg|png
            $table->string('disk');                   // zwykle taki sam jak w assets
            $table->string('path');                   // ścieżka do pliku na dysku

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedTinyInteger('quality')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamp('generated_at')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            // Ograniczenia i indeksy
            $table->foreign('asset_uuid')->references('uuid')->on('media_assets')->cascadeOnDelete();
            $table->unique(['asset_uuid', 'name', 'format']); // 1 wariant/form at per asset
            $table->index(['name', 'format']);
            $table->index(['disk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_variants');
        Schema::dropIfExists('media_assets');
    }
};
