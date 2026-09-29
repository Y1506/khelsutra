<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;

class CoachProfile
{
    use BelongsToOrganization;

    protected string $table = 'coach_profiles';

    public ?int $id = null;
    public int $organization_id;
    public int $employee_id;
    public string $coach_code;
    public ?string $specialization = null;
    public ?string $qualification = null;
    public ?string $certifications = null;
    public ?float $experience_years = null;
    public ?string $joining_date = null;
    public ?string $license_number = null;
    public ?string $license_expiry_date = null;
    public string $status = 'active';
    public ?string $notes = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $deleted_at = null;

    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $val) {
            if (property_exists($this, $key)) {
                $this->{$key} = $val;
            }
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'employee_id' => $this->employee_id,
            'coach_code' => $this->coach_code,
            'specialization' => $this->specialization,
            'qualification' => $this->qualification,
            'certifications' => $this->certifications,
            'experience_years' => $this->experience_years,
            'joining_date' => $this->joining_date,
            'license_number' => $this->license_number,
            'license_expiry_date' => $this->license_expiry_date,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }



    public function sports()
    {
        return $this->belongsToMany(\App\Models\Sport::class, 'coach_sports', 'coach_id', 'sport_id')
            ->withPivot('organization_id')
            ->withTimestamps();
    }
}
