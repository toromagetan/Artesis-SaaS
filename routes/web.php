<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Modules\Catalog\Http\Controllers\ProductController;
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
    // Pencarian global (Blueprint F.2 butir 9)
    Route::get('/cari', [SearchController::class, 'index'])->name('search');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ---- Pengelolaan (Platform): pengguna, peran, cabang ----
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/pengguna', [UserController::class, 'index'])->name('users.index');
        Route::get('/pengguna/cetak', [UserController::class, 'printList'])->name('users.print');
        Route::get('/pengguna/buat', [UserController::class, 'create'])->name('users.create');
        Route::post('/pengguna', [UserController::class, 'store'])->name('users.store');
        Route::get('/pengguna/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('/pengguna/{user}/cetak', [UserController::class, 'printProfile'])->name('users.print-profile');
        Route::get('/pengguna/{user}/ubah', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/pengguna/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/pengguna/{user}/alih-aktif', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    });

    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('/peran', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/peran/{role}/ubah', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('/peran/{role}', [RoleController::class, 'update'])->name('roles.update');
    });

    // ---- Katalog (M.1) ----
    Route::get('/katalog/{type}', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
    Route::get('/katalog/{type}/buat', [ProductController::class, 'create'])->middleware('permission:products.create')->name('products.create');
    Route::post('/katalog/{type}', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
    Route::get('/produk/{product}', [ProductController::class, 'show'])->middleware('permission:products.view')->name('products.show');
    Route::get('/produk/{product}/ubah', [ProductController::class, 'edit'])->middleware('permission:products.update')->name('products.edit');
    Route::put('/produk/{product}', [ProductController::class, 'update'])->middleware('permission:products.update')->name('products.update');
    Route::post('/produk/{product}/alih-aktif', [ProductController::class, 'toggleActive'])->middleware('permission:products.deactivate')->name('products.toggle-active');

    Route::middleware('permission:branches.manage')->group(function () {
        Route::get('/cabang', [BranchController::class, 'index'])->name('branches.index');
        Route::get('/cabang/buat', [BranchController::class, 'create'])->name('branches.create');
        Route::post('/cabang', [BranchController::class, 'store'])->name('branches.store');
        Route::get('/cabang/{branch}', [BranchController::class, 'show'])->name('branches.show');
        Route::get('/cabang/{branch}/ubah', [BranchController::class, 'edit'])->name('branches.edit');
        Route::put('/cabang/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::post('/cabang/{branch}/alih-aktif', [BranchController::class, 'toggleActive'])->name('branches.toggle-active');
    });
});

require __DIR__.'/auth.php';
