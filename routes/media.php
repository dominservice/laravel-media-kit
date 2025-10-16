<?php

use Illuminate\Support\Facades\Route;
use Dominservice\MediaKit\Http\Controllers\MediaController;

/*
|--------------------------------------------------------------------------
| Trasy pakietu: serwowanie wariantów obrazów
|--------------------------------------------------------------------------
| Przykładowy URL:
|   /media/{asset-uuid}/{variant}/{filename?}
|
| - {asset-uuid}  – UUID rekordu z media_assets
| - {variant}     – np. thumb, sm, md, lg, xl (musi istnieć w configu)
| - {filename?}   – opcjonalne (dla ładniejszych linków), ignorowane przez kontroler
|
| W trybie `eager` kontroler tylko zwraca istniejący wariant.
| W trybie `lazy` kontroler spróbuje wygenerować wariant przy pierwszym żądaniu.
*/

Route::get('/media/{asset}/{variant}/{filename?}', [MediaController::class, 'show'])
    ->whereUuid('asset')
    ->where('variant', '[A-Za-z0-9@_\-]+') // np. md@2x też przejdzie
    ->name('mediakit.media.show');
