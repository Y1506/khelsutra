<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venue extends Model
{
    use \App\Traits\HasSports;
    use BelongsToOrganization, SoftDeletes;

    protected $table = 'venues';
    protected $guarded = ['id'];

    public function sports()
    {
        return $this->belongsToMany(\App\Models\Sport::class, 'venue_sports', 'venue_id', 'sport_id')
            ->withPivot('organization_id')
            ->withTimestamps();
    }
}
