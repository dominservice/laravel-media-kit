<?php

namespace Dominservice\MediaKit\Console;

use Dominservice\MediaKit\Models\MediaAsset;
use Dominservice\MediaKit\Services\ImageEngine;
use Illuminate\Console\Command;

class MediaRegenerate extends Command
{
    protected $signature = 'media:regenerate 
                            {--only-missing : Generuj tylko brakujące warianty} 
                            {--asset= : Ogranicz do konkretnego assetu (UUID)} 
                            {--collection= : Ogranicz do kolekcji (np. featured)}';

    protected $description = 'Regeneruje warianty obrazów dla wszystkich (lub wybranych) assetów.';

    public function handle(): int
    {
        $q = MediaAsset::query();

        if ($id = (string) $this->option('asset')) {
            $q->whereKey($id);
        }
        if ($col = (string) $this->option('collection')) {
            $q->where('collection', $col);
        }

        $onlyMissing = (bool) $this->option('only-missing');

        $total = 0;
        $this->info('Start regeneracji wariantów…');

        $q->orderBy('id')->chunk(100, function ($assets) use (&$total, $onlyMissing) {
            foreach ($assets as $asset) {
                if ($onlyMissing) {
                    $this->regenerateMissing($asset);
                } else {
                    ImageEngine::generateAllVariants($asset);
                }
                $total++;
                if ($total % 50 === 0) {
                    $this->line("…przetworzono {$total} assetów");
                }
            }
        });

        $this->info("Zakończono. Przetworzono {$total} assetów.");
        return self::SUCCESS;
    }

    protected function regenerateMissing(MediaAsset $asset): void
    {
        $variants = config('media-kit.variants', []);
        $formatsPerVariant = config('media-kit.enabled_formats_per_variant', []);
        $fallbackFormats = config('media-kit.formats_priority', ['avif','webp','jpeg','png']);

        foreach ($variants as $name => $rules) {
            $enabled = $formatsPerVariant[$name] ?? ($formatsPerVariant['*'] ?? $fallbackFormats);
            foreach ($enabled as $fmt) {
                $exists = $asset->variants()->where(['name' => $name, 'format' => $fmt])->exists();
                if (! $exists) {
                    ImageEngine::generateVariant($asset, (string)$name, (array)$rules, (string)$fmt, $asset->disk, $asset->original_path);
                }
            }
        }
    }
}
