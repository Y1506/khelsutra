<?php

namespace App\Services\Operations;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\SchoolActivity;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class EventService
{
    /**
     * Create a new event with generated reference.
     *
     * @param int $orgId The organization ID
     * @param array $data Event data
     * @return Event The created event
     */
    public function createEvent(int $orgId, array $data): Event
    {
        return DB::transaction(function () use ($orgId, $data) {
            $data['organization_id'] = $orgId;
            $data['event_reference'] = ReferenceGenerator::generate('EV', 'events', 'event_reference', $orgId);
            $data['status'] = 'planned';
            
            return Event::create($data);
        });
    }

    /**
     * Add a participant to an event with duplicate check.
     *
     * @param int $orgId The organization ID
     * @param int $eventId The event ID
     * @param array $data Participant data including participant_type and participant_id
     * @return EventParticipant The created participant record
     */
    public function addParticipant(int $orgId, int $eventId, array $data): EventParticipant
    {
        return DB::transaction(function () use ($orgId, $eventId, $data) {
            $event = Event::where('organization_id', $orgId)->lockForUpdate()->findOrFail($eventId);

            // Ensure unique per event
            $typeColMap = [
                'athlete' => 'athlete_id',
                'employee' => 'employee_id',
                'coach' => 'coach_id',
                'team' => 'team_id'
            ];

            if (!isset($typeColMap[$data['participant_type']])) {
                throw new Exception("Invalid participant type.", 400);
            }

            $idCol = $typeColMap[$data['participant_type']];
            $participantId = $data['participant_id'];

            $exists = EventParticipant::where('organization_id', $orgId)
                ->where('event_id', $event->id)
                ->where('participant_type', $data['participant_type'])
                ->where($idCol, $participantId)
                ->exists();

            if ($exists) {
                throw new Exception("Participant already added to this event.", 409);
            }

            $partData = [
                'organization_id' => $orgId,
                'event_id' => $event->id,
                'participant_type' => $data['participant_type'],
                $idCol => $participantId
            ];

            return EventParticipant::create($partData);
        });
    }

    /**
     * Create a new school activity.
     *
     * @param int $orgId The organization ID
     * @param array $data Activity data
     * @return SchoolActivity The created activity
     */
    public function createSchoolActivity(int $orgId, array $data): SchoolActivity
    {
        return DB::transaction(function () use ($orgId, $data) {
            $data['organization_id'] = $orgId;
            return SchoolActivity::create($data);
        });
    }

    /**
     * Record an expense for an event.
     *
     * @param int $orgId The organization ID
     * @param int $eventId The event ID
     * @param float $amount The expense amount
     * @param string $description The expense description
     * @param int|null $vendorId The vendor ID (optional)
     * @return int The created expense ID
     */
    public function recordEventExpense(int $orgId, int $eventId, float $amount, string $description, ?int $vendorId = null): int
    {
        return DB::transaction(function () use ($orgId, $eventId, $amount, $description, $vendorId) {
            $event = Event::where('organization_id', $orgId)->findOrFail($eventId);
            
            $recorder = new \App\Services\Operations\ExpenseRecorder();
            return $recorder->recordExpense($orgId, $amount, $description, [
                'event_id' => $event->id,
                'vendor_id' => $vendorId
            ]);
        });
    }
}
