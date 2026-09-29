<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasSports
{
    /**
     * Scope a query to only include records that are associated with a specific sport.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $sportId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForSport(Builder $query, $sportId)
    {
        // If the model has a direct sport_id column
        if (in_array('sport_id', $this->getFillable()) || \Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'sport_id')) {
            return $query->where($this->getTable() . '.sport_id', $sportId);
        }

        // Otherwise assume a BelongsToMany 'sports' relation
        return $query->whereHas('sports', function ($q) use ($sportId) {
            $q->where('sports.id', $sportId);
        });
    }
}
