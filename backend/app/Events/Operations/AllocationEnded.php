<?php

namespace App\Events\Operations;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\AccommodationAllocation;

class AllocationEnded
{
    use Dispatchable, SerializesModels;

    public AccommodationAllocation $allocation;

    public function __construct(AccommodationAllocation $allocation)
    {
        $this->allocation = $allocation;
    }
}
