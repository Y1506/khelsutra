<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\SoftDeletes;

class HousekeepingSchedule extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $table = 'housekeeping_schedules';
    protected $guarded = ['id'];
    
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_run_date' => 'date',
    ];

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    public function assignedEmployee()
    {
        // Assuming employee model exists, if not, wait for Member 1
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }
}
