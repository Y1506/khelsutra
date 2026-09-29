<?php

namespace App\Services\Operations;

use App\Models\Event;
use Illuminate\Database\Capsule\Manager as DB;

class SportContextService
{
    /**
     * Resolves the sport_id from a given context type and ID.
     *
     * @param string $contextType 'tournament', 'team', or 'event'
     * @param int $contextId
     * @return int|null
     */
    public function resolveSportContext(string $contextType, int $contextId): ?int
    {
        switch ($contextType) {
            case 'tournament':
                $record = DB::table('tournaments')->where('id', $contextId)->first();
                return $record ? (int)$record->sport_id : null;
            case 'team':
                $record = DB::table('teams')->where('id', $contextId)->first();
                return $record ? (int)$record->sport_id : null;
            case 'event':
                $event = Event::find($contextId);
                if ($event && method_exists($event, 'sports') && $event->sports()->exists()) {
                    return $event->sports()->first()->id;
                } elseif ($event && isset($event->sport_id)) {
                    return (int)$event->sport_id;
                }
                return null;
        }

        return null;
    }
}
