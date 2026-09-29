<?php
namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $orgId = defined('CURRENT_ORGANIZATION_ID') ? CURRENT_ORGANIZATION_ID : 1;
        $userId = $request->user()->id ?? 1;
        
        $notifs = DB::table('notifications')
            ->where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json(['success' => true, 'data' => $notifs]);
    }
    
    public function markRead(Request $request, $id)
    {
        $orgId = defined('CURRENT_ORGANIZATION_ID') ? CURRENT_ORGANIZATION_ID : 1;
        $userId = $request->user()->id ?? 1;
        
        DB::table('notifications')
            ->where('id', $id)
            ->where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->update(['is_read' => 1, 'read_at' => now()]);
            
        return response()->json(['success' => true]);
    }
}
