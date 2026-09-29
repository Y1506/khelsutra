<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\SoftDeletes;

class VenueBooking extends Model
{
    use BelongsToOrganization, SoftDeletes, Auditable;

    protected $table = 'venue_bookings';
    protected $guarded = ['id'];
}
