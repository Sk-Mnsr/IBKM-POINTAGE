<?php

use App\Http\Controllers\CurrentShopController;
use App\Http\Controllers\CustomerReviewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeReportController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\KioskDisplayController;
use App\Http\Controllers\KioskPunchController;
use App\Http\Controllers\KioskReviewController;
use App\Http\Controllers\KioskScanController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveReviewController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PunchController;
use App\Http\Controllers\PunchCorrectionController;
use App\Http\Controllers\PunchHistoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ShopKioskController;
use App\Http\Controllers\StaffHistoryController;
use App\Http\Controllers\StaffScheduleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Home')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [SessionController::class, 'create'])->name('login');
    Route::post('/connexion', [SessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

Route::post('/deconnexion', [SessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/mot-de-passe', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/mot-de-passe', [PasswordController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('password.update');
});

Route::middleware(['auth', 'admin', 'password.changed'])->group(function () {
    Route::get('/tableau-de-bord', [DashboardController::class, 'index'])->name('dashboard');
    Route::put('/boutique-active', [CurrentShopController::class, 'update'])->name('current-shop.update');

    Route::resource('employes', EmployeeController::class)
        ->except(['show', 'destroy'])
        ->parameters(['employes' => 'employee'])
        ->names('employees');

    Route::resource('departements', DepartmentController::class)
        ->except(['show'])
        ->parameters(['departements' => 'department'])
        ->names('departments');

    Route::get('/pointages', [PunchHistoryController::class, 'index'])->name('punches.index');
    Route::get('/pointages/export', [PunchHistoryController::class, 'export'])->name('punches.export');
    Route::get('/pointages/modele-import', [PunchHistoryController::class, 'template'])->name('punches.import.template');
    Route::post('/pointages/import', [PunchHistoryController::class, 'import'])
        ->middleware('throttle:10,1')
        ->name('punches.import');

    Route::get('/rapports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/rapports/excel', [ReportController::class, 'excel'])->name('reports.excel');
    Route::get('/rapports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');

    Route::get('/rapports/employes/{employee}', [EmployeeReportController::class, 'show'])->name('reports.employee');
    Route::get('/rapports/employes/{employee}/excel', [EmployeeReportController::class, 'excel'])->name('reports.employee.excel');
    Route::get('/rapports/employes/{employee}/pdf', [EmployeeReportController::class, 'pdf'])->name('reports.employee.pdf');

    Route::post('/rapports/employes/{employee}/pointages', [PunchCorrectionController::class, 'store'])->name('punches.corrections.store');
    Route::put('/pointages/{punch}', [PunchCorrectionController::class, 'update'])->name('punches.corrections.update');
    Route::delete('/pointages/{punch}', [PunchCorrectionController::class, 'destroy'])->name('punches.corrections.destroy');

    Route::get('/conges', [LeaveReviewController::class, 'index'])->name('leaves.index');
    Route::patch('/conges/{leaveRequest}', [LeaveReviewController::class, 'update'])->name('leaves.update');

    Route::get('/avis-clients', [CustomerReviewController::class, 'index'])->name('reviews.index');

    Route::middleware('super_admin')->group(function () {
        Route::delete('/employes/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

        Route::resource('boutiques', ShopController::class)
            ->except(['show', 'destroy'])
            ->parameters(['boutiques' => 'shop'])
            ->names('shops');

        Route::resource('gerants', ManagerController::class)
            ->except(['show', 'destroy'])
            ->parameters(['gerants' => 'manager'])
            ->names('managers');

        Route::post('/boutiques/{shop}/bornes', [ShopKioskController::class, 'store'])->name('shops.kiosks.store');
        Route::put('/boutiques/{shop}/bornes/{kiosk}', [ShopKioskController::class, 'update'])
            ->scopeBindings()
            ->name('shops.kiosks.update');

        Route::get('/reglages', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/reglages', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/reglages/jours-feries', [HolidayController::class, 'store'])->name('holidays.store');
        Route::post('/reglages/jours-feries/fetes-fixes', [HolidayController::class, 'storeSuggestions'])->name('holidays.suggestions');
        Route::delete('/reglages/jours-feries/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
    });
});

Route::middleware(['auth', 'staff', 'password.changed'])->group(function () {
    Route::get('/mon-pointage', [PunchController::class, 'create'])->name('punch.create');
    Route::post('/mon-pointage', [PunchController::class, 'store'])->name('punch.store');
    Route::post('/mon-pointage/synchroniser', [PunchController::class, 'sync'])
        ->middleware('throttle:60,1')
        ->name('punch.sync');

    Route::get('/mon-historique', [StaffHistoryController::class, 'index'])->name('staff.history');
    Route::get('/mon-planning', [StaffScheduleController::class, 'index'])->name('staff.schedule');

    Route::get('/mes-conges', [LeaveRequestController::class, 'index'])->name('staff.leaves.index');
    Route::post('/mes-conges', [LeaveRequestController::class, 'store'])->name('staff.leaves.store');
    Route::delete('/mes-conges/{leaveRequest}', [LeaveRequestController::class, 'destroy'])->name('staff.leaves.destroy');
});

Route::get('/borne/{kiosk}', [KioskDisplayController::class, 'show'])->name('kiosk.show');
Route::get('/borne/{kiosk}/jeton', [KioskDisplayController::class, 'token'])->name('kiosk.token');
Route::get('/borne/{kiosk}/jetons', [KioskDisplayController::class, 'tokens'])->name('kiosk.tokens');
Route::get('/borne/{kiosk}/activite', [KioskDisplayController::class, 'activity'])->name('kiosk.activity');
Route::get('/borne/{kiosk}/scan', [KioskScanController::class, 'show'])->name('kiosk.scan');
Route::get('/borne/{kiosk}/avis', [KioskReviewController::class, 'show'])->name('kiosk.reviews.show');
Route::post('/borne/{kiosk}/avis', [KioskReviewController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('kiosk.reviews.store');
Route::post('/borne/{kiosk}/identification', [KioskPunchController::class, 'identify'])
    ->middleware('throttle:30,1')
    ->name('kiosk.identify');
Route::post('/borne/{kiosk}/pointage', [KioskPunchController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('kiosk.punch');

Route::middleware(['auth', 'admin', 'password.changed'])->group(function () {
    Route::post('/borne/{kiosk}/appairage', [KioskDisplayController::class, 'pair'])->name('kiosk.pair');
    Route::delete('/borne/{kiosk}/appairage', [KioskDisplayController::class, 'unpair'])->name('kiosk.unpair');
});
