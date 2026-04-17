<?php

use App\Http\Controllers\Admin\AccountController as AdminAccountController;
use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Admin\BlocksController as AdminBlocksController;
use App\Http\Controllers\Admin\CacheController as AdminCacheController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FormsController as AdminFormsController;
use App\Http\Controllers\Admin\FormSubmissionsController as AdminFormSubmissionsController;
use App\Http\Controllers\Admin\MaintenanceController as AdminMaintenanceController;
use App\Http\Controllers\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Admin\MenuController as AdminMenuController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PermissionsController as AdminPermissionsController;
use App\Http\Controllers\Admin\PluginsController as AdminPluginsController;
use App\Http\Controllers\Admin\RedirectsController as AdminRedirectsController;
use App\Http\Controllers\Admin\RolesController as AdminRolesController;
use App\Http\Controllers\Admin\Settings\EmailController as AdminEmailSettingsController;
use App\Http\Controllers\Admin\Settings\GeneralController as AdminGeneralSettingsController;
use App\Http\Controllers\Admin\Settings\ThemesController as AdminThemesController;
use App\Http\Controllers\Admin\UsersController as AdminUsersController;
use App\Http\Controllers\FormSubmissionController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::prefix('admin')->middleware(['auth', 'verified', 'admin.role'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

    // Account (logged-in user profile & password)
    Route::get('account', [AdminAccountController::class, 'index'])->name('admin.account.index');
    Route::put('account/profile', [AdminAccountController::class, 'updateProfile'])->name('admin.account.profile');
    Route::put('account/password', [AdminAccountController::class, 'updatePassword'])->name('admin.account.password');

    // Custom page routes must be defined before the resource to avoid {page} wildcard conflicts
    Route::post('pages/reorder', [AdminPageController::class, 'reorder'])->name('admin.pages.reorder');
    Route::post('pages/bulk', [AdminPageController::class, 'bulkAction'])->name('admin.pages.bulk');
    Route::get('pages/trash', [AdminPageController::class, 'trash'])->name('admin.pages.trash');
    Route::patch('pages/{id}/restore', [AdminPageController::class, 'restore'])->name('admin.pages.restore');
    Route::delete('pages/{id}/force', [AdminPageController::class, 'forceDelete'])->name('admin.pages.force-delete');
    Route::post('pages/{page}/duplicate', [AdminPageController::class, 'duplicate'])->name('admin.pages.duplicate');
    Route::get('pages/{page}/preview', [AdminPageController::class, 'preview'])->name('admin.pages.preview');
    Route::post('pages/{page}/preview', [AdminPageController::class, 'previewLive'])->name('admin.pages.preview.live');

    Route::resource('pages', AdminPageController::class)->except(['show'])->names([
        'index' => 'admin.pages.index',
        'create' => 'admin.pages.create',
        'store' => 'admin.pages.store',
        'edit' => 'admin.pages.edit',
        'update' => 'admin.pages.update',
        'destroy' => 'admin.pages.destroy',
    ]);
    Route::prefix('settings')->group(function () {
        Route::get('/', [AdminGeneralSettingsController::class, 'index'])->name('admin.settings.index');
        Route::put('/', [AdminGeneralSettingsController::class, 'update'])->name('admin.settings.update');

        Route::get('email', [AdminEmailSettingsController::class, 'index'])->name('admin.settings.email.index');
        Route::put('email', [AdminEmailSettingsController::class, 'update'])->name('admin.settings.email.update');
        Route::post('email/test', [AdminEmailSettingsController::class, 'testSend'])->name('admin.settings.email.test');
    });

    // Themes
    Route::get('themes', [AdminThemesController::class, 'index'])->name('admin.themes.index');
    Route::post('themes/{theme}/activate', [AdminThemesController::class, 'activate'])->name('admin.themes.activate');

    // Plugins
    Route::get('plugins', [AdminPluginsController::class, 'index'])->name('admin.plugins.index');
    Route::post('plugins/{plugin}/toggle', [AdminPluginsController::class, 'toggle'])->name('admin.plugins.toggle');

    // Blocks
    Route::get('blocks', [AdminBlocksController::class, 'index'])->name('admin.blocks.index');

    // Users
    Route::get('users', [AdminUsersController::class, 'index'])->name('admin.users.index');
    Route::post('users', [AdminUsersController::class, 'store'])->name('admin.users.store');
    Route::put('users/{user}/roles', [AdminUsersController::class, 'updateRoles'])->name('admin.users.update-roles');
    Route::put('users/{user}/permissions', [AdminUsersController::class, 'updatePermissions'])->name('admin.users.update-permissions');
    Route::delete('users/{user}', [AdminUsersController::class, 'destroy'])->name('admin.users.destroy');

    // Roles & Permissions (mutations only — data served via users.index)
    Route::post('roles', [AdminRolesController::class, 'store'])->name('admin.roles.store');
    Route::put('roles/{role}', [AdminRolesController::class, 'update'])->name('admin.roles.update');
    Route::delete('roles/{role}', [AdminRolesController::class, 'destroy'])->name('admin.roles.destroy');

    // Permissions
    Route::post('permissions', [AdminPermissionsController::class, 'store'])->name('admin.permissions.store');
    Route::delete('permissions/{permission}', [AdminPermissionsController::class, 'destroy'])->name('admin.permissions.destroy');

    // Redirects
    Route::get('redirects', [AdminRedirectsController::class, 'index'])->name('admin.redirects.index');
    Route::post('redirects', [AdminRedirectsController::class, 'store'])->name('admin.redirects.store');
    Route::put('redirects/{redirect}', [AdminRedirectsController::class, 'update'])->name('admin.redirects.update');
    Route::delete('redirects/{redirect}', [AdminRedirectsController::class, 'destroy'])->name('admin.redirects.destroy');
    Route::patch('redirects/{redirect}/toggle', [AdminRedirectsController::class, 'toggle'])->name('admin.redirects.toggle');

    // Menus
    Route::resource('menus', AdminMenuController::class)->except(['show'])->names([
        'index' => 'admin.menus.index',
        'create' => 'admin.menus.create',
        'store' => 'admin.menus.store',
        'edit' => 'admin.menus.edit',
        'update' => 'admin.menus.update',
        'destroy' => 'admin.menus.destroy',
    ]);

    // Media library
    Route::get('media', [AdminMediaController::class, 'index'])->name('admin.media.index');
    Route::get('media/list', [AdminMediaController::class, 'list'])->name('admin.media.list');
    Route::get('media/folders', [AdminMediaController::class, 'folders'])->name('admin.media.folders');
    Route::post('media/upload', [AdminMediaController::class, 'upload'])->name('admin.media.upload');
    Route::patch('media/{media}', [AdminMediaController::class, 'update'])->name('admin.media.update');
    Route::delete('media/{media}', [AdminMediaController::class, 'destroy'])->name('admin.media.destroy');

    // Activity log
    Route::get('activity', [AdminActivityController::class, 'index'])->name('admin.activity.index');

    // Cache management
    Route::get('cache', [AdminCacheController::class, 'index'])->name('admin.cache.index');
    Route::post('cache/clear', [AdminCacheController::class, 'clear'])->name('admin.cache.clear');

    // Maintenance mode
    Route::get('maintenance', [AdminMaintenanceController::class, 'index'])->name('admin.maintenance.index');
    Route::post('maintenance/enable', [AdminMaintenanceController::class, 'enable'])->name('admin.maintenance.enable');
    Route::post('maintenance/disable', [AdminMaintenanceController::class, 'disable'])->name('admin.maintenance.disable');

    // Forms admin
    Route::get('forms', [AdminFormsController::class, 'index'])->name('admin.forms.index');
    Route::get('forms/create', [AdminFormsController::class, 'create'])->name('admin.forms.create');
    Route::post('forms', [AdminFormsController::class, 'store'])->name('admin.forms.store');
    Route::get('forms/{form}/edit', [AdminFormsController::class, 'edit'])->name('admin.forms.edit');
    Route::put('forms/{form}', [AdminFormsController::class, 'update'])->name('admin.forms.update');
    Route::delete('forms/{form}', [AdminFormsController::class, 'destroy'])->name('admin.forms.destroy');
    Route::get('forms/{form}/submissions', [AdminFormSubmissionsController::class, 'index'])->name('admin.forms.submissions.index');
    Route::delete('forms/{form}/submissions/{submission}', [AdminFormSubmissionsController::class, 'destroy'])->name('admin.forms.submissions.destroy');
    Route::get('forms/{form}/submissions/export', [AdminFormSubmissionsController::class, 'export'])->name('admin.forms.submissions.export');
});

Route::post('forms/{form:slug}/submit', [FormSubmissionController::class, 'submit'])->name('forms.submit');

Route::get('/', [PageController::class, 'show'])->name('home');

Route::get('{slug}', [PageController::class, 'show'])
    ->where('slug', '.*')
    ->name('page.show');
