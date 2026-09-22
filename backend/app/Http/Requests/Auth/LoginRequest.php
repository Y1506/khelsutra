<?php

namespace App\Http\Requests\Auth;

class LoginRequest
{
    public string $email;
    public string $password;
    public ?string $organization_code = null;

    /**
     * LoginRequest constructor.
     *
     * @param array $data Login credentials
     */
    public function __construct(array $data)
    {
        $this->email = $data['email'] ?? '';
        $this->password = $data['password'] ?? '';
        $this->organization_code = $data['organization_code'] ?? null;
    }

    /**
     * Validate login credentials.
     *
     * @return array Validation errors (empty if valid)
     */
    public function validate(): array
    {
        $errors = [];
        if (empty($this->email)) {
            $errors['email'][] = 'The email field is required.';
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'The email format is invalid.';
        }
        if (empty($this->password)) {
            $errors['password'][] = 'The password field is required.';
        }
        return $errors;
    }
}
