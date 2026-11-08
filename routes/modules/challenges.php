<?php

use App\Http\Controllers\Challenges\AdminChallengeController;
use App\Http\Controllers\Challenges\ChallengeController;
use App\Http\Controllers\Challenges\MyChallengeController;
use Illuminate\Support\Facades\Route;

/*
| Owner module M05: Challenge Exchange.
*/
Route::middleware('feature:challenges')->group(function () {
    Route::prefix('challenges')->name('challenges.')->group(function () {
        Route::get('/', [ChallengeController::class, 'index'])->name('index');

        Route::middleware('auth')->group(function () {
            Route::get('/mine', [MyChallengeController::class, 'mine'])->name('mine');
            Route::get('/new', [MyChallengeController::class, 'create'])->name('create');
            Route::post('/', [MyChallengeController::class, 'store'])->middleware('throttle:10,60')->name('store');
            Route::get('/proposals/{proposal}', [ChallengeController::class, 'proposal'])->whereUuid('proposal')->name('proposals.show');
            Route::get('/{challenge}/edit', [MyChallengeController::class, 'edit'])->whereUuid('challenge')->name('edit');
            Route::put('/{challenge}', [MyChallengeController::class, 'update'])->whereUuid('challenge')->name('update');
            Route::post('/{challenge}/submit', [MyChallengeController::class, 'submit'])->whereUuid('challenge')->name('submit');
            Route::post('/{challenge}/evidence', [MyChallengeController::class, 'storeEvidence'])->whereUuid('challenge')->middleware('throttle:30,60')->name('evidence.store');
            Route::delete('/{challenge}/evidence/{evidence}', [MyChallengeController::class, 'destroyEvidence'])->whereUuid('challenge')->name('evidence.destroy');
            Route::get('/{challenge}/evidence/{evidence}/download', [MyChallengeController::class, 'download'])->whereUuid('challenge')->name('evidence.download');
            Route::post('/{challenge}/bookmark', [ChallengeController::class, 'bookmark'])->whereUuid('challenge')->middleware('throttle:60,1')->name('bookmark');
            Route::post('/{challenge}/propose', [ChallengeController::class, 'propose'])->whereUuid('challenge')->middleware('throttle:5,60')->name('propose');
        });

        Route::get('/{challenge}', [ChallengeController::class, 'show'])->whereUuid('challenge')->name('show');
    });

    Route::middleware(['auth', 'admin', 'mfa'])->prefix('admin/challenges')->name('admin.challenges.')->group(function () {
        Route::get('/', [AdminChallengeController::class, 'index'])->name('index');
        Route::middleware('can:manage-challenges')->group(function () {
            Route::get('/new', [AdminChallengeController::class, 'create'])->name('create');
            Route::post('/', [AdminChallengeController::class, 'store'])->name('store');
            Route::get('/{challenge}/edit', [AdminChallengeController::class, 'edit'])->name('edit');
            Route::put('/{challenge}', [AdminChallengeController::class, 'update'])->name('update');
            Route::post('/{challenge}/submit', [AdminChallengeController::class, 'submit'])->name('submit');
            Route::post('/{challenge}/evidence', [AdminChallengeController::class, 'storeEvidence'])->name('evidence.store');
            Route::delete('/{challenge}/evidence/{evidence}', [AdminChallengeController::class, 'destroyEvidence'])->name('evidence.destroy');
        });
        Route::middleware('can:review-challenges')->group(function () {
            Route::post('/{challenge}/review', [AdminChallengeController::class, 'review'])->name('review');
            Route::post('/{challenge}/publish', [AdminChallengeController::class, 'publish'])->name('publish');
            Route::post('/{challenge}/archive', [AdminChallengeController::class, 'archive'])->name('archive');
            Route::post('/{challenge}/evidence/{evidence}/validate', [AdminChallengeController::class, 'validateEvidence'])->name('evidence.validate');
            Route::get('/{challenge}/proposals', [AdminChallengeController::class, 'proposals'])->name('proposals');
            Route::put('/{challenge}/proposals/{proposal}', [AdminChallengeController::class, 'updateProposal'])->name('proposals.update');
        });
        Route::get('/{challenge}', [AdminChallengeController::class, 'show'])->name('show');
    });
});
