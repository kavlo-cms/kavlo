<?php

use App\Http\Controllers\Admin\AccountController as AdminAccountController;
use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Admin\BackupsController as AdminBackupsController;
use App\Http\Controllers\Admin\BlocksController as AdminBlocksController;
use App\Http\Controllers\Admin\CacheController as AdminCacheController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DataHubController as AdminDataHubController;
use App\Http\Controllers\Admin\EmailTemplatesController as AdminEmailTemplatesController;
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
use App\Http\Controllers\Admin\ScriptsController as AdminScriptsController;
use App\Http\Controllers\Admin\SearchController as AdminSearchController;
use App\Http\Controllers\Admin\Settings\EmailController as AdminEmailSettingsController;
use App\Http\Controllers\Admin\Settings\GeneralController as AdminGeneralSettingsController;
use App\Http\Controllers\Admin\Settings\ThemesController as AdminThemesController;
use App\Http\Controllers\Admin\UsersController as AdminUsersController;
use App\Http\Controllers\FormSubmissionController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/health', HealthController::class)->name('health');

Route::get('/dashboard', function (Request $request) {
    if ($request->user()?->hasAnyRole(['super-admin', 'admin', 'editor'])) {
        return to_route('admin.dashboard');
    }

    return Inertia::render('Dashboard/Home');
})->middleware('auth')->name('dashboard');

Route::middleware('guest')->get('/admin/login', fn () => redirect()->route('login'))->name('admin.login');

Route::prefix('admin')->middleware(['auth', 'admin.role', 'admin.activity'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

    // Account (logged-in user profile & password)
    Route::get('account', [AdminAccountController::class, 'index'])->name('admin.account.index');
    Route::put('account/profile', [AdminAccountController::class, 'updateProfile'])->name('admin.account.profile');
    Route::put('account/password', [AdminAccountController::class, 'updatePassword'])->name('admin.account.password');
    Route::post('account/api-keys', [AdminAccountController::class, 'storeApiKey'])->middleware('password.confirm')->name('admin.account.api-keys.store');
    Route::post('account/api-keys/{apiKey}/rotate', [AdminAccountController::class, 'rotateApiKey'])->middleware('password.confirm')->name('admin.account.api-keys.rotate');
    Route::delete('account/api-keys/{apiKey}', [AdminAccountController::class, 'destroyApiKey'])->middleware('password.confirm')->name('admin.account.api-keys.destroy');

    // Custom page routes must be defined before the resource to avoid {page} wildcard conflicts
    Route::post('pages/quick-create', [AdminPageController::class, 'quickCreate'])->name('admin.pages.quick-create');
    Route::post('pages/reorder', [AdminPageController::class, 'reorder'])->name('admin.pages.reorder');
    Route::post('pages/bulk', [AdminPageController::class, 'bulkAction'])->name('admin.pages.bulk');
    Route::get('pages/trash', [AdminPageController::class, 'trash'])->name('admin.pages.trash');
    Route::patch('pages/{id}/restore', [AdminPageController::class, 'restore'])->name('admin.pages.restore');
    Route::delete('pages/{id}/force', [AdminPageController::class, 'forceDelete'])->name('admin.pages.force-delete');
    Route::post('pages/{page}/duplicate', [AdminPageController::class, 'duplicate'])->name('admin.pages.duplicate');
    Route::get('pages/{page}/revisions/{revision}/preview', [AdminPageController::class, 'previewRevision'])->name('admin.pages.revisions.preview');
    Route::post('pages/{page}/revisions/{revision}/restore', [AdminPageController::class, 'restoreRevision'])->name('admin.pages.revisions.restore');
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
        Route::get('/', [AdminGeneralSettingsController::class, 'index'])->middleware('can:view settings')->name('admin.settings.index');
        Route::put('/', [AdminGeneralSettingsController::class, 'update'])->middleware('can:manage settings')->name('admin.settings.update');

        Route::get('email', [AdminEmailSettingsController::class, 'index'])->middleware('can:view settings')->name('admin.settings.email.index');
        Route::put('email', [AdminEmailSettingsController::class, 'update'])->middleware(['can:manage settings', 'password.confirm'])->name('admin.settings.email.update');
        Route::post('email/test', [AdminEmailSettingsController::class, 'testSend'])->middleware(['can:manage settings', 'password.confirm'])->name('admin.settings.email.test');
    });

    Route::get('scripts', [AdminScriptsController::class, 'index'])->middleware('can:view scripts')->name('admin.scripts.index');
    Route::post('scripts', [AdminScriptsController::class, 'store'])->middleware('can:manage scripts')->name('admin.scripts.store');
    Route::put('scripts/{siteScript}', [AdminScriptsController::class, 'update'])->middleware('can:manage scripts')->name('admin.scripts.update');
    Route::delete('scripts/{siteScript}', [AdminScriptsController::class, 'destroy'])->middleware('can:manage scripts')->name('admin.scripts.destroy');

    // Themes
    Route::get('themes', [AdminThemesController::class, 'index'])->middleware('can:view themes')->name('admin.themes.index');
    Route::post('themes/{theme}/activate', [AdminThemesController::class, 'activate'])->middleware('can:manage themes')->name('admin.themes.activate');

    // Plugins
    Route::get('plugins', [AdminPluginsController::class, 'index'])->middleware('can:view plugins')->name('admin.plugins.index');
    Route::post('plugins/upload', [AdminPluginsController::class, 'upload'])->middleware('can:manage plugins')->name('admin.plugins.upload');
    Route::post('plugins/{plugin}/toggle', [AdminPluginsController::class, 'toggle'])->middleware('can:manage plugins')->name('admin.plugins.toggle');

    // Blocks
    Route::get('blocks', [AdminBlocksController::class, 'index'])->middleware('can:view pages')->name('admin.blocks.index');

    // Email templates
    Route::get('email-templates', [AdminEmailTemplatesController::class, 'index'])->middleware('can:view email templates')->name('admin.email-templates.index');
    Route::get('email-templates/create', [AdminEmailTemplatesController::class, 'create'])->middleware('can:view email templates')->name('admin.email-templates.create');
    Route::post('email-templates', [AdminEmailTemplatesController::class, 'store'])->middleware('can:manage email templates')->name('admin.email-templates.store');
    Route::get('email-templates/{emailTemplate}/edit', [AdminEmailTemplatesController::class, 'edit'])->middleware('can:view email templates')->name('admin.email-templates.edit');
    Route::put('email-templates/{emailTemplate}', [AdminEmailTemplatesController::class, 'update'])->middleware('can:manage email templates')->name('admin.email-templates.update');
    Route::delete('email-templates/{emailTemplate}', [AdminEmailTemplatesController::class, 'destroy'])->middleware('can:manage email templates')->name('admin.email-templates.destroy');

    // DataHub
    Route::get('data-hub', [AdminDataHubController::class, 'index'])
        ->middleware('can:view datahub')
        ->name('admin.datahub.index');

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
    Route::get('redirects', [AdminRedirectsController::class, 'index'])->middleware('can:view redirects')->name('admin.redirects.index');
    Route::post('redirects', [AdminRedirectsController::class, 'store'])->middleware('can:manage redirects')->name('admin.redirects.store');
    Route::put('redirects/{redirect}', [AdminRedirectsController::class, 'update'])->middleware('can:manage redirects')->name('admin.redirects.update');
    Route::delete('redirects/{redirect}', [AdminRedirectsController::class, 'destroy'])->middleware('can:manage redirects')->name('admin.redirects.destroy');
    Route::patch('redirects/{redirect}/toggle', [AdminRedirectsController::class, 'toggle'])->middleware('can:manage redirects')->name('admin.redirects.toggle');

    // Menus
    Route::resource('menus', AdminMenuController::class)->except(['show'])->middleware([
        'index' => 'can:view menus',
        'create' => 'can:manage menus',
        'store' => 'can:manage menus',
        'edit' => 'can:manage menus',
        'update' => 'can:manage menus',
        'destroy' => 'can:manage menus',
    ])->names([
        'index' => 'admin.menus.index',
        'create' => 'admin.menus.create',
        'store' => 'admin.menus.store',
        'edit' => 'admin.menus.edit',
        'update' => 'admin.menus.update',
        'destroy' => 'admin.menus.destroy',
    ]);

    // Media library
    Route::get('media', [AdminMediaController::class, 'index'])->middleware('can:view media')->name('admin.media.index');
    Route::get('media/list', [AdminMediaController::class, 'list'])->middleware('can:view media')->name('admin.media.list');
    Route::get('media/folders', [AdminMediaController::class, 'folders'])->middleware('can:view media')->name('admin.media.folders');
    Route::post('media/upload', [AdminMediaController::class, 'upload'])->middleware('can:upload media')->name('admin.media.upload');
    Route::patch('media/{media}', [AdminMediaController::class, 'update'])->middleware('can:upload media')->name('admin.media.update');
    Route::delete('media/{media}', [AdminMediaController::class, 'destroy'])->middleware('can:delete media')->name('admin.media.destroy');

    // Activity log
    Route::get('search', [AdminSearchController::class, 'index'])->name('admin.search.index');
    Route::get('analytics', [AdminAnalyticsController::class, 'index'])->middleware('can:view analytics')->name('admin.analytics.index');
    Route::get('activity', [AdminActivityController::class, 'index'])->middleware('can:view activity log')->name('admin.activity.index');

    // Cache management
    Route::get('backups', [AdminBackupsController::class, 'index'])->middleware('can:manage backups')->name('admin.backups.index');
    Route::get('backups/export', [AdminBackupsController::class, 'export'])->middleware(['can:manage backups', 'password.confirm'])->name('admin.backups.export');
    Route::post('backups/checkpoints', [AdminBackupsController::class, 'storeCheckpoint'])->middleware(['can:manage backups', 'password.confirm'])->name('admin.backups.checkpoints.store');
    Route::get('backups/checkpoints/download', [AdminBackupsController::class, 'downloadCheckpoint'])->middleware(['can:manage backups', 'password.confirm'])->name('admin.backups.checkpoints.download');
    Route::post('backups/inspect', [AdminBackupsController::class, 'inspect'])->middleware(['can:manage backups', 'password.confirm'])->name('admin.backups.inspect');
    Route::post('backups/restore', [AdminBackupsController::class, 'restore'])->middleware(['can:manage backups', 'password.confirm'])->name('admin.backups.restore');
    Route::get('cache', [AdminCacheController::class, 'index'])->middleware('can:manage cache')->name('admin.cache.index');
    Route::post('cache/clear', [AdminCacheController::class, 'clear'])->middleware(['can:manage cache', 'password.confirm'])->name('admin.cache.clear');

    // Maintenance mode
    Route::get('maintenance', [AdminMaintenanceController::class, 'index'])->middleware('can:manage maintenance')->name('admin.maintenance.index');
    Route::post('maintenance/enable', [AdminMaintenanceController::class, 'enable'])->middleware(['can:manage maintenance', 'password.confirm'])->name('admin.maintenance.enable');
    Route::post('maintenance/disable', [AdminMaintenanceController::class, 'disable'])->middleware(['can:manage maintenance', 'password.confirm'])->name('admin.maintenance.disable');

    // Forms admin
    Route::get('forms', [AdminFormsController::class, 'index'])->middleware('can:view forms')->name('admin.forms.index');
    Route::get('forms/create', [AdminFormsController::class, 'create'])->middleware('can:manage forms')->name('admin.forms.create');
    Route::post('forms', [AdminFormsController::class, 'store'])->middleware('can:manage forms')->name('admin.forms.store');
    Route::get('forms/{form}/edit', [AdminFormsController::class, 'edit'])->middleware('can:view forms')->name('admin.forms.edit');
    Route::put('forms/{form}', [AdminFormsController::class, 'update'])->middleware('can:manage forms')->name('admin.forms.update');
    Route::delete('forms/{form}', [AdminFormsController::class, 'destroy'])->middleware('can:manage forms')->name('admin.forms.destroy');
    Route::get('forms/{form}/submissions', [AdminFormSubmissionsController::class, 'index'])->middleware('can:view forms')->name('admin.forms.submissions.index');
    Route::delete('forms/{form}/submissions/{submission}', [AdminFormSubmissionsController::class, 'destroy'])->middleware('can:manage forms')->name('admin.forms.submissions.destroy');
    Route::get('forms/{form}/submissions/export', [AdminFormSubmissionsController::class, 'export'])->middleware('can:view forms')->name('admin.forms.submissions.export');
});

Route::post('forms/{form:slug}/submit', [FormSubmissionController::class, 'submit'])->name('forms.submit');
Route::get('/search', [SearchController::class, 'index'])->name('search');

Route::get('/', [PageController::class, 'show'])->name('home');

Route::get('{slug}', [PageController::class, 'show'])
    ->where('slug', '.*')
    ->name('page.show');
