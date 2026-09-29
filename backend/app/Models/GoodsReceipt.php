<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;

class GoodsReceipt extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $table = 'goods_receipts';
    protected $guarded = ['id'];

    protected $casts = [
        'organization_id' => 'integer',
        'purchase_order_id' => 'integer',
        'received_by' => 'integer',
        'receipt_date' => 'date',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
