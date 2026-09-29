<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facility extends Model
{
    use \App\Traits\HasSports;
    use BelongsToOrganization, SoftDeletes;

    protected $table = 'venue_facilities';
    protected $guarded = ['id'];

    public function sports()
    {
        return $this->belongsToMany(\App\Models\Sport::class, 'facility_sports', 'facility_id', 'sport_id')
            ->withPivot('organization_id')
            ->withTimestamps();
    }
}
