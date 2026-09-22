<?php

namespace App\Http\Requests\Venues;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class VenueBookingRequest extends BaseFormRequest
{
    /**
     * Get validation rules for venue booking request.
     *
     * @return array Validation rules
     */
    public function rules(): array
    {
        return [
            'venue_id' => 'required|integer|min:1',
            'facility_id' => 'nullable|integer|min:1',
            'team_id' => 'nullable|integer|min:1',
            'event_id' => 'nullable|integer|min:1',
            'tournament_id' => 'nullable|integer|min:1',
            'training_session_id' => 'nullable|integer|min:1',
            'fixture_id' => 'nullable|integer|min:1',
            'booked_by_user_id' => 'required|integer|min:1',
            'purpose' => 'nullable|string',
            'booking_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s',
        ];
    }
}
