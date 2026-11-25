<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'package_name',
        'quota_amount',
        'price',
        'proof_image',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}