<?php

use App\Http\Controllers\Knowledge\Admin\CategoryController;
use App\Http\Controllers\Knowledge\Admin\ResourceController;
use App\Http\Controllers\Knowledge\KnowledgeController;
use Illuminate\Support\Facades\Route;

/*
| Knowledge Hub (owner module M06)
*/
Route::middleware('feature:knowledge')->prefix('knowledge')->name('knowledge.')->group(function () {
    Route::get('/', [KnowledgeController::class, 'index'])->name('index');
    Route::get('/saved', [KnowledgeController::class, 'mine'])->middleware('auth')->name('mine');
    Route::get('/{resource}', [KnowledgeController::class, 'show'])->name('show');
    Route::get('/{resource}/download', [KnowledgeController::class, 'download'])->middleware('throttle:60,1')->name('download');
    Route::post('/{resource}/bookmark', [KnowledgeController::class, 'bookmark'])->middleware(['auth', 'throttle:60,1'])->name('bookmark');
});

Route::prefix('admin/knowledge')->name('admin.knowledge.')->middleware(['auth', 'admin', 'mfa', 'feature:knowledge'])->group(function () {
    // Each action checks manage-knowledge / publish-knowledge in the controller (some pages serve both).
    Route::get('/', [ResourceController::class, 'index'])->name('index');
    Route::middleware('can:manage-knowledge')->group(function () {
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });
    Route::get('/create', [ResourceController::class, 'create'])->name('create');
    Route::post('/', [ResourceController::class, 'store'])->name('store');
    Route::get('/{resource:uuid}/edit', [ResourceController::class, 'edit'])->name('edit');
    Route::put('/{resource:uuid}', [ResourceController::class, 'update'])->name('update');
    Route::post('/{resource:uuid}/submit', [ResourceController::class, 'submit'])->name('submit');
    Route::post('/{resource:uuid}/publish', [ResourceController::class, 'publish'])->name('publish');
    Route::post('/{resource:uuid}/return', [ResourceController::class, 'returnToDraft'])->name('return');
    Route::post('/{resource:uuid}/archive', [ResourceController::class, 'archive'])->name('archive');
    Route::get('/{resource:uuid}/versions', [ResourceController::class, 'versions'])->name('versions');
    Route::get('/{resource:uuid}/file', [ResourceController::class, 'file'])->name('file');
});
