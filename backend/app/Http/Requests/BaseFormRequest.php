<?php

namespace App\Http\Requests;

use Illuminate\Validation\Factory;
use Illuminate\Translation\Translator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Validation\DatabasePresenceVerifier;

abstract class BaseFormRequest
{
    public array $data;

    /**
     * BaseFormRequest constructor.
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
     * Get validation rules.
     *
     * @return array Validation rules
     */
    abstract public function rules(): array;

    /**
     * Validate request data against rules.
     *
     * @return array Validation errors (empty if valid)
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
