<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $table = 'inventory_items';
    protected $guarded = ['id'];

    protected $casts = [
        'organization_id' => 'integer',
        'category_id' => 'integer',
        'quantity' => 'decimal:2',
        'minimum_stock_level' => 'decimal:2',
        'reorder_level' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function stockTransactions()
    {
        return $this->hasMany(StockTransaction::class, 'inventory_item_id');
    }

    public function equipmentUnits()
    {
        return $this->hasMany(Equipment::class, 'inventory_item_id');
    }
}
