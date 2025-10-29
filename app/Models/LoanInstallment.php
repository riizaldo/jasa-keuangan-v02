<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanInstallment extends Model
{
    use HasFactory;

    // protected $table = 'loan_installments';

    protected $fillable = [
        'loan_application_id',
        'installment_number',
        'due_date',
        'amount_due',
        'amount_paid',
        'status',
    ];

    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'installment_id');
    }
}
