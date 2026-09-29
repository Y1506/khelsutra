<?php
namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait Auditable
{
    public static function bootAuditable()
    {
        static::updated(function ($model) {
            $model->logAudit('UPDATE');
        });

        static::deleted(function ($model) {
            $model->logAudit('DELETE');
        });
        
        static::created(function ($model) {
            $model->logAudit('CREATE');
        });
    }

    protected function logAudit($action)
    {
        $orgId = defined('CURRENT_ORGANIZATION_ID') ? CURRENT_ORGANIZATION_ID : ($this->organization_id ?? 1);
        $userId = \App\Helpers\AuthContext::getUserId() ?? 1;

        $old = $action === 'CREATE' ? [] : array_intersect_key($this->getOriginal(), $this->getDirty());
        $new = $action === 'DELETE' ? [] : $this->getDirty();
        
        if (empty($old) && empty($new) && $action === 'UPDATE') {
            return;
        }

        DB::table('audit_logs')->insert([
            'organization_id' => $orgId,
            'user_id' => $userId,
            'action' => $action,
            'module' => class_basename($this),
            'table_name' => $this->getTable(),
            'record_id' => $this->id,
            'old_values' => json_encode($old),
            'new_values' => json_encode($new),
            'description' => "{$action} on {$this->getTable()} #{$this->id}",
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'System',
            'created_at' => now(),
        ]);
    }
}
