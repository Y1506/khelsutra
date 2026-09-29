<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;

class IncomeTransaction extends Model
{
    use BelongsToOrganization;

    protected $table = 'income_transactions';
    protected $guarded = ['id'];

    protected $casts = [
        'organization_id' => 'integer',
        'finance_category_id' => 'integer',
        'created_by' => 'integer',
        'amount' => 'decimal:2',
        'income_date' => 'date',
    ];

    public function financeCategory()
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
