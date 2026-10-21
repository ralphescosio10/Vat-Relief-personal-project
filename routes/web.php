<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ClientDocumentController;
use App\Http\Controllers\CounterpartyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/register', [RegistrationController::class, 'create'])->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:3,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
    Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
    Route::prefix('/companies/{company}')->name('companies.')->group(function () {
        Route::get('/counterparties', [CounterpartyController::class, 'index'])->name('counterparties');
        Route::get('/audit', [AuditController::class, 'index'])->name('audit');
        Route::post('/counterparties', [CounterpartyController::class, 'store'])->name('counterparties.store');
        Route::delete('/counterparties/{counterparty}', [CounterpartyController::class, 'destroy'])->name('counterparties.destroy');
        Route::post('/documents', [ClientDocumentController::class, 'store'])->name('documents.store');
        Route::get('/documents/{document}', [ClientDocumentController::class, 'download'])->name('documents.download');
        Route::delete('/documents/{document}', [ClientDocumentController::class, 'destroy'])->name('documents.destroy');
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions');
        Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::get('/transactions/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
        Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
        Route::get('/imports', [ImportController::class, 'index'])->name('imports');
        Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
        Route::post('/imports/{batch}/commit', [ImportController::class, 'commit'])->name('imports.commit');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports');
        Route::post('/reports', [ReportController::class, 'generate'])->name('reports.generate');
        Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        Route::post('/reports/{report}/submit', [ReportController::class, 'submit'])->name('reports.submit');
        Route::post('/reports/{report}/decision', [ReportController::class, 'decision'])->name('reports.decision');
        Route::post('/reports/{report}/compliance', [ReportController::class, 'compliance'])->name('reports.compliance');
        Route::post('/reports/{report}/reopen', [ReportController::class, 'reopen'])->name('reports.reopen');
        Route::post('/reports/{report}/close', [ReportController::class, 'close'])->name('reports.close');
        Route::get('/reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');
    });
});
