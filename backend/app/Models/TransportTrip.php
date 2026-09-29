<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use App\Traits\BelongsToOrganization;

class TransportTrip extends Model
{
    use BelongsToOrganization, Auditable;

    protected $table = 'transport_trips';
    protected $guarded = ['id'];
}
