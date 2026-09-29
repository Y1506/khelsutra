<?php
namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Operations\LogisticsContextResolver;

class OperationsIntegrationController extends Controller
{
    public function audit(Request $request, $resource, $id)
    {
        $orgId = defined('CURRENT_ORGANIZATION_ID') ? CURRENT_ORGANIZATION_ID : ($request->user()->organization_id ?? 1);
        
        $table = match($resource) {
            'bookings' => 'venue_bookings',
            'trips' => 'transport_trips',
            'allocations' => 'accommodation_allocations',
            default => null
        };
        
        if (!$table) return response()->json(['success' => false, 'message' => 'Invalid resource'], 404);

        $logs = DB::table('audit_logs')
            ->where('organization_id', $orgId)
            ->where('table_name', $table)
            ->where('record_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json(['success' => true, 'data' => $logs]);
    }

    public function context(Request $request, $type, $id)
    {
        $resolver = new LogisticsContextResolver();
        return response()->json(['success' => true, 'data' => $resolver->resolve($type, $id)]);
    }

    public function calendar(Request $request)
    {
        $orgId = defined('CURRENT_ORGANIZATION_ID') ? CURRENT_ORGANIZATION_ID : ($request->user()->organization_id ?? 1);
        
        $bookings = DB::table('venue_bookings')->where('organization_id', $orgId)->whereNull('deleted_at')->get();
        $trips = DB::table('transport_trips')->where('organization_id', $orgId)->whereNull('deleted_at')->get();
        $allocations = DB::table('accommodation_allocations')->where('organization_id', $orgId)->whereNull('deleted_at')->get();
        
        return response()->json([
            'success' => true, 
            'data' => [
                'bookings' => $bookings,
                'trips' => $trips,
                'allocations' => $allocations
            ]
        ]);
    }
}
