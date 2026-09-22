<?php

namespace App\Http\Requests\Athletes;

class StoreAthleteRequest
{
    public array $data;

    /**
     * Construct a new athlete registration request.
     *
     * @param array $data The athlete data to validate
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Validate the athlete registration data.
     *
     * @return array An array of validation errors, empty if validation passes
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
