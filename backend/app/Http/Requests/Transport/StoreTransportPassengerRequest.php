<?php

namespace App\Http\Requests\Transport;

use App\Http\Requests\BaseFormRequest;
use App\Services\Operations\AthleteFitnessProvider;
use Illuminate\Validation\Validator;

class StoreTransportPassengerRequest extends BaseFormRequest
{
    public function authorize()
    {
        return $this->user()->hasAnyPermission(['transport.manage', 'event.view']); // Simplification per rules
    }

    public function rules()
    {
        return [
            'transport_trip_id' => 'required|integer|exists:transport_trips,id',
            'athlete_id' => 'nullable|integer|exists:athletes,id',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'coach_id' => 'nullable|integer|exists:coach_profiles,id',
            'passenger_name' => 'nullable|string|max:150',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $athleteId = $this->input('athlete_id');
            if (!$athleteId) {
                return;
            }

            $gateMode = config('logistics.medical.gate_mode', 'strict');
            if ($gateMode === 'none') {
                return;
            }

            $organizationId = $this->user()->current_organization_id; // Assume accessible via tenant logic
            // Fallback for safety
            if (!$organizationId && method_exists($this->user(), 'currentOrganization')) {
                $organizationId = $this->user()->currentOrganization()?->id;
            }

            if ($organizationId) {
                $provider = app(AthleteFitnessProvider::class);
                $status = $provider->getFitnessStatus($athleteId, $organizationId);

                if ($gateMode === 'strict' && in_array($status, ['unfit', 'unknown'])) {
                    $validator->errors()->add('athlete_id', "Athlete cannot be added to transport due to medical gate (Status: {$status}).");
                } elseif ($gateMode === 'lenient' && $status === 'unfit') {
                    $validator->errors()->add('athlete_id', "Athlete is medically unfit and cannot be added.");
                }
            }
        });
    }
}
