<?php

namespace App\Events\Operations;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\VenueBooking;

class BookingCompleted
{
    use Dispatchable, SerializesModels;

    public VenueBooking $booking;

    public function __construct(VenueBooking $booking)
    {
        $this->booking = $booking;
    }
}
