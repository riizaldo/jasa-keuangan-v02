<?php

namespace App\Http\Controllers\Api;


use App\Models\Payment;
use App\Models\Cashflow;
use Illuminate\Http\Request;
use App\Models\LoanInstallment;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class LoanPaymentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'installment_id' => 'required',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|string|in:manual,va',
            'notes' => 'nullable|string',
        ]);

        $user = Auth::user();

        return DB::transaction(function () use ($validated, $user) {
            $installment = LoanInstallment::with('loanApplication')->findOrFail($validated['installment_id']);
            // Cegah duplikasi pembayaran
            if ($installment->status === 'paid') {
                return response()->json([
                    'message' => 'Cicilan ini sudah lunas.'
                ], 400);
            }

            // Cegah nominal pembayaran yang lebih kecil dari jumlah tagihan
            if ($validated['amount'] < $installment->amount_due) {
                return response()->json([
                    'message' => 'Nominal pembayaran tidak boleh kurang dari jumlah cicilan yang ditetapkan.'
                ], 400);
            }
            // Catat pembayaran
            $payment = Payment::create([
                'installment_id' => $installment->id,
                'paid_at' => now(),
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'notes' => $validated['notes'] ?? null,
            ]);
            \App\Models\Cashflow::create([
                'date' => now(),
                'type' => 'income',
                'source_type' => Payment::class,
                'source_id' => $payment->id,
                'amount' => $payment->amount,
                'description' => 'Pembayaran cicilan pinjaman #' . $installment->loanApplication->id,
            ]);
            // Update status cicilan

            $installment->status = 'paid';
            $installment->save();

            // Ambil relasi loan
            $loan = $installment->loanApplication;

            // Jika semua installment sudah 'paid', tandai loan sebagai 'paid'
            $unpaidCount = $loan->installments()->where('status', '!=', 'paid')->count();

            if ($unpaidCount === 0) {
                $loan->update(['status' => 'paid']);
            }

            return response()->json([
                'message' => 'Payment recorded successfully',
                'data' => $payment,
            ], 201);
        });
    }

    // ✅ Lihat daftar pembayaran user
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Payment::with(['installment.loanApplication'])
            ->when(!$user->is_admin, function ($q) use ($user) {
                $q->whereHas('installment.loanApplication', function ($sub) use ($user) {
                    $sub->where('user_id', $user->id);
                });
            })
            ->when($request->filled('paid_from'), function ($q) use ($request) {
                $q->whereDate('paid_at', '>=', $request->paid_from);
            })
            ->when($request->filled('paid_to'), function ($q) use ($request) {
                $q->whereDate('paid_at', '<=', $request->paid_to);
            })
            ->when($request->filled('method'), function ($q) use ($request) {
                $q->where('method', $request->method);
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                if ($request->status === 'paid') {
                    $q->whereNotNull('paid_at');
                } elseif ($request->status === 'unpaid') {
                    $q->whereNull('paid_at');
                }
            })
            ->latest();

        return response()->json($query->paginate(10));
    }

    // ✅ Detail pembayaran
    public function show($id)
    {
        $payment = Payment::with(['installment.loanApplication'])->findOrFail($id);

        if ($payment->loanApplication->user_id !== Auth::id() && !Auth::user()->is_admin) {
            abort(403, 'Unauthorized');
        }

        return response()->json($payment);
    }
    public function destroy($id)
    {
        $user = Auth::user();

        // Hanya admin boleh hapus pembayaran
        if (!$user->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return DB::transaction(function () use ($id) {
            $payment = Payment::with('installment.loanApplication')->findOrFail($id);

            $installment = $payment->installment;
            $loan = $installment->loanApplication;

            // Hapus pembayaran
            $payment->delete();

            // Ubah status cicilan jadi belum dibayar
            $installment->update([
                'status' => false,
                'paid_at' => null,
            ]);

            // Jika sebelumnya pinjaman sudah dinyatakan lunas, ubah kembali jadi aktif
            if ($loan->status === 'paid') {
                $loan->update(['status' => 'active']);
            }
            // Catat pengeluaran akibat refund
            Cashflow::create([
                'date' => now(),
                'type' => 'expense',
                'source_type' => Payment::class,
                'source_id' => $payment->id,
                'amount' => $payment->amount,
                'description' => 'Pembatalan pembayaran cicilan #' . $installment->id,
            ]);
            return response()->json([
                'message' => 'Payment has been cancelled successfully',
            ]);
        });
    }
}
