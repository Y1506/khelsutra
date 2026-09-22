<?php

namespace App\Http\Requests\Athletes;

class StoreAthleteRequest
{
    public array $data;

    /**
     * StoreAthleteRequest constructor.
     *
     * @param array $data Athlete data to validate
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Validate athlete data.
     *
     * @return array Validation errors (empty if valid)
     */
    public function validate(): array
    {
        $errors = [];
        if (empty($this->data['first_name'])) {
            $errors['first_name'][] = 'The first name is required.';
        }
        if (empty($this->data['date_of_birth'])) {
            $errors['date_of_birth'][] = 'The date of birth is required.';
        }
        if (empty($this->data['gender'])) {
            $errors['gender'][] = 'The gender field is required.';
        }
        if (empty($this->data['primary_sport_id'])) {
            $errors['primary_sport_id'][] = 'The primary sport selection is required.';
        }
        return $errors;
    }
}
