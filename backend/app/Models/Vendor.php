<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $table = 'vendors';
    protected $guarded = ['id'];

    protected $casts = [
        'organization_id' => 'integer',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function invoices()
    {
        return $this->hasMany(VendorInvoice::class, 'vendor_id');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'vendor_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'vendor_id');
    }
}
