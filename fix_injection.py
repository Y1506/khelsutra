import re

for filename in ['backend/resources/views/competitions/tournaments-create.blade.php', 'backend/resources/views/competitions/tournaments-edit.blade.php']:
    with open(filename, 'r') as f:
        content = f.read()

    js = """
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sportSelect = document.getElementById('sportSelect');
    const venueSelect = document.getElementById('venueSelect');
    
    if (sportSelect && venueSelect) {
        // Clone all original options
        const allOptions = Array.from(venueSelect.options).map(opt => opt.cloneNode(true));
        
        sportSelect.addEventListener('change', function() {
            const selectedSport = this.value;
            const currentSelectedVenue = venueSelect.value;
            
            // Clear current options
            venueSelect.innerHTML = '';
            
            // Filter options
            allOptions.forEach(opt => {
                if (opt.value === '') {
                    venueSelect.appendChild(opt.cloneNode(true)); // Add placeholder
                } else {
                    const sportsStr = opt.getAttribute('data-sports') || '';
                    const sportsArr = sportsStr.split(',');
                    
                    // If no sport selected, or venue has no sports (assume general purpose), or venue has the sport
                    if (!selectedSport || sportsStr === '' || sportsArr.includes(selectedSport)) {
                        venueSelect.appendChild(opt.cloneNode(true));
                    }
                }
            });
            
            // Try to restore previous selection if it's still available
            let match = Array.from(venueSelect.options).find(opt => opt.value === currentSelectedVenue);
            if (match) {
                venueSelect.value = currentSelectedVenue;
            } else {
                venueSelect.value = '';
            }
        });
        
        // Trigger initial filter
        const initialVenue = venueSelect.value;
        sportSelect.dispatchEvent(new Event('change'));
        if(initialVenue) venueSelect.value = initialVenue;
    }
});
</script>
"""

    if "<script>" not in content:
        content = content.replace('</div>\n\n<?php', '</div>\n\n' + js + '\n<?php')
        with open(filename, 'w') as f:
            f.write(content)
