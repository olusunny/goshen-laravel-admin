<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonationBankAccount extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(DonationAccountCategory::class, 'donation_account_category_id');
    }
}
