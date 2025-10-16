<?php

namespace Dominservice\MediaKit\Console;

use Dominservice\MediaKit\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MediaCleanup extends Command
{
    protected $signature = 'media:cleanup 
                            {--dry-run : Pokaż, co byłoby usunięte, ale nie usuwaj}
                            {--prefix=media/variants : Katalog wariantów do skanowania na dysku}';

    protected $description = 'Usuwa osierocone pliki wariantów (których nie ma w bazie).';

    public function handle(): int
    {
        $disk = (string) config('media-kit.disk', 'public');
        $prefix = (string) $this->option('prefix');
        $dry = (bool) $this->option('dry-run');

        // Zbierz wszystkie ścieżki wariantów zapisane w DB
        $valid = [];
        MediaAsset::with('variants')->chunk(500, function ($assets) use (&$valid) {
            foreach ($assets as $asset) {
                foreach ($asset->variants as $v) {
                    $valid[$v->path] = true;
                }
            }
        });

        $all = Storage::disk($disk)->allFiles($prefix);
        $deleted = 0;
        $kept = 0;

        foreach ($all as $file) {
            if (!isset($valid[$file])) {
                if ($dry) {
                    $this->line("[DRY] delete: {$file}");
                } else {
                    Storage::disk($disk)->delete($file);
                }
                $deleted++;
            } else {
                $kept++;
            }
        }

        $this->info(($dry ? '[DRY] ' : '')."Usunięto: {$deleted}, pozostawiono: {$kept}");
        return self::SUCCESS;
    }
}
