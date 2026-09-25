<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;

class Athlete
{
    use BelongsToOrganization;

    protected string $table = 'athletes';

    public ?int $id = null;
    public int $organization_id;
    public ?int $user_id = null;
    public string $athlete_code;
    public string $first_name;
    public ?string $last_name = null;
    public string $date_of_birth;
    public string $gender;
    public ?string $blood_group = null;
    public int $primary_sport_id;
    public ?string $phone = null;
    public ?string $email = null;
    public string $status = 'active';

    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $val) {
            if (property_exists($this, $key)) {
                $this->{$key} = $val;
            }
        }
    }

    public function getFullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'user_id' => $this->user_id,
            'athlete_code' => $this->athlete_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->getFullName(),
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'blood_group' => $this->blood_group,
            'primary_sport_id' => $this->primary_sport_id,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
        ];
    }



    public function sports()
    {
        return $this->belongsToMany(\App\Models\Sport::class, 'athlete_sports', 'athlete_id', 'sport_id')
            ->withPivot('organization_id')
            ->withTimestamps();
    }
}
