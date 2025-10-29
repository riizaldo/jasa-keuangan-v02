<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Api\CashflowController;
use App\Http\Controllers\Api\LoanPaymentController;
use App\Http\Controllers\Api\LoanApplicationController;

Route::post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    $token = $user->createToken('api_token')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => $user,
    ]);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();

    return response()->json(['message' => 'Logged out']);
});



Route::middleware('auth:sanctum')->group(function () {
    Route::get('/loan-applications', [LoanApplicationController::class, 'index']);
    Route::post('/loan-applications', [LoanApplicationController::class, 'store']);
    Route::get('/loan-applications/{id}', [LoanApplicationController::class, 'show']);
    Route::post('/loan-applications/{id}/approve', [LoanApplicationController::class, 'approve']);
    Route::post('/loan-applications/{id}/reject', [LoanApplicationController::class, 'reject']);

    // Pembayaran cicilan
    Route::get('/loan-payments', [LoanPaymentController::class, 'index']);
    Route::post('/loan-payments', [LoanPaymentController::class, 'store']);
    Route::get('/loan-payments/{id}', [LoanPaymentController::class, 'show']);


    Route::post('/payments', [LoanPaymentController::class, 'store']);
    Route::delete('/payments/{id}', [LoanPaymentController::class, 'destroy']);




    Route::get('/cashflows', [CashflowController::class, 'index']);
    Route::get('/cashflows/summary', [CashflowController::class, 'summary']);
    Route::post('/cashflow/expense', [CashflowController::class, 'storeExpense']);
    Route::get('/reports/profit-loss', [CashflowController::class, 'profitLoss']);
    Route::get('/reports/balance-sheet', [CashflowController::class, 'balanceSheet']);
    Route::get('/reports/changes-in-equity', [CashflowController::class, 'statementOfChangesInEquity']);
});
