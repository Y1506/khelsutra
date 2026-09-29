<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;

class StockTransaction extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $table = 'stock_transactions';
    protected $guarded = ['id'];

    protected $casts = [
        'organization_id' => 'integer',
        'inventory_item_id' => 'integer',
        'reference_id' => 'integer',
        'performed_by' => 'integer',
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function inventoryItem()
    {
        return $this->item();
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
