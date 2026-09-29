import os
import re

files = ['backend/app/Models/Athlete.php', 'backend/app/Models/CoachProfile.php']

for file in files:
    with open(file, 'r') as f:
        content = f.read()

    # Replace duplicate public function sports()
    # Find all occurrences of public function sports() { ... }
    pattern = r'(    public function sports\(\)\n    \{\n        return \$this->belongsToMany\(.*?\)\n            ->withPivot\(\'organization_id\'\)\n            ->withTimestamps\(\);\n    \})'
    
    matches = re.findall(pattern, content, flags=re.DOTALL)
    if len(matches) > 1:
        # Keep only the first occurrence
        content = content.replace(matches[0], '', 1) # remove first instance
        
    # Also check if HasSports is duplicated
    if 'use \App\Traits\HasSports;\n    use \App\Traits\HasSports;' in content:
        content = content.replace('use \App\Traits\HasSports;\n    use \App\Traits\HasSports;', 'use \App\Traits\HasSports;')
        
    with open(file, 'w') as f:
        f.write(content)

