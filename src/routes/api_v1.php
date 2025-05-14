<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardDrawerItemController;
use App\Http\Controllers\LayoutItemController;
use App\Http\Controllers\MasterEntryController;
use App\Http\Controllers\SidebarMenuItemController;
use App\Http\Controllers\TableDrawerItemListController;
use App\Http\Controllers\UserAuthEmailVerifyController;
use App\Http\Controllers\UserAuthenticationController;
use App\Http\Controllers\TenantAuthController;
use App\Http\Controllers\TenantPackagesController;
use App\Http\Controllers\PortalWidgetItemManagementController;

Route::get('/status', function (Request $request): string {
    return "API is live";
}); 

Route::prefix('tenant-packages')->group(function () {
    Route::get("/get-all-tenant-packages", [TenantPackagesController::class, "index"]);
});

Route::controller(UserAuthenticationController::class)->group(function () {
    Route::post('user_register', 'registerNewUser');
    Route::post('user_login', 'loginUser');
    Route::post('refresh', 'refreshToken');
    Route::post('drawer_item', 'drawerItems');
    Route::post('dashboard_item', 'dashboardItem');
})->middleware(['cors']);

// Route::controller(TenantAuthController::class)->group(function () {
//     Route::post('tenant_user_login', 'tenantLoginUser');
// })->middleware(['auth:api']);
// Route::controller(TenantAuthController::class)->group(function () {
//     Route::post('tenant_user_register', 'tenantUserRegister');
//     Route::post('tenant_user_data_update', 'updateTenantUserData');
// })->middleware('auth:api');

Route::controller(DashboardDrawerItemController::class)->group(function () {
    Route::post('drawer_item', 'drawerItems');
    Route::post('dashboard_item', 'dashboardItem');
    Route::post('menu_structure', 'storeSidebarMenuItems');
})->middleware(['cors']);

Route::controller(TableDrawerItemListController::class)->group(function () {
    Route::post('add_new_drawer_item', 'storeTableDrawerItemList');
    // Route::get('get_drawer_item_list', 'retrieveDrawerItemList');
    Route::get('get_drawer_item_list', 'retrieveWidgets');
})->middleware(['cors']);

Route::controller(SidebarMenuItemController::class)->group(function () {
    Route::post('menu_structure', 'storeSidebarMenuItems');
    Route::get('get_menu_structure', 'getSidebarMenuItems');
    Route::get('get-menus', 'getSidebarMenus');
})->middleware(['cors']);

Route::controller(UserAuthenticationController::class)->group(function () {
    Route::get('get_user_details', 'getUserDetails');
    Route::post('logout_user', 'userLogout');
})->middleware('auth:api');

Route::controller(PortalWidgetItemManagementController::class)->group(function () {
    // Route::get('/', [LayoutItemController::class, 'index']);
    // Route::put('/', [LayoutItemController::class, 'update']);
    // Route::delete('/{id}', [LayoutItemController::class, 'destroy']);

 
    Route::get('draggable_layout', 'index');
    Route::post('draggable_layout/add_or_update_widget', 'addOrUpdateDashboardLayout');
    Route::delete('draggable_layout/remove/{layout_id}', 'destroy');
})->middleware('auth:api');

Route::post('/email/verify', [UserAuthEmailVerifyController::class, 'verifyUserAuthEmail'])->name('verification.verify');

Route::post("tenant_user_login", [TenantAuthController::class, "tenantLoginUser"])->middleware('throttle:5,1');

Route::group([
    "middleware" => ["auth:api"]
], function(){
    Route::post("tenant_user_register", [TenantAuthController::class, "tenantUserRegister"]); 
    Route::post("tenant_user_data_update", [TenantAuthController::class, "updateTenantUserData"]); 
    Route::post('tenant_user_delete', [TenantAuthController::class, 'tenantUserdestroy']);
    Route::post('tenant_user_status_change', [TenantAuthController::class, 'changeTenantUserStatus']);
    Route::post('tenant_user_paassword', [TenantAuthController::class, 'TenantUserPasswordReset']);
    Route::post('tenant_user_logout', [TenantAuthController::class, 'TenantUserLogout']);
    Route::post('tenant_auth_password_reset', [TenantAuthController::class, 'changeauthuserpassword']);
    Route::post('tenant_auth_password_reset_cancle', [TenantAuthController::class, 'AuthPasswordResetCancle']);
    
    Route::prefix('auth')->group(function () {
        Route::post("/update-theme", [AuthController::class, "updateTheme"]);
        Route::get("/get-theme", [AuthController::class, "gettheme"]);
        Route::post("/forget-password", [AuthController::class, "forgetpassword"]);
        Route::post("auth-user-password-change", [AuthController::class, "resetauthuserpassword"]);
        Route::post("cancle-auth-user-password-change", [AuthController::class, "cancleresetauthuserpassword"]);
        Route::post("/auth-user-logout", [AuthController::class, "logout"]);
    });
});

Route::prefix('auth-user')->group(function () {
    Route::post("/potral-user-login", [AuthController::class, "login"]);
    Route::post("/forget-password", [AuthController::class, "forgetpassword"]);
});

Route::post('tenant_auth_forget_password', [TenantAuthController::class, 'tenantForgotPassword']);

Route::middleware(['auth:api', 'scopes:refresh_portal'])->post('refresh-token', [AuthController::class, 'refreshToken']);

Route::prefix('masterentry')->group(function(){
    Route::get('/get_all_country_codes', [MasterEntryController::class,"getAllCountryCodes"]);
});
