<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;

class PurchaseOrder extends Model
{
    use BelongsToOrganization;

    protected $table = 'purchase_orders';
    protected $guarded = ['id'];

    protected $casts = [
        'organization_id' => 'integer',
        'purchase_request_id' => 'integer',
        'vendor_id' => 'integer',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
    ];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class, 'purchase_order_id');
    }

    public function invoices()
    {
        return $this->hasMany(VendorInvoice::class, 'purchase_order_id');
    }
}
