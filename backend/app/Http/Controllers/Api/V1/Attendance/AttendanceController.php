<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Http\Controllers\Controller;
use App\Services\Attendance\AttendanceService;
use App\Helpers\ApiResponse;

/**
 * Manages attendance recording for training sessions and matches.
 */
class AttendanceController extends Controller
{
    protected AttendanceService $attendanceService;

    /**
     * Create a new AttendanceController instance.
     *
     * @param AttendanceService|null $attendanceService Attendance service instance
     */
    public function __construct(?AttendanceService $attendanceService = null)
    {
        $this->attendanceService = $attendanceService ?? new AttendanceService();
    }

    /**
     * Record attendance for a training session.
     *
     * @param int $orgId Organization ID
     * @param int $sessionId Training session ID
     * @param array $requestData Attendance data for a participant
     * @param int|null $performedBy User ID performing this action
     * @return array API response confirming recorded attendance or error
     */
    public function recordTraining(int $orgId, int $sessionId, array $requestData, ?int $performedBy = null): array
    {
        $res = $this->attendanceService->recordTrainingAttendance($orgId, $sessionId, $requestData, $performedBy);
        if (!$res) {
            return ApiResponse::error('Validation failed. Exactly one participant (athlete, coach, or employee) must be specified with valid status.', null, 422);
        }
        return ApiResponse::success($res, 'Training attendance recorded', 200);
    }

    /**
     * Record attendance for a match.
     *
     * @param int $orgId Organization ID
     * @param int $matchId Match ID
     * @param array $requestData Attendance data for a participant
     * @param int|null $performedBy User ID performing this action
     * @return array API response confirming recorded attendance or error
     */
    public function recordMatch(int $orgId, int $matchId, array $requestData, ?int $performedBy = null): array
    {
        $res = $this->attendanceService->recordMatchAttendance($orgId, $matchId, $requestData, $performedBy);
        if (!$res) {
            return ApiResponse::error('Validation failed. Exactly one participant (athlete, coach, or employee) must be specified with valid status.', null, 422);
        }
        return ApiResponse::success($res, 'Match attendance recorded', 200);
    }

    /**
     * Retrieve training attendance history with optional filtering by session.
     *
     * @param int $orgId Organization ID
     * @param array $requestData Query parameters including optional training_session_id and limit
     * @return array API response with attendance history
     */
    public function trainingHistory(int $orgId, array $requestData): array
    {
        $sessionId = !empty($requestData['training_session_id']) ? (int)$requestData['training_session_id'] : null;
        $limit = (int)($requestData['limit'] ?? 50);
        $history = $this->attendanceService->getTrainingAttendanceHistory($orgId, $sessionId, $limit);
        return ApiResponse::success($history, 'Training attendance history retrieved', 200);
    }
}
