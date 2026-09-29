import re

with open('backend/resources/views/operations/accommodation/index.blade.php', 'r') as f:
    content = f.read()

content = content.replace('</select>\n        <div><button class="ks-btn ks-btn-secondary w-100" onclick="clearFilters()">', '</select></div>\n        <div><button class="ks-btn ks-btn-secondary w-100" onclick="clearFilters()">')

with open('backend/resources/views/operations/accommodation/index.blade.php', 'w') as f:
    f.write(content)
