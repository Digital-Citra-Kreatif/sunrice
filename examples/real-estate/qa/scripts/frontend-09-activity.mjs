// The activity log after the MCP session: lines from the AI agent.
import { launch, BASE, DESKTOP, shot, login } from './frontend-lib.mjs';
const b = await launch(); const c = await b.newContext(DESKTOP); const p = await login(c);
await p.goto(BASE + '/admin/activity', { waitUntil: 'networkidle' });
await shot(p, '60-activity-ai-agent', false);
console.log((await p.locator('main').last().innerText()).slice(0, 900).replace(/\n+/g, ' | '));
await b.close();
