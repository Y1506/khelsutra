import re

with open('backend/resources/views/components/navbar.blade.php', 'r') as f:
    content = f.read()

# Replace the conflict block with the upstream version
content = re.sub(r'<<<<<<< HEAD\n.*?\n=======\n(.*?)\n>>>>>>> upstream/feature/resources-finance', 
    r'\1', 
    content, flags=re.DOTALL)

with open('backend/resources/views/components/navbar.blade.php', 'w') as f:
    f.write(content)
