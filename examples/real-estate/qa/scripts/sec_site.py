import re, time, requests, html
BASE='http://127.0.0.1:8200'
def form_fields(text, form_id):
    i=text.find(f'id="{form_id}"'); j=text.find('</form>', i)
    chunk=text[i:j]
    fields={}
    for m in re.finditer(r'<input[^>]*>', chunk):
        tag=m.group(0)
        n=re.search(r'name="([^"]*)"', tag); v=re.search(r'value="([^"]*)"', tag); t=re.search(r'type="([^"]*)"', tag)
        if n and (t is None or t.group(1) in ('hidden','text')) and not n.group(1).startswith('data['):
            fields[n.group(1)]=html.unescape(v.group(1)) if v else ''
    return fields
