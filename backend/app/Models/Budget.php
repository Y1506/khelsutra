<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;

class Budget extends Model
{
    use BelongsToOrganization;

    protected $table = 'budgets';
    protected $guarded = ['id'];

    protected $casts = [
        'organization_id' => 'integer',
        'total_budget' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(BudgetItem::class, 'budget_id');
    }
}
