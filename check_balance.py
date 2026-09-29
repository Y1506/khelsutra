import re
html = open('backend/resources/views/operations/accommodation/index.blade.php').read()
div_count = 0
for tag in re.findall(r'<\/?div[^>]*>', html):
    if tag.startswith('</div'):
        div_count -= 1
    else:
        div_count += 1
print(f"Final div count: {div_count}")
