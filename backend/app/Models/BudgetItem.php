<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetItem extends Model
{
    public $timestamps = false;

    protected $table = 'budget_items';
    protected $guarded = ['id'];

    protected $casts = [
        'budget_id' => 'integer',
        'finance_category_id' => 'integer',
        'department_id' => 'integer',
        'allocated_amount' => 'decimal:2',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function financeCategory()
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
