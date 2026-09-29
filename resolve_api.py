import re

with open('backend/routes/api.php', 'r') as f:
    content = f.read()

# I need to properly resolve the $skeletonGroups conflict.
content = re.sub(r'<<<<<<< HEAD\n(.*?)\n=======\n(.*?)\n>>>>>>> upstream/feature/resources-finance', 
    lambda m: m.group(1) + '\n' + m.group(2) if 'skeletonGroups' not in m.group(1) else m.group(2), 
    content, flags=re.DOTALL)

with open('backend/routes/api.php', 'w') as f:
    f.write(content)
