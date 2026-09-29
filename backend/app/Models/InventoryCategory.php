<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryCategory extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $table = 'inventory_categories';
    protected $guarded = ['id'];

    protected $casts = [
        'organization_id' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(InventoryItem::class, 'category_id');
    }
}
