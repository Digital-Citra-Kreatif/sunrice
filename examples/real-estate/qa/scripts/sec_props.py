import sys, re, json
sys.path.insert(0,'/home/user/realestate/qa/scripts')
from sec_lib import *
from sec_sweep import GETS
pat=re.compile(r'("[^"]*(password|token|secret|api_key|apikey|hash|remember|smtp|key)[^"]*"\s*:\s*("[^"]{0,80}"|[^,}\]]{0,40}))|([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,})', re.I)
for u in sys.argv[1:]:
    s=login(u)
    for g in GETS:
        if 'export' in g: continue
        r,d=page(s,g)
        if not d: continue
        txt=json.dumps(d)
        hits=set(m.group(0)[:120] for m in pat.finditer(txt))
        hits={h for h in hits if not h.startswith('"key"') and u+"@" not in h and "preferencesKey" not in h}
        if hits: print(u,g,sorted(hits)[:15])
