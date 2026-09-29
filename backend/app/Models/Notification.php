<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;

class Notification extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $table = 'notifications';
    protected $guarded = ['id'];

    protected $casts = [
        'organization_id' => 'integer',
        'user_id' => 'integer',
        'reference_id' => 'integer',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function markAsRead(): bool
    {
        $this->is_read = true;
        $this->read_at = now();
        return $this->save();
    }
}
