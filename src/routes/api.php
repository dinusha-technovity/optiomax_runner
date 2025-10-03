<?php

use App\Http\Controllers\TenantPackagesController;
use Illuminate\Support\Facades\Route;

// ...existing routes...

Route::prefix('tenant-packages')->group(function () {
    Route::get("/get-all-tenant-packages", [TenantPackagesController::class, "index"]);
    Route::get("/get-packages-with-addons", [TenantPackagesController::class, "getPackagesWithAddons"]);
    Route::get("/package/{id}", [TenantPackagesController::class, "getPackageDetails"]);
});

// ...existing routes...