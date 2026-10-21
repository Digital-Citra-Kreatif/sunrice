import { launch, session, shot, BASE, toasts, errors, pick } from './editor-lib.mjs';
import { fillRich, fillFields } from './editor-content.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
async function toId() { await p.getByRole('combobox', { name: 'Language' }).click(); await p.getByRole('option', { name: 'Indonesian' }).click(); await p.waitForLoadState('networkidle'); await p.waitForTimeout(500); }
async function save(tag) { await p.getByRole('button', { name: 'Save' }).click(); console.log(tag, await toasts(p, 1200), await errors(p)); }
async function editable(id) { return p.locator(id).isEditable().catch(() => false); }
// company EN
await p.goto(BASE + '/admin/cms/globals/company', { waitUntil: 'networkidle' });
await fillFields(p, { legal_name: 'PT Lumina Estates Indonesia', tagline: 'Homes of quiet distinction', phone: '+62 21 7199 4400', whatsapp: '+62 811 1900 4400', email: 'hello@lumina-estates.test',
  address: 'Jl. Kemang Raya No. 8\nBangka, Mampang Prapatan\nJakarta Selatan 12730\nIndonesia', office_hours: 'Monday to Friday: 9:00–18:00\nSaturday: 10:00–15:00\nSunday and public holidays: by appointment',
  map: 'https://maps.google.com/?q=Jl.%20Kemang%20Raya%208%20Jakarta', 'primary_contact-name': 'Anindya Wirawan', 'primary_contact-role': 'Principal', 'primary_contact-phone': '+62 21 7199 4400', 'primary_contact-email': 'anindya@lumina-estates.test' });
await save('company en');
await toId();
const st = {}; for (const k of ['legal_name', 'tagline', 'phone', 'address', 'office_hours', 'primary_contact-role']) st[k] = await editable('#data-' + k);
console.log('company id editable', st);
await shot(p, '73-company-id', true);
if (st.tagline) await p.fill('#data-tagline', 'Hunian dengan keanggunan yang tenang');
if (st.office_hours) await p.fill('#data-office_hours', 'Senin–Jumat: 09.00–18.00\nSabtu: 10.00–15.00\nMinggu dan hari libur: dengan perjanjian');
if (st.address) await p.fill('#data-address', 'Jl. Kemang Raya No. 8\nBangka, Mampang Prapatan\nJakarta Selatan 12730\nIndonesia');
if (st['primary_contact-role']) await p.fill('#data-primary_contact-role', 'Prinsipal');
await save('company id');
// social
await p.goto(BASE + '/admin/cms/globals/social', { waitUntil: 'networkidle' });
for (const [i, [n, u]] of [['Instagram', 'https://www.instagram.com/luminaestates'], ['LinkedIn', 'https://www.linkedin.com/company/lumina-estates'], ['Facebook', 'https://www.facebook.com/luminaestates'], ['YouTube', 'https://www.youtube.com/@luminaestates']].entries()) {
  await p.getByRole('button', { name: 'Add link' }).click(); await p.waitForTimeout(200);
  const ok = await pick(p, `#data-links-${i}-network`, n);
  if (!ok) console.log('NETWORK OPTION MISSING', n);
  await p.fill(`#data-links-${i}-url`, u);
}
await save('social');
await shot(p, '74-social', true);
// footer
await p.goto(BASE + '/admin/cms/globals/footer', { waitUntil: 'networkidle' });
await p.fill('#data-about', 'Lumina Estates is an independent agency for exceptional homes, villas and land in Jakarta and Bali. Offices in Kemang and Canggu.');
await fillRich(p, '#data-legal', '<p>© 2026 PT Lumina Estates Indonesia. Licensed real estate broker (SIU-P4) No. 0012/2014. All prices are indicative and subject to change.</p>');
await p.fill('#data-newsletter_heading', 'Quarterly market notes, in your inbox');
await save('footer en');
await toId();
console.log('footer id editable', await editable('#data-about'), await editable('#data-newsletter_heading'));
await p.fill('#data-about', 'Lumina Estates adalah agen independen untuk rumah, vila dan tanah istimewa di Jakarta dan Bali. Kantor di Kemang dan Canggu.');
await fillRich(p, '#data-legal', '<p>© 2026 PT Lumina Estates Indonesia. Perantara perdagangan properti berizin (SIU-P4) No. 0012/2014. Semua harga bersifat indikatif dan dapat berubah.</p>');
await p.fill('#data-newsletter_heading', 'Catatan pasar setiap kuartal, langsung ke kotak masuk Anda');
await save('footer id');
await shot(p, '75-footer-id', true);
await b.close();
