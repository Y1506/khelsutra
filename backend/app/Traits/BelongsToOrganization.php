<?php

namespace App\Traits;

trait BelongsToOrganization
{
    /**
     * Boot the BelongsToOrganization trait for an Eloquent model.
     * Automatically sets organization_id on creation and scopes queries.
     */
    public static function bootBelongsToOrganization(): void
    {
        // For standard Eloquent integration:
        if (method_exists(static::class, 'addGlobalScope')) {
            static::addGlobalScope('organization', function ($builder) {
                if (defined('CURRENT_ORGANIZATION_ID') && CURRENT_ORGANIZATION_ID !== null) {
                    $builder->where($builder->getModel()->getTable().'.organization_id', CURRENT_ORGANIZATION_ID);
                }
            });
        }
    }

    /**
     * Organization relationship definition.
     */
    public function organization()
    {
        return $this->belongsTo(\App\Models\Organization::class, 'organization_id');
    }

    /**
     * Scope query to a specific organization.
     */
    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where($this->getTable().'.organization_id', $organizationId);
    }
}
