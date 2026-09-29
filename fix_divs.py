import re

with open('backend/resources/views/operations/accommodation/index.blade.php', 'r') as f:
    content = f.read()

# Just replace all `</select></div>` with `</select>`
content = content.replace('</select></div>', '</select>')

with open('backend/resources/views/operations/accommodation/index.blade.php', 'w') as f:
    f.write(content)
