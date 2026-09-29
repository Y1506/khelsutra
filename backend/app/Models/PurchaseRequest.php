<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;

class PurchaseRequest extends Model
{
    use BelongsToOrganization;

    protected $table = 'purchase_requests';
    protected $guarded = ['id'];

    protected $casts = [
        'organization_id' => 'integer',
        'requested_by' => 'integer',
        'department_id' => 'integer',
        'approved_by' => 'integer',
        'request_date' => 'date',
        'required_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class, 'purchase_request_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'purchase_request_id');
    }
}
