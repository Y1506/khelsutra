<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $table = 'expenses';
    protected $guarded = ['id'];

    protected $casts = [
        'organization_id' => 'integer',
        'finance_category_id' => 'integer',
        'department_id' => 'integer',
        'vendor_id' => 'integer',
        'event_id' => 'integer',
        'tournament_id' => 'integer',
        'approved_by' => 'integer',
        'created_by' => 'integer',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'expense_date' => 'date',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function financeCategory()
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class, 'tournament_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payments()
    {
        return $this->hasMany(FinancePayment::class, 'expense_id');
    }
}
