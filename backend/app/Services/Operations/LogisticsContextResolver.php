<?php
namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;
use App\Models\Tournament;
use App\Models\Event;
use App\Models\Team;

class LogisticsContextResolver
{
    public function resolve($type, $id)
    {
        $orgId = defined('CURRENT_ORGANIZATION_ID') ? CURRENT_ORGANIZATION_ID : 1;
        
        $context = [
            'type' => $type,
            'id' => $id,
            'sport_id' => null,
            'start_date' => null,
            'end_date' => null,
            'participants' => [],
            'venue_id' => null
        ];

        switch($type) {
            case 'tournament':
                $t = Tournament::where('id', $id)->first();
                if ($t) {
                    $context['sport_id'] = $t->sport_id;
                    $context['start_date'] = $t->start_date;
                    $context['end_date'] = $t->end_date;
                }
                break;
            case 'event':
                $e = Event::where('id', $id)->first();
                if ($e) {
                    $context['sport_id'] = $e->sport_id;
                    $context['start_date'] = $e->start_date;
                    $context['end_date'] = $e->end_date;
                    $context['venue_id'] = $e->venue_id;
                }
                break;
        }

        return $context;
    }
}
