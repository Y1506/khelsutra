<?php $title = 'Unified Resource Calendar'; ?>
<div class="container-fluid py-4">
    <div class="ks-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-weight-bold mb-1">Unified Resource Calendar</h1>
            <p class="text-muted mb-0">View all venue bookings, fleet trips, and room allocations.</p>
        </div>
    </div>
    
    <div class="card shadow-sm ks-card border-0 mb-4">
        <div class="card-body">
            <div id="calendar-timeline" style="display: grid; gap: 10px; overflow-x: auto;">
                <div class="text-center p-5 text-muted">
                    Loading timeline...
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('/api/v1/calendar/resources')
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('calendar-timeline');
            if(data.success) {
                let html = '<h5>Bookings</h5>';
                data.data.bookings.forEach(b => {
                    html += `<div class="p-2 mb-2 bg-light border rounded">Booking: ${b.booking_reference} | ${b.booking_date}</div>`;
                });
                html += '<h5>Trips</h5>';
                data.data.trips.forEach(t => {
                    html += `<div class="p-2 mb-2 bg-light border rounded">Trip: ${t.trip_reference} | ${t.trip_date}</div>`;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="text-danger">Failed to load calendar data.</div>';
            }
        });
});
</script>
<?php 
$slot = ob_get_clean(); 
include __DIR__ . '/../../layouts/app.blade.php'; 
?>
