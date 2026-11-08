<?php

use App\Http\Controllers\Organizations\AdminOrganizationController;
use App\Http\Controllers\Organizations\MemberController;
use App\Http\Controllers\Organizations\PortalController;
use App\Http\Controllers\Organizations\WorkspaceOrganizationController;
use Illuminate\Support\Facades\Route;

/*
| M01 — Organizations (tenants) and startup-approved portfolio access.
*/

// Organization portal
Route::middleware(['auth', 'feature:organizations'])->prefix('organizations')->name('organizations.')->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('index');
    Route::get('/invitations/{token}', [MemberController::class, 'showInvitation'])->name('invitations.show');
    Route::post('/invitations/{token}/accept', [MemberController::class, 'acceptInvitation'])->middleware('throttle:10,1')->name('invitations.accept');
    Route::post('/invitations/{token}/decline', [MemberController::class, 'declineInvitation'])->name('invitations.decline');

    Route::get('/{organization}', [PortalController::class, 'show'])->name('show');
    Route::post('/{organization}/switch', [PortalController::class, 'switch'])->name('switch');
    Route::get('/{organization}/startups/{startup}', [PortalController::class, 'startup'])->name('startup');
    Route::post('/{organization}/requests', [PortalController::class, 'requestAccess'])->middleware('throttle:30,60')->name('requests.store');
    Route::post('/{organization}/relationships/{relationship}/withdraw', [PortalController::class, 'withdraw'])->name('relationships.withdraw');

    Route::get('/{organization}/members', [MemberController::class, 'index'])->name('members');
    Route::post('/{organization}/members', [MemberController::class, 'invite'])->middleware('throttle:30,1')->name('members.invite');
    Route::put('/{organization}/members/{member}', [MemberController::class, 'updateRole'])->name('members.role');
    Route::delete('/{organization}/members/{member}', [MemberController::class, 'remove'])->name('members.remove');
});

// Startup side: approve, decline, revoke, share (owners and founders decide)
Route::middleware(['auth', 'feature:registry', 'feature:organizations'])->prefix('workspace/startups/{startup}/organizations')->name('workspace.organizations')->group(function () {
    Route::get('/', [WorkspaceOrganizationController::class, 'index'])->name('');
    Route::post('/share', [WorkspaceOrganizationController::class, 'share'])->middleware('throttle:30,60')->name('.share');
    Route::post('/{relationship}/decision', [WorkspaceOrganizationController::class, 'decide'])->name('.decide');
    Route::post('/{relationship}/revoke', [WorkspaceOrganizationController::class, 'revoke'])->name('.revoke');
});

// Platform staff
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'mfa', 'can:manage-organizations', 'feature:organizations'])->group(function () {
    Route::get('organizations', [AdminOrganizationController::class, 'index'])->name('organizations.index');
    Route::post('organizations', [AdminOrganizationController::class, 'store'])->name('organizations.store');
    Route::get('organizations/{organization}', [AdminOrganizationController::class, 'show'])->name('organizations.show');
    Route::put('organizations/{organization}', [AdminOrganizationController::class, 'update'])->name('organizations.update');
    Route::post('organizations/{organization}/members', [AdminOrganizationController::class, 'members'])->name('organizations.members');
});
