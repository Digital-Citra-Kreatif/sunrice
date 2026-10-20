// MCP client for the QA: node frontend-mcp.mjs <ro|full> <method> ['<json params>']
// Tools: node frontend-mcp.mjs full tools/call '{"name":"get_panel_info","arguments":{}}'
import fs from 'node:fs';
const [kind, method, params = '{}'] = process.argv.slice(2);
const toks = JSON.parse(fs.readFileSync('/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/ai-tokens.json', 'utf8'));
const t = typeof toks[kind] === 'string' ? toks[kind] : toks[kind].token;
const t0 = Date.now();
const res = await fetch('http://127.0.0.1:8200/mcp', { method: 'POST', headers: { Authorization: 'Bearer ' + t, 'Content-Type': 'application/json', Accept: 'application/json, text/event-stream' }, body: JSON.stringify({ jsonrpc: '2.0', id: 1, method, params: JSON.parse(params) }) });
const text = await res.text();
let out = text;
try { const j = JSON.parse(text); if (j.result?.content) { out = j.result.content.map((c) => c.text ?? JSON.stringify(c)).join('\n') + (j.result.isError ? '\n[isError]' : ''); } else out = JSON.stringify(j.result ?? j.error ?? j, null, 1); } catch {}
console.log(`[${res.status} ${Date.now() - t0}ms]`);
console.log(out);
