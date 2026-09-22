<?php

namespace App\Http\Requests;

use Illuminate\Validation\Factory;
use Illuminate\Translation\Translator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Validation\DatabasePresenceVerifier;

/**
 * Base class for form request validation using Laravel's validator.
 */
abstract class BaseFormRequest
{
    public array $data;

    /**
     * Create a new form request instance and remove organization_id if present.
     *
     * @param array $data Request data to validate
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        if (isset($this->data['organization_id'])) {
            unset($this->data['organization_id']);
        }
    }

    /**
     * Define validation rules for this request.
     *
     * @return array Validation rules array
     */
    abstract public function rules(): array;

    /**
     * Validate the request data against the defined rules.
     *
     * @return array Array of validation errors, or empty array if validation passes
     */
    public function validate(): array
    {
        $loader = new ArrayLoader();
        $translator = new Translator($loader, 'en');
        $factory = new Factory($translator);

        $presenceVerifier = new DatabasePresenceVerifier(DB::getDatabaseManager());
        $factory->setPresenceVerifier($presenceVerifier);

        $validator = $factory->make($this->data, $this->rules());

        if ($validator->fails()) {
            return $validator->errors()->toArray();
        }

        return [];
    }
}
