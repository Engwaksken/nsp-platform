<?php

use App\Http\Controllers\Admin\BuilderCurriculumController;
use App\Http\Controllers\Workspace\BuilderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'feature:venture_builder'])->prefix('workspace/startups/{startup}/builder')->name('workspace.builder')->group(function () {
    Route::get('/', [BuilderController::class, 'index'])->name('');
    Route::post('/start', [BuilderController::class, 'start'])->name('.start');
    Route::get('/steps/{submission}', [BuilderController::class, 'edit'])->whereNumber('submission')->name('.step');
    Route::put('/steps/{submission}', [BuilderController::class, 'save'])->whereNumber('submission')->name('.step.save');
    Route::post('/steps/{submission}/submit', [BuilderController::class, 'submit'])->whereNumber('submission')->name('.step.submit');
    Route::post('/steps/{submission}/review', [BuilderController::class, 'review'])->whereNumber('submission')->middleware('can:review-builder-steps')->name('.step.review');
    Route::post('/steps/{submission}/evidence', [BuilderController::class, 'addEvidence'])->whereNumber('submission')->name('.step.evidence');
    Route::get('/steps/{submission}/evidence/{evidence}', [BuilderController::class, 'downloadEvidence'])->whereNumber('submission')->whereNumber('evidence')->name('.step.evidence.download');
    Route::delete('/steps/{submission}/evidence/{evidence}', [BuilderController::class, 'deleteEvidence'])->whereNumber('submission')->whereNumber('evidence')->name('.step.evidence.delete');
    Route::post('/steps/{submission}/document/generate', [BuilderController::class, 'generateDocument'])->whereNumber('submission')->name('.step.document.generate');
    Route::put('/steps/{submission}/document/{document}', [BuilderController::class, 'saveDocument'])->whereNumber('submission')->whereNumber('document')->name('.step.document.save');
    Route::get('/steps/{submission}/document/{document}/export', [BuilderController::class, 'exportDocument'])->whereNumber('submission')->whereNumber('document')->name('.step.document.export');
});

Route::middleware(['auth', 'admin', 'mfa', 'feature:venture_builder'])
    ->prefix('admin/builder')->name('admin.builder.')
    ->group(function () {
        Route::middleware('can:manage-builder-curricula')->group(function () {
            Route::get('/', [BuilderCurriculumController::class, 'index'])->name('index');
            Route::post('/new', [BuilderCurriculumController::class, 'createDraft'])->name('create');
            Route::get('/{curriculum}/edit', [BuilderCurriculumController::class, 'edit'])->name('edit');
            Route::put('/{curriculum}', [BuilderCurriculumController::class, 'update'])->name('update');
        });
        Route::post('/{curriculum}/publish', [BuilderCurriculumController::class, 'publish'])
            ->middleware('can:publish-builder-curricula')->name('publish');
    });
