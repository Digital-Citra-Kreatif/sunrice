import requests, re, json, html, urllib.parse
BASE='http://127.0.0.1:8200'
PW={'ayu':'Lumina!Admin2026','budi':'Lumina!Budi2026','citra':'Lumina!Citra2026','dewi':'Lumina!Dewi2026','eko':'Lumina!Eko2026','fajar':'Lumina!Fajar2026'}
def email(u): return f'{u}@lumina-estates.test'
def xsrf(s):
    return urllib.parse.unquote(s.cookies.get('XSRF-TOKEN',''))
def login(u, pw=None, mail=None):
    s=requests.Session()
    s.get(BASE+'/admin/login')
    r=s.post(BASE+'/admin/login',json={'email':mail or email(u),'password':pw or PW[u],'remember':False},headers=hdr(s),allow_redirects=False)
    s.login_status=r.status_code; s.login_resp=r
    return s
def hdr(s, inertia=False, extra=None):
    h={'X-XSRF-TOKEN':xsrf(s),'Accept':'text/html, application/xhtml+xml','X-Requested-With':'XMLHttpRequest'}
    if inertia:
        h['X-Inertia']='true'
        v=getattr(s,'iver',None)
        if v: h['X-Inertia-Version']=v
    if extra: h.update(extra)
    return h
def page(s, path):
    r=s.get(BASE+path, allow_redirects=False)
    m=None
    if True:
        m2=re.search(r'<script[^>]*data-page="app"[^>]*>(.*?)</script>', r.text, re.S)
        if m2:
            d=json.loads(m2.group(1)); s.iver=d.get('version'); return r, d
        return r, None
    d=json.loads(html.unescape(m.group(1)))
    s.iver=d.get('version')
    return r, d
def req(s, method, path, data=None, inertia=True, files=None, json_=True):
    h=hdr(s, inertia)
    if files is not None:
        return s.request(method, BASE+path, data=data, files=files, headers=h, allow_redirects=False)
    return s.request(method, BASE+path, json=data, headers=h, allow_redirects=False)
