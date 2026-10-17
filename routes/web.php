<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Modules\Identity\Http\Controllers\RoleController;
use App\Modules\Identity\Http\Controllers\UserController;
use App\Modules\Master\Http\Controllers\BranchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/bahasa/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ---- Pengelolaan (Platform): pengguna, peran, cabang ----
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/pengguna', [UserController::class, 'index'])->name('users.index');
        Route::get('/pengguna/buat', [UserController::class, 'create'])->name('users.create');
        Route::post('/pengguna', [UserController::class, 'store'])->name('users.store');
        Route::get('/pengguna/{user}/ubah', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/pengguna/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/pengguna/{user}/alih-aktif', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    });

    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('/peran', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/peran/{role}/ubah', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('/peran/{role}', [RoleController::class, 'update'])->name('roles.update');
    });

    Route::middleware('permission:branches.manage')->group(function () {
        Route::get('/cabang', [BranchController::class, 'index'])->name('branches.index');
        Route::get('/cabang/buat', [BranchController::class, 'create'])->name('branches.create');
        Route::post('/cabang', [BranchController::class, 'store'])->name('branches.store');
        Route::get('/cabang/{branch}/ubah', [BranchController::class, 'edit'])->name('branches.edit');
        Route::put('/cabang/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::post('/cabang/{branch}/alih-aktif', [BranchController::class, 'toggleActive'])->name('branches.toggle-active');
    });
});

require __DIR__.'/auth.php';
