<?php

use App\Http\Controllers\Api\RegistryApiController;
use Illuminate\Support\Facades\Route;

/*
| Registry API v1 for connected systems (docs/integration-contract-v1.md).
| Every route needs an API client; all of it is off unless the
| ims_integration feature flag is on.
*/
Route::prefix('v1')->middleware('throttle:api-clients')->group(function () {
    Route::post('connect/token', [RegistryApiController::class, 'token'])->middleware('api.client:registry.read');
    Route::get('startups/{uuid}', [RegistryApiController::class, 'showStartup'])->middleware('api.client:registry.read');
    Route::post('startup-links', [RegistryApiController::class, 'link'])->middleware('api.client:registry.link');
    Route::post('events', [RegistryApiController::class, 'events'])->middleware('api.client:events.write');
    Route::get('integration/sync-status/{uuid}', [RegistryApiController::class, 'syncStatus'])->middleware('api.client:registry.read');
});
