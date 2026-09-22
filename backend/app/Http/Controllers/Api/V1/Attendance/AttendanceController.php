<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Http\Controllers\Controller;
use App\Services\Attendance\AttendanceService;
use App\Helpers\ApiResponse;

class AttendanceController extends Controller
{
    protected AttendanceService $attendanceService;

    public function __construct(?AttendanceService $attendanceService = null)
    {
        $this->attendanceService = $attendanceService ?? new AttendanceService();
    }

    /**
     * Record training session attendance.
     *
     * @param int $orgId Organization ID
     * @param int $sessionId Training session ID
     * @param array $requestData Attendance data (participant and status)
     * @param int|null $performedBy User ID who performed the action
     * @return array API response with attendance record or error
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
     * Record match attendance.
     *
     * @param int $orgId Organization ID
     * @param int $matchId Match ID
     * @param array $requestData Attendance data (participant and status)
     * @param int|null $performedBy User ID who performed the action
     * @return array API response with attendance record or error
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
     * Get training attendance history.
     *
     * @param int $orgId Organization ID
     * @param array $requestData Filter parameters (training_session_id, limit)
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
