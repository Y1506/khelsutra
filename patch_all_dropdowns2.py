import os
import re

ops_dir = 'backend/resources/views/operations'

venue_selectors = []
facility_selectors = []
employee_selectors = []
vendor_selectors = []
event_selectors = []
team_selectors = []
tournament_selectors = []
accommodation_selectors = []

def get_selector(html_id, html_name):
    if html_id: return f"#{html_id}"
    if html_name: return f"[name='{html_name}']"
    return None

for root, dirs, files in os.walk(ops_dir):
    for file in files:
        if file.endswith('.blade.php'):
            filepath = os.path.join(root, file)
            with open(filepath, 'r') as f:
                content = f.read()
            
            def replace_input(match):
                full_tag = match.group(0)
                
                # Extract id and name
                id_match = re.search(r'id="([^"]+)"', full_tag)
                name_match = re.search(r'name="([^"]+)"', full_tag)
                
                html_id = id_match.group(1) if id_match else ''
                html_name = name_match.group(1) if name_match else ''
                
                sel = get_selector(html_id, html_name)
                if not sel: return full_tag
                
                lower_html = full_tag.lower()
                
                # We need to preserve everything except type="number" and change <input> to <select>
                # But it's easier to just rebuild it or do a simple replace
                new_tag = full_tag.replace('<input ', '<select ').replace('type="number"', '')
                if new_tag.endswith('/>'):
                    new_tag = new_tag[:-2] + '>'
                elif new_tag.endswith('>'):
                    pass
                    
                if 'venue' in lower_html:
                    venue_selectors.append(sel)
                    return f'{new_tag}\n<option value="">Select Venue...</option>\n</select>'
                elif 'facility' in lower_html:
                    facility_selectors.append(sel)
                    return f'{new_tag}\n<option value="">Select Facility...</option>\n</select>'
                elif 'employee' in lower_html or 'organizer' in lower_html or 'driver' in lower_html:
                    employee_selectors.append(sel)
                    return f'{new_tag}\n<option value="">Select Employee...</option>\n</select>'
                elif 'vendor' in lower_html:
                    vendor_selectors.append(sel)
                    return f'{new_tag}\n<option value="">Select Vendor...</option>\n</select>'
                elif 'event' in lower_html:
                    event_selectors.append(sel)
                    return f'{new_tag}\n<option value="">Select Event...</option>\n</select>'
                elif 'team' in lower_html:
                    team_selectors.append(sel)
                    return f'{new_tag}\n<option value="">Select Team...</option>\n</select>'
                elif 'tournament' in lower_html:
                    tournament_selectors.append(sel)
                    return f'{new_tag}\n<option value="">Select Tournament...</option>\n</select>'
                elif 'participant_count' in lower_html or 'capacity' in lower_html or 'year' in lower_html or 'room_number' in lower_html or 'participant' in lower_html:
                    return full_tag # Ignore these valid numbers
                
                return full_tag

            new_content = re.sub(r'<input\s+type="number"[^>]*>', replace_input, content)
            
            # Now let's inject the JS if it doesn't already have loadGlobalDropdowns2
            if new_content != content:
                with open(filepath, 'w') as f:
                    f.write(new_content)
                print(f"Patched HTML in {filepath}")

print("V:", list(set(venue_selectors)))
print("F:", list(set(facility_selectors)))
print("E:", list(set(employee_selectors)))
print("Vendor:", list(set(vendor_selectors)))
print("Event:", list(set(event_selectors)))
print("Team:", list(set(team_selectors)))
print("Tourn:", list(set(tournament_selectors)))

