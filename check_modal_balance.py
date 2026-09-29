import re

html = open('backend/resources/views/operations/accommodation/index.blade.php').read()

match = re.search(r'(<div class="modal fade" id="accommodationModal".*?)<!-- End Modal -->', html, re.DOTALL)
if not match:
    match = re.search(r'(<div class="modal fade" id="accommodationModal".*?)</script>', html, re.DOTALL)

if match:
    modal_html = match.group(1)
    # let's just find the form
    form_match = re.search(r'(<form.*?</form>)', modal_html, re.DOTALL)
    if form_match:
        form_html = form_match.group(1)
        div_count = 0
        for tag in re.findall(r'<\/?div[^>]*>', form_html):
            if tag.startswith('</div'):
                div_count -= 1
            else:
                div_count += 1
        print(f"Form div count: {div_count}")
