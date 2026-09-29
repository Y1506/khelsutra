<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;

class FinanceCategory extends Model
{
    use BelongsToOrganization;

    protected $table = 'finance_categories';
    protected $guarded = ['id'];

    protected $casts = [
        'organization_id' => 'integer',
    ];

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'finance_category_id');
    }

    public function incomeTransactions()
    {
        return $this->hasMany(IncomeTransaction::class, 'finance_category_id');
    }

    public function budgetItems()
    {
        return $this->hasMany(BudgetItem::class, 'finance_category_id');
    }
}
