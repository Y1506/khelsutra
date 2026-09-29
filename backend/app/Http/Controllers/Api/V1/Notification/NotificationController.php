<?php

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Controller;
use App\Services\Notification\NotificationService;
use App\Helpers\ApiResponse;
use InvalidArgumentException;
use Exception;

/**
 * Dedicated REST API controller for Member 5 Notification Service:
 * Fetch notifications, count unread alerts, mark as read, mark all as read, delete notifications.
 */
class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(?NotificationService $notificationService = null)
    {
        $this->notificationService = $notificationService ?? new NotificationService();
    }

    /**
     * Get user notifications with pagination and unread filtering.
     */
    public function index(int $orgId, int $userId, array $requestData = []): array
    {
        $page = max(1, (int)($requestData['page'] ?? ($_GET['page'] ?? 1)));
        $limit = max(1, min(100, (int)($requestData['limit'] ?? ($_GET['limit'] ?? 20))));
        $unreadOnly = !empty($requestData['unread_only'] ?? ($_GET['unread_only'] ?? false)) && ($requestData['unread_only'] ?? $_GET['unread_only']) !== 'false';

        $res = $this->notificationService->getUserNotifications($orgId, $userId, $page, $limit, $unreadOnly);
        return ApiResponse::success($res, 'Notifications retrieved successfully', 200);
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(int $orgId, int $userId): array
    {
        $count = $this->notificationService->getUnreadCount($orgId, $userId);
        return ApiResponse::success(['unread_count' => $count], 'Unread count retrieved', 200);
    }

    /**
     * Create/send a notification.
     */
    public function store(int $orgId, array $requestData, int $userId): array
    {
        $targetUserId = (int)($requestData['user_id'] ?? $userId);
        $title = trim($requestData['title'] ?? '');
        $message = trim($requestData['message'] ?? '');
        $type = $requestData['notification_type'] ?? ($requestData['type'] ?? null);
        $refType = $requestData['reference_type'] ?? null;
        $refId = !empty($requestData['reference_id']) ? (int)$requestData['reference_id'] : null;
        $channel = $requestData['channel'] ?? 'in_app';

        if (empty($title)) {
            return ApiResponse::error('Notification title is required.', ['title' => ['Title is required.']], 422);
        }
        if (empty($message)) {
            return ApiResponse::error('Notification message is required.', ['message' => ['Message is required.']], 422);
        }

        try {
            $created = $this->notificationService->createNotification(
                $orgId,
                $targetUserId,
                $title,
                $message,
                $type,
                $refType,
                $refId,
                $channel
            );
            return ApiResponse::success($created, 'Notification created successfully', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to create notification: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(int $orgId, int $id, int $userId): array
    {
        $ok = $this->notificationService->markAsRead($orgId, $id, $userId);
        if (!$ok) {
            return ApiResponse::error('Notification not found or access denied.', null, 404);
        }
        $unreadCount = $this->notificationService->getUnreadCount($orgId, $userId);
        return ApiResponse::success(['unread_count' => $unreadCount], 'Notification marked as read', 200);
    }

    /**
     * Mark all notifications as read for current user.
     */
    public function markAllAsRead(int $orgId, int $userId): array
    {
        $updated = $this->notificationService->markAllAsRead($orgId, $userId);
        return ApiResponse::success([
            'updated_count' => $updated,
            'unread_count' => 0
        ], 'All notifications marked as read', 200);
    }

    /**
     * Delete a notification.
     */
    public function destroy(int $orgId, int $id, int $userId): array
    {
        $ok = $this->notificationService->deleteNotification($orgId, $id, $userId);
        if (!$ok) {
            return ApiResponse::error('Notification not found or access denied.', null, 404);
        }
        return ApiResponse::success(null, 'Notification deleted successfully', 200);
    }
}
