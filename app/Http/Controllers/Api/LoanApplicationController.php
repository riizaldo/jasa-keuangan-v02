<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\LoanApplication;
use App\Models\LoanInstallment;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;



class LoanApplicationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100000',
            'term' => 'required|integer|min:1',
            'interest_rate' => 'required|numeric|min:0',
        ]);

        $loan = LoanApplication::create([
            'user_id' => Auth::id(),
            'amount' => $validated['amount'],
            'term' => $validated['term'],
            'interest_rate' => $validated['interest_rate'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Loan application submitted successfully.',
            'data' => $loan,
        ]);
    }
    public function index()
    {
        $user = Auth::user();

        $query = LoanApplication::with('user');

        if (!$user->is_admin) {
            $query->where('user_id', $user->id);
        }

        return response()->json($query->get());
    }

    // ✅ Detail pinjaman
    public function show($id)
    {
        $loan = LoanApplication::with('installments')->findOrFail($id);

        if (Auth::id() !== $loan->user_id && !Auth::user()->is_admin) {
            abort(403, 'Unauthorized');
        }

        return response()->json($loan);
    }

    // ✅ Approve pengajuan + generate cicilan
    public function approve($id)
    {
        $loan = LoanApplication::findOrFail($id);

        if (!Auth::user()->is_admin) {
            abort(403, 'Unauthorized');
        }

        if ($loan->status !== 'pending') {
            return response()->json(['message' => 'Loan already processed.'], 400);
        }

        DB::transaction(function () use ($loan) {
            $loan->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            $amountPerMonth = $loan->amount * (1 + ($loan->interest_rate / 100)) / $loan->term;
            $dueDate = Carbon::now()->addMonth();

            for ($i = 1; $i <= $loan->term; $i++) {
                LoanInstallment::create([
                    'loan_application_id' => $loan->id,
                    'installment_number' => $i,
                    'due_date' => $dueDate->copy(),
                    'amount_due' => $amountPerMonth,
                    'amount_paid' => 0,
                    'status' => 'unpaid',
                ]);
                $dueDate->addMonth();
            }
        });

        return response()->json(['message' => 'Loan approved and installments created.']);
    }

    // ✅ Reject pengajuan
    public function reject($id)
    {
        $loan = LoanApplication::findOrFail($id);

        if (!Auth::user()->is_admin) {
            abort(403, 'Unauthorized');
        }

        $loan->update([
            'status' => 'rejected',
            'rejected_at' => now(),
        ]);

        return response()->json(['message' => 'Loan application rejected.']);
    }
}
