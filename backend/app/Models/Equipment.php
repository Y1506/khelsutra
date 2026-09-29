<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $table = 'equipment';
    protected $guarded = ['id'];

    protected $casts = [
        'organization_id' => 'integer',
        'inventory_item_id' => 'integer',
        'purchase_cost' => 'decimal:2',
        'purchase_date' => 'date',
        'warranty_expiry_date' => 'date',
    ];

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function assignments()
    {
        return $this->hasMany(EquipmentAssignment::class, 'equipment_id');
    }
}
