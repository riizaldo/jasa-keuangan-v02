<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'installment_id',
        'paid_at',
        'amount',
        'method',
        'notes',
    ];

    public function installment()
    {
        return $this->belongsTo(LoanInstallment::class);
    }
}
