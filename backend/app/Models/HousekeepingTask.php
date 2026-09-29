<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\SoftDeletes;

class HousekeepingTask extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $table = 'housekeeping_tasks';
    protected $guarded = ['id'];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function schedule()
    {
        return $this->belongsTo(HousekeepingSchedule::class, 'schedule_id');
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }
}
