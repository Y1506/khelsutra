import re

with open('backend/resources/views/competitions/tournaments-edit.blade.php', 'r') as f:
    content = f.read()

# Update venue query
old_query = """$venueStmt = $db->prepare("SELECT id, name FROM venues WHERE organization_id = :org_id AND status = 'active' AND deleted_at IS NULL ORDER BY name ASC");"""
new_query = """$venueStmt = $db->prepare("SELECT v.id, v.name, GROUP_CONCAT(vs.sport_id) as sport_ids FROM venues v LEFT JOIN venue_sports vs ON v.id = vs.venue_id WHERE v.organization_id = :org_id AND v.status = 'active' AND v.deleted_at IS NULL GROUP BY v.id, v.name ORDER BY v.name ASC");"""
content = content.replace(old_query, new_query)

# Update sport select
content = content.replace('<select name="sport_id"', '<select name="sport_id" id="sportSelect"')

# Update venue select
content = content.replace('<select name="venue_id"', '<select name="venue_id" id="venueSelect"')

# Update venue options
old_option = """<option value="<?= (int)$v['id'] ?>" <?= ($v['id'] == $tournament['venue_id']) ? 'selected' : '' ?>><?= htmlspecialchars($v['name'], ENT_QUOTES, 'UTF-8') ?></option>"""
new_option = """<option value="<?= (int)$v['id'] ?>" data-sports="<?= htmlspecialchars($v['sport_ids'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= ($v['id'] == $tournament['venue_id']) ? 'selected' : '' ?>><?= htmlspecialchars($v['name'], ENT_QUOTES, 'UTF-8') ?></option>"""
content = content.replace(old_option, new_option)

# Inject JS
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
        
        // Trigger initial filter but retain the original selection if valid
        const initialVenue = venueSelect.value;
        sportSelect.dispatchEvent(new Event('change'));
        if(initialVenue) venueSelect.value = initialVenue;
    }
});
</script>
"""

content = content.replace("</div>\n</div>\n<?php", "</div>\n</div>\n" + js + "\n<?php")

with open('backend/resources/views/competitions/tournaments-edit.blade.php', 'w') as f:
    f.write(content)
