import os
import re

ops_dir = 'backend/resources/views/operations'

venue_ids = []
facility_ids = []
employee_ids = []
vendor_ids = []
event_ids = []
team_ids = []
tournament_ids = []

for root, dirs, files in os.walk(ops_dir):
    for file in files:
        if file.endswith('.blade.php'):
            filepath = os.path.join(root, file)
            with open(filepath, 'r') as f:
                content = f.read()
            
            # Find all <input type="number" ... id="something" ...>
            # that are likely foreign keys.
            def replace_input(match):
                full_tag = match.group(0)
                pre_id = match.group(1)
                html_id = match.group(2)
                post_id = match.group(3)
                
                lower_html = full_tag.lower()
                
                # Deduce what type of foreign key it is based on id or surrounding context
                if 'venue' in lower_html:
                    venue_ids.append(html_id)
                    return f'<select{pre_id}id="{html_id}"{post_id}>\n<option value="">Select Venue...</option>\n</select>'
                elif 'facility' in lower_html:
                    facility_ids.append(html_id)
                    return f'<select{pre_id}id="{html_id}"{post_id}>\n<option value="">Select Facility...</option>\n</select>'
                elif 'employee' in lower_html or 'organizer' in lower_html or 'driver' in lower_html:
                    employee_ids.append(html_id)
                    return f'<select{pre_id}id="{html_id}"{post_id}>\n<option value="">Select Employee...</option>\n</select>'
                elif 'vendor' in lower_html:
                    vendor_ids.append(html_id)
                    return f'<select{pre_id}id="{html_id}"{post_id}>\n<option value="">Select Vendor...</option>\n</select>'
                elif 'event' in lower_html:
                    event_ids.append(html_id)
                    return f'<select{pre_id}id="{html_id}"{post_id}>\n<option value="">Select Event...</option>\n</select>'
                elif 'team' in lower_html:
                    team_ids.append(html_id)
                    return f'<select{pre_id}id="{html_id}"{post_id}>\n<option value="">Select Team...</option>\n</select>'
                elif 'tournament' in lower_html:
                    tournament_ids.append(html_id)
                    return f'<select{pre_id}id="{html_id}"{post_id}>\n<option value="">Select Tournament...</option>\n</select>'
                
                return full_tag

            new_content = re.sub(r'<input\s+type="number"([^>]*?)id="([^"]+)"([^>]*?)>', replace_input, content)
            
            if new_content != content:
                with open(filepath, 'w') as f:
                    f.write(new_content)

print("V:", list(set(venue_ids)))
print("F:", list(set(facility_ids)))
print("E:", list(set(employee_ids)))
print("Vendor:", list(set(vendor_ids)))
print("Event:", list(set(event_ids)))
print("Team:", list(set(team_ids)))
print("Tourn:", list(set(tournament_ids)))

