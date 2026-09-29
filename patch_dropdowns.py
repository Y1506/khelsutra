import os
import re

files_to_patch = [
    'backend/resources/views/operations/maintenance/index.blade.php',
    'backend/resources/views/operations/bookings/index.blade.php',
    'backend/resources/views/operations/events/index.blade.php',
    'backend/resources/views/operations/transport/index.blade.php',
    'backend/resources/views/operations/school-activities/index.blade.php'
]

# Map of fields to their endpoints and display properties
# field_pattern (id) -> (endpoint, label_field, optional)
# E.g. 'venue' -> ('/api/v1/venues', 'name', False)

for filepath in files_to_patch:
    if not os.path.exists(filepath):
        continue
        
    with open(filepath, 'r') as f:
        content = f.read()

    # 1. Replace raw number inputs with selects
    # e.g. <input type="number" class="ks-form-control" id="createVenue" required>
    # We will look for anything that looks like an input for these specific entities.
    
    replacements = [
        (r'<input type="number"([^>]*)id="createVenue"([^>]*)>', r'<select\1id="createVenue"\2>\n<option value="">Select Venue...</option>\n</select>'),
        (r'<input type="number"([^>]*)id="createFacility"([^>]*)>', r'<select\1id="createFacility"\2>\n<option value="">Select Facility...</option>\n</select>'),
        (r'<input type="number"([^>]*)id="createEmployee"([^>]*)>', r'<select\1id="createEmployee"\2>\n<option value="">Select Employee...</option>\n</select>'),
        (r'<input type="number"([^>]*)id="createVendor"([^>]*)>', r'<select\1id="createVendor"\2>\n<option value="">Select Vendor...</option>\n</select>'),
        (r'<input type="number"([^>]*)id="createVenueHK"([^>]*)>', r'<select\1id="createVenueHK"\2>\n<option value="">Select Venue...</option>\n</select>'),
        (r'<input type="number"([^>]*)id="createFacilityHK"([^>]*)>', r'<select\1id="createFacilityHK"\2>\n<option value="">Select Facility...</option>\n</select>'),
        (r'<input type="number"([^>]*)id="createEmployeeHK"([^>]*)>', r'<select\1id="createEmployeeHK"\2>\n<option value="">Select Employee...</option>\n</select>'),
        # Bookings
        (r'<input type="number"([^>]*)id="bookingVenue"([^>]*)>', r'<select\1id="bookingVenue"\2>\n<option value="">Select Venue...</option>\n</select>'),
        (r'<input type="number"([^>]*)id="bookingFacility"([^>]*)>', r'<select\1id="bookingFacility"\2>\n<option value="">Select Facility...</option>\n</select>'),
        (r'<input type="number"([^>]*)id="bookingTeam"([^>]*)>', r'<select\1id="bookingTeam"\2>\n<option value="">Select Team...</option>\n</select>'),
        (r'<input type="number"([^>]*)id="bookingEvent"([^>]*)>', r'<select\1id="bookingEvent"\2>\n<option value="">Select Event...</option>\n</select>'),
        (r'<input type="number"([^>]*)id="bookingTournament"([^>]*)>', r'<select\1id="bookingTournament"\2>\n<option value="">Select Tournament...</option>\n</select>'),
        # Events
        (r'<input type="number"([^>]*)id="eventVenue"([^>]*)>', r'<select\1id="eventVenue"\2>\n<option value="">Select Venue...</option>\n</select>'),
        (r'<input type="number"([^>]*)id="eventOrganizer"([^>]*)>', r'<select\1id="eventOrganizer"\2>\n<option value="">Select Organizer...</option>\n</select>'),
        # Transport
        (r'<input type="number"([^>]*)id="vehicleDriver"([^>]*)>', r'<select\1id="vehicleDriver"\2>\n<option value="">Select Driver...</option>\n</select>'),
        (r'<input type="number"([^>]*)id="tripDriver"([^>]*)>', r'<select\1id="tripDriver"\2>\n<option value="">Select Driver...</option>\n</select>'),
        (r'<input type="number"([^>]*)id="tripEvent"([^>]*)>', r'<select\1id="tripEvent"\2>\n<option value="">Select Event...</option>\n</select>'),
        # School Activities
        (r'<input type="number"([^>]*)id="activityVenue"([^>]*)>', r'<select\1id="activityVenue"\2>\n<option value="">Select Venue...</option>\n</select>'),
        (r'<input type="number"([^>]*)id="activityEvent"([^>]*)>', r'<select\1id="activityEvent"\2>\n<option value="">Select Event...</option>\n</select>'),
    ]
    
    for old, new in replacements:
        content = re.sub(old, new, content)
        
    # Append JS to fetch data if not already present
    if 'loadGlobalDropdowns()' not in content:
        js_code = """
<script>
// Auto-injected Context-Aware Dropdowns
document.addEventListener('DOMContentLoaded', loadGlobalDropdowns);

async function fetchDropdownData(url) {
    try {
        const res = await fetch(url).then(r => r.json());
        if (res.data && res.data.data) return res.data.data;
        if (res.data) return res.data;
        return [];
    } catch (e) {
        console.error('Error fetching ' + url, e);
        return [];
    }
}

async function populateSelect(selectId, url, labelFn) {
    const select = document.getElementById(selectId);
    if (!select) return;
    const defaultText = select.options[0] ? select.options[0].text : 'Select...';
    select.innerHTML = '<option value="">Loading...</option>';
    const data = await fetchDropdownData(url);
    select.innerHTML = `<option value="">${defaultText}</option>`;
    data.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = labelFn(item);
        select.appendChild(opt);
    });
}

async function loadGlobalDropdowns() {
    const escapeHtml = typeof ksEscape === 'function' ? ksEscape : (s) => String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;");
    
    // Venues
    const venueIds = ['createVenue', 'createVenueHK', 'bookingVenue', 'eventVenue', 'activityVenue'];
    venueIds.forEach(id => {
        populateSelect(id, '/api/v1/venues?limit=100', v => escapeHtml(v.name));
    });

    // Employees
    const empIds = ['createEmployee', 'createEmployeeHK', 'eventOrganizer', 'vehicleDriver', 'tripDriver'];
    empIds.forEach(id => {
        populateSelect(id, '/api/v1/employees?limit=200', e => escapeHtml(e.first_name + ' ' + e.last_name));
    });

    // Vendors
    const vendorIds = ['createVendor'];
    vendorIds.forEach(id => {
        populateSelect(id, '/api/v1/vendors?limit=100', v => escapeHtml(v.vendor_name || v.name));
    });

    // Events
    const eventIds = ['bookingEvent', 'tripEvent', 'activityEvent'];
    eventIds.forEach(id => {
        populateSelect(id, '/api/v1/events?limit=100', e => escapeHtml(e.name || e.event_reference));
    });

    // Cascading Facilities
    const venueFacilityMap = {
        'createVenue': 'createFacility',
        'createVenueHK': 'createFacilityHK',
        'bookingVenue': 'bookingFacility'
    };
    
    for (const [vId, fId] of Object.entries(venueFacilityMap)) {
        const vSelect = document.getElementById(vId);
        const fSelect = document.getElementById(fId);
        if (vSelect && fSelect) {
            vSelect.addEventListener('change', async (e) => {
                const venueId = e.target.value;
                if (!venueId) {
                    fSelect.innerHTML = '<option value="">Select Facility...</option>';
                    return;
                }
                fSelect.innerHTML = '<option value="">Loading...</option>';
                const data = await fetchDropdownData(`/api/v1/venues/${venueId}/facilities`);
                fSelect.innerHTML = '<option value="">Select Facility...</option>';
                data.forEach(f => {
                    const opt = document.createElement('option');
                    opt.value = f.id;
                    opt.textContent = `${escapeHtml(f.name)} (${escapeHtml(f.facility_type)})`;
                    fSelect.appendChild(opt);
                });
            });
        }
    }
}
</script>
"""
        # Insert before closing body or at end
        if '</body>' in content:
            content = content.replace('</body>', js_code + '\n</body>')
        else:
            content += js_code

    with open(filepath, 'w') as f:
        f.write(content)

print("Dropdowns patched successfully.")
