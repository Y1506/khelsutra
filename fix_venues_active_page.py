import os

fixes = {
    'operations/venues/index.blade.php': 'operations_venues',
    'operations/venues/create.blade.php': 'operations_venues',
}

for rel_path, key in fixes.items():
    path = os.path.join('backend/resources/views', rel_path)
    if not os.path.exists(path):
        continue
    
    with open(path, 'r') as f:
        content = f.read()
    
    if '$activePage' in content:
        continue
    
    new_header = f"<?php\n$activePage = '{key}';\n$title = 'Venues Management — KhelSutra';\nob_start();\n?>"
    
    if content.startswith('<?php ob_start(); ?>'):
        content = content.replace('<?php ob_start(); ?>', new_header, 1)
    elif content.startswith('<?php\nob_start();'):
        content = content.replace('<?php\nob_start();', new_header, 1)
    else:
        content = new_header + '\n' + content
        
    with open(path, 'w') as f:
        f.write(content)
