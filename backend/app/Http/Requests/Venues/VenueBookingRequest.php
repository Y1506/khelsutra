<?php

namespace App\Http\Requests\Venues;

use App\Http\Requests\BaseFormRequest;
use App\Rules\SportCompatible;
use App\Services\Operations\SportContextService;
use Illuminate\Validation\Rule;

class VenueBookingRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $sportId = null;
        $contextService = new SportContextService();
        
        if (!empty($this->data['tournament_id'])) {
            $sportId = $contextService->resolveSportContext('tournament', $this->data['tournament_id']);
        } elseif (!empty($this->data['event_id'])) {
            $sportId = $contextService->resolveSportContext('event', $this->data['event_id']);
        } elseif (!empty($this->data['team_id'])) {
            $sportId = $contextService->resolveSportContext('team', $this->data['team_id']);
        }

        $rules = [
            'venue_id' => ['required', 'integer', 'min:1'],
            'facility_id' => ['nullable', 'integer', 'min:1'],
            'team_id' => ['nullable', 'integer', 'min:1'],
            'event_id' => ['nullable', 'integer', 'min:1'],
            'tournament_id' => ['nullable', 'integer', 'min:1'],
            'training_session_id' => ['nullable', 'integer', 'min:1'],
            'fixture_id' => ['nullable', 'integer', 'min:1'],
            'booked_by_user_id' => ['required', 'integer', 'min:1'],
            'purpose' => ['nullable', 'string'],
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'end_time' => ['required', 'date_format:H:i:s'],
        ];

        if ($sportId) {
            $rules['venue_id'][] = new SportCompatible($sportId);
            $rules['facility_id'][] = new SportCompatible($sportId);
        }

        return $rules;
    }
}
