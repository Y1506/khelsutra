<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;

class EquipmentAssignment extends Model
{
    use BelongsToOrganization;

    protected $table = 'equipment_assignments';
    protected $guarded = ['id'];

    protected $casts = [
        'organization_id' => 'integer',
        'equipment_id' => 'integer',
        'athlete_id' => 'integer',
        'coach_id' => 'integer',
        'employee_id' => 'integer',
        'team_id' => 'integer',
        'venue_id' => 'integer',
        'issued_by' => 'integer',
        'received_by' => 'integer',
        'assigned_date' => 'date',
        'expected_return_date' => 'date',
        'returned_date' => 'date',
    ];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function athlete()
    {
        return $this->belongsTo(Athlete::class, 'athlete_id');
    }

    public function coach()
    {
        return $this->belongsTo(CoachProfile::class, 'coach_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
