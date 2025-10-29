<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cashflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'amount',
        'description',
        'date',
        'source_id',
        'source_type',


    ];
}
