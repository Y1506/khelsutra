<?php

namespace App\Http\Requests\Venues;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class FacilityRequest extends BaseFormRequest
{
    /**
     * Get validation rules for facility request.
     *
     * @return array Validation rules
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'facility_type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'capacity' => 'nullable|integer|min:0',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'status' => ['nullable', Rule::in(['active', 'inactive', 'under_maintenance'])],
        ];
    }
}
