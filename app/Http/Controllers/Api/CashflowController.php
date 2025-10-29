<?php

namespace App\Http\Controllers\Api;

use App\Models\Cashflow;
use Illuminate\Http\Request;
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
}
