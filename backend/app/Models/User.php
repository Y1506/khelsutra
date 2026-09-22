<?php

namespace App\Models;

/**
 * Represents a user entity with authentication and profile information.
 */
class User
{
    protected string $table = 'users';

    public ?int $id = null;
    public string $uuid;
    public ?string $username = null;
    public string $email;
    public string $password;
    public string $first_name;
    public ?string $last_name = null;
    public ?string $phone = null;
    public ?string $profile_photo_path = null;
    public string $status = 'active';

    /**
     * Create a new User model instance.
     *
     * @param array $attributes User attributes to initialize
     */
    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $val) {
            if (property_exists($this, $key)) {
                $this->{$key} = $val;
            }
        }
    }

    /**
     * Get the user's full name by combining first and last names.
     *
     * @return string The user's full name
     */
    public function getFullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Convert the user model to an array representation.
     *
     * @return array User data as an associative array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'username' => $this->username,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->getFullName(),
            'phone' => $this->phone,
            'profile_photo_path' => $this->profile_photo_path,
            'status' => $this->status,
        ];
    }
}
