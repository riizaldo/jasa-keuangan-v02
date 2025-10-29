<?php

namespace App\Http\Controllers\Api;

use App\Models\Cashflow;
use Illuminate\Http\Request;
use App\Models\LoanApplication;
use App\Http\Controllers\Controller;

class CashflowController extends Controller
{
    public function index(Request $request)
    {
        $query = Cashflow::orderBy('date', 'desc');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return response()->json($query->paginate(20));
    }

    public function summary(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $query = Cashflow::query();

        if ($from && $to) {
            $query->whereBetween('date', [$from, $to]);
        }

        $totalIncome = (clone $query)->where('type', 'income')->sum('amount');
        $totalExpense = (clone $query)->where('type', 'expense')->sum('amount');
        $balance = $totalIncome - $totalExpense;

        return response()->json([
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'balance' => $balance,
            'from' => $from,
            'to' => $to,
        ]);
    }
    /**
     * Catat pengeluaran.
     */
    public function storeExpense(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string',
            'date' => 'required|date',
        ]);

        $expense = Cashflow::create([
            'type' => 'expense',
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'date' => $validated['date'],
            'source_id' => null,   // tidak ada model sumber spesifik
            'source_type' => null,
        ]);

        return response()->json([
            'message' => 'Expense recorded successfully',
            'data' => $expense,
        ], 201);
    }

    public function profitLoss(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $query = Cashflow::query();

        if ($from && $to) {
            $query->whereBetween('date', [$from, $to]);
        }

        $totalIncome = (clone $query)->where('type', 'income')->sum('amount');
        $totalExpense = (clone $query)->where('type', 'expense')->sum('amount');
        $netProfit = $totalIncome - $totalExpense;

        return response()->json([
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_profit' => $netProfit,
            'from' => $from,
            'to' => $to,
        ]);
    }
    public function balanceSheet(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $query = Cashflow::query();
        if ($from && $to) {
            $query->whereBetween('date', [$from, $to]);
        }

        $totalCash = $query->where('type', 'income')->sum('amount') - $query->where('type', 'expense')->sum('amount');

        $totalLiabilities = LoanApplication::where('status', '!=', 'paid')->sum('amount');

        $equity = $totalCash - $totalLiabilities;

        return response()->json([
            'assets' => $totalCash,
            'liabilities' => $totalLiabilities,
            'equity' => $equity,
            'from' => $from,
            'to' => $to,
        ]);
    }
    public function statementOfChangesInEquity(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        // Validasi tanggal, gunakan format Y-m-d
        if (!$from || !$to) {
            return response()->json([
                'message' => 'Parameter "from" dan "to" harus diisi dan dalam format YYYY-MM-DD',
            ], 400);
        }

        // Pastikan format tanggal valid
        try {
            $fromDate = \Carbon\Carbon::parse($from)->startOfDay();
            $toDate = \Carbon\Carbon::parse($to)->endOfDay();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Format tanggal tidak valid.',
                'error' => $e->getMessage(),
            ], 400);
        }

        // Modal awal
        $initialEquity = Cashflow::where('type', 'equity')
            ->where('date', '<', $fromDate)
            ->sum('amount');

        // Tambahan/penarikan modal periode ini
        $equityChanges = Cashflow::where('type', 'equity')
            ->whereBetween('date', [$fromDate, $toDate])
            ->sum('amount');

        // Laba bersih periode ini
        $totalIncome = Cashflow::where('type', 'income')
            ->whereBetween('date', [$fromDate, $toDate])
            ->sum('amount');

        $totalExpense = Cashflow::where('type', 'expense')
            ->whereBetween('date', [$fromDate, $toDate])
            ->sum('amount');

        $netProfit = $totalIncome - $totalExpense;

        // Modal akhir
        $endingEquity = $initialEquity + $equityChanges + $netProfit;

        return response()->json([
            'initial_equity' => $initialEquity,
            'equity_changes' => $equityChanges,
            'net_profit' => $netProfit,
            'ending_equity' => $endingEquity,
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
        ]);
    }
}
