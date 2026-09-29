<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class LogisticsNotification extends Notification
{
    use Queueable;

    public $title;
    public $message;
    public $type;
    public $referenceType;
    public $referenceId;

    public function __construct($title, $message, $type, $referenceType = null, $referenceId = null)
    {
        $this->title = $title;
        $this->message = $message;
        $this->type = $type;
        $this->referenceType = $referenceType;
        $this->referenceId = $referenceId;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'organization_id' => $notifiable->organization_id ?? (defined('CURRENT_ORGANIZATION_ID') ? CURRENT_ORGANIZATION_ID : null),
            'user_id' => $notifiable->id,
            'title' => $this->title,
            'message' => $this->message,
            'notification_type' => $this->type,
            'reference_type' => $this->referenceType,
            'reference_id' => $this->referenceId,
            'channel' => 'in_app'
        ];
    }
}
