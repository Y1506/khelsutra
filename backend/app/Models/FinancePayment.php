<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;

class FinancePayment extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $table = 'finance_payments';
    protected $guarded = ['id'];

    protected $casts = [
        'organization_id' => 'integer',
        'expense_id' => 'integer',
        'vendor_invoice_id' => 'integer',
        'payroll_id' => 'integer',
        'created_by' => 'integer',
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function expense()
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }

    public function vendorInvoice()
    {
        return $this->belongsTo(VendorInvoice::class, 'vendor_invoice_id');
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
