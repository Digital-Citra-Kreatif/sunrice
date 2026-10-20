import sys, json, requests
SP='/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/'
class M:
    def __init__(s, kind):
        s.tok=open(SP+'tok_'+kind).read().strip(); s.sid=None; s.n=0
        r=s.call('initialize',{'protocolVersion':'2025-06-18','capabilities':{},'clientInfo':{'name':'qa','version':'1'}})
    def call(s, method, params=None):
        s.n+=1
        h={'Authorization':'Bearer '+s.tok,'Accept':'application/json, text/event-stream','Content-Type':'application/json'}
        if s.sid: h['Mcp-Session-Id']=s.sid
        r=requests.post('http://127.0.0.1:8200/mcp',json={'jsonrpc':'2.0','id':s.n,'method':method,'params':params or {}},headers=h)
        if r.headers.get('Mcp-Session-Id'): s.sid=r.headers['Mcp-Session-Id']
        t=r.text
        if t.startswith('event:') or 'data:' in t[:20]:
            t=[l[5:] for l in t.splitlines() if l.startswith('data:')][-1]
        try: return json.loads(t)
        except Exception: return {'status':r.status_code,'raw':t[:300]}
    def tool(s, name, args):
        return s.call('tools/call',{'name':name,'arguments':args})
def short(x, n=500): return json.dumps(x)[:n]
