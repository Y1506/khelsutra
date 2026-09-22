<?php

namespace App\Http\Requests\Venues;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class VenueRequest extends BaseFormRequest
{
    /**
     * Get validation rules for venue request.
     *
     * @return array Validation rules
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'venue_type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'capacity' => 'nullable|integer|min:0',
            'opening_time' => 'nullable|date_format:H:i',
            'closing_time' => 'nullable|date_format:H:i|after:opening_time',
            'status' => ['nullable', Rule::in(['active', 'inactive', 'under_maintenance'])],
        ];
    }
}
