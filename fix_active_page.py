import os
import glob

fixes = {
    'operations/events/index.blade.php': 'events',
    'operations/school-activities/index.blade.php': 'events',
    'operations/accommodation/index.blade.php': 'accommodation',
    'operations/bookings/index.blade.php': 'bookings',
    'operations/transport/index.blade.php': 'transport',
    'operations/housekeeping/index.blade.php': 'maintenance',
    'operations/venues/show.blade.php': 'operations_venues',
    'operations/venues/edit.blade.php': 'operations_venues',
    'operations/facilities/index.blade.php': 'operations_venues',
    'operations/facilities/create.blade.php': 'operations_venues',
    'operations/facilities/edit.blade.php': 'operations_venues'
}

for rel_path, key in fixes.items():
    path = os.path.join('backend/resources/views', rel_path)
    if not os.path.exists(path):
        continue
    
    with open(path, 'r') as f:
        content = f.read()
    
    # Check if activePage is already set
    if '$activePage' in content:
        continue
    
    # Replace <?php ob_start(); ?> with setting activePage
    new_header = f"<?php\n$activePage = '{key}';\n$title = '{key.title().replace('_', ' ')} Management — KhelSutra';\nob_start();\n?>"
    
    if content.startswith('<?php ob_start(); ?>'):
        content = content.replace('<?php ob_start(); ?>', new_header, 1)
    elif content.startswith('<?php\nob_start();'):
        content = content.replace('<?php\nob_start();', new_header, 1)
    else:
        # Just prepend it
        content = new_header + '\n' + content
        
    with open(path, 'w') as f:
        f.write(content)

print("Fixed activePage variables!")
