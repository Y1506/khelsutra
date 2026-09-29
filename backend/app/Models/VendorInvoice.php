<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;

class VendorInvoice extends Model
{
    use BelongsToOrganization;

    protected $table = 'vendor_invoices';
    protected $guarded = ['id'];

    protected $casts = [
        'organization_id' => 'integer',
        'vendor_id' => 'integer',
        'purchase_order_id' => 'integer',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'invoice_date' => 'date',
        'due_date' => 'date',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function payments()
    {
        return $this->hasMany(FinancePayment::class, 'vendor_invoice_id');
    }
}
