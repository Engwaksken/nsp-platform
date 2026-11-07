<?php

use App\Http\Controllers\Capital\InvestorCapitalController;
use App\Http\Controllers\Workspace\CapitalController;
use Illuminate\Support\Facades\Route;

/*
| M08 Capital Exchange (owner alignment). Extends Phase 5b funding:
| founder controls (sharing scopes, diligence, document requests, readiness,
| activity) and the investor's pipeline, watchlist and diligence pages.
| Uses the existing "funding" feature flag.
*/
Route::middleware(['auth', 'feature:registry', 'feature:funding'])->group(function () {
    Route::prefix('workspace/startups/{startup}/capital')->name('workspace.capital.')->group(function () {
        Route::get('/', [CapitalController::class, 'index'])->name('index');
        Route::put('/profile', [CapitalController::class, 'saveProfile'])->name('profile');
        Route::post('/shares', [CapitalController::class, 'grantScopes'])->name('shares.store');
        Route::delete('/shares/{share}', [CapitalController::class, 'revokeScopes'])->name('shares.destroy');
        Route::put('/diligence/{item}', [CapitalController::class, 'updateItem'])->name('diligence.update');
        Route::post('/document-requests/{documentRequest}/fulfil', [CapitalController::class, 'fulfil'])->middleware('throttle:30,1')->name('documents.fulfil');
        Route::post('/document-requests/{documentRequest}/decline', [CapitalController::class, 'decline'])->name('documents.decline');
        Route::post('/match-feedback', [CapitalController::class, 'feedback'])->middleware('throttle:30,1')->name('feedback');
        Route::get('/readiness', [CapitalController::class, 'readiness'])->name('readiness');
        Route::get('/activity', [CapitalController::class, 'activity'])->name('activity');
    });
});

Route::middleware(['auth', 'feature:funding'])->prefix('investors')->name('investor.capital.')->group(function () {
    Route::get('/pipeline', [InvestorCapitalController::class, 'pipeline'])->name('pipeline');
    Route::post('/startups/{startup}/pipeline', [InvestorCapitalController::class, 'transition'])->middleware('throttle:60,1')->name('transition');
    Route::post('/startups/{startup}/watch', [InvestorCapitalController::class, 'watch'])->name('watch');
    Route::delete('/startups/{startup}/watch', [InvestorCapitalController::class, 'unwatch'])->name('unwatch');
    Route::get('/startups/{startup}/readiness', [InvestorCapitalController::class, 'readiness'])->name('readiness');
    Route::get('/startups/{startup}/diligence', [InvestorCapitalController::class, 'diligence'])->name('diligence');
    Route::post('/startups/{startup}/diligence', [InvestorCapitalController::class, 'addItems'])->middleware('throttle:30,1')->name('diligence.store');
    Route::put('/diligence/{item}', [InvestorCapitalController::class, 'updateItem'])->name('diligence.update');
    Route::post('/startups/{startup}/document-requests', [InvestorCapitalController::class, 'requestDocument'])->middleware('throttle:30,1')->name('documents.store');
    Route::post('/startups/{startup}/match-feedback', [InvestorCapitalController::class, 'feedback'])->middleware('throttle:30,1')->name('feedback');
});
