import { launch, session, shot, BASE } from './editor-lib.mjs';
import { createEntry } from './editor-content.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/collections/neighbourhoods/entries/create', { waitUntil: 'networkidle' });
console.log(await p.getByRole('button').allInnerTexts());
const types = { body: 'rich', highlights: 'repeater:Add highlight' };
const N = [
 ['Kemang', { intro: 'Leafy, cosmopolitan and walkable by Jakarta standards, Kemang is where expatriate families and creative professionals settle for its international schools, galleries and garden houses.',
   body: '<h2>Living in Kemang</h2><p>Kemang grew from a quiet orchard village into South Jakarta\'s most international neighbourhood. Low-rise houses with gardens line the side streets off Jalan Kemang Raya, while the main road keeps its cafés, design shops and galleries.</p><h3>Who lives here</h3><ul><li>Families with children at the Jakarta Intercultural School and nearby international schools</li><li>Diplomats and corporate leaders who prefer a house to a tower</li><li>Architects, designers and restaurateurs</li></ul><p>Expect traffic at school hours and plan around the rainy season: we can tell you which streets stay dry.</p>',
   highlights: [{ label: 'International schools', text: 'JIS Pattimura and several British and French schools within 15 minutes.' }, { label: 'Garden houses', text: 'Rare in Jakarta: detached homes on 600–1,500 m² plots with mature trees.' }, { label: 'Dining and culture', text: 'Galleries, specialty coffee and some of the city\'s best restaurants on Jalan Kemang Raya.' }],
   average_price: '28000000000' }],
 ['Menteng', { intro: 'Jakarta\'s original garden suburb: Dutch-era villas, wide boulevards and parks, a short drive from the central business district.',
   body: '<h2>A heritage address</h2><p>Planned in the 1910s as Batavia\'s first garden city, Menteng still has the tree-lined streets, roundabouts and parks of its original design. Many villas are protected heritage buildings, which keeps the neighbourhood\'s character intact and supply very limited.</p><h3>What to know before buying</h3><ul><li>Heritage-listed homes need approval for exterior changes</li><li>Plots are large, often 1,000 m² or more</li><li>Ten minutes to Sudirman and Thamrin</li></ul>',
   highlights: [{ label: 'Heritage villas', text: 'Art deco and Indies-style homes from the 1920s and 1930s.' }, { label: 'Parks', text: 'Taman Suropati and Taman Menteng are on the doorstep.' }, { label: 'Central', text: 'The business district, embassies and Gondangdia station within minutes.' }],
   average_price: '65000000000' }],
 ['Pondok Indah', { intro: 'A planned residential estate in South Jakarta with spacious family homes, a championship golf course, hospitals and two large malls.',
   body: '<h2>Space for families</h2><p>Pondok Indah was developed in the 1970s as an upmarket estate and remains one of the most sought-after addresses for families who want space, security and everything close by.</p><ul><li>Pondok Indah Golf Course and Pondok Indah Hospital</li><li>Pondok Indah Mall 1, 2 and 3</li><li>Direct access to the JORR toll road</li></ul>',
   highlights: [{ label: 'Golf and green', text: 'Homes along the fairways of Pondok Indah Golf Course.' }, { label: 'Healthcare', text: 'Pondok Indah Hospital, one of Jakarta\'s best private hospitals.' }, { label: 'Connected', text: 'Toll road access to the airport and the CBD.' }],
   average_price: '32000000000' }],
 ['Canggu', { intro: 'Bali\'s west coast surf village turned lifestyle hub: rice fields, beach clubs and modern villas between Berawa and Pererenan.',
   body: '<h2>From rice fields to beach clubs</h2><p>Canggu has changed faster than anywhere else in Bali. Rice fields still frame many villas, yet the area now offers co-working spaces, international schools and some of the island\'s best restaurants.</p><h3>Areas within Canggu</h3><ul><li><strong>Berawa</strong>: lively, close to Finns and Atlas beach clubs</li><li><strong>Batu Bolong and Echo Beach</strong>: the surf heart of the village</li><li><strong>Pererenan</strong>: quieter, with views over the fields</li></ul><p>Rental demand is strong all year, which makes Canggu popular with investors.</p>',
   highlights: [{ label: 'Surf and beaches', text: 'Batu Bolong, Echo Beach and Pererenan are ten minutes apart.' }, { label: 'Rental yields', text: 'High year-round occupancy for well-run villas.' }, { label: 'Schools', text: 'Canggu Community School and several Montessori options.' }],
   average_price: '9500000000' }],
 ['Ubud', { intro: 'Bali\'s cultural heart in the hills of Gianyar: jungle valleys, rice terraces, yoga and a slower, greener way of life.',
   body: '<h2>Green, cultural, calm</h2><p>Ubud sits among river valleys and terraced rice fields, half an hour inland from the coast. Villas here are about views and privacy: a ridge over the Ayung river, a garden facing the fields.</p><ul><li>Royal palace, museums and the Saturday art market</li><li>Wellness retreats and yoga studios</li><li>Cooler evenings than the coast</li></ul>',
   highlights: [{ label: 'Rice-field views', text: 'Homes facing the terraces of Tegallalang and Sayan.' }, { label: 'Culture', text: 'Temples, dance and craft villages all around.' }, { label: 'Wellness', text: 'Some of the world\'s best-known retreats.' }],
   average_price: '7200000000' }],
];
for (const [t, f] of N) await createEntry(p, BASE, 'neighbourhoods', t, f, types);
await shot(p, '13-neighbourhood-ubud', true);
await b.close();
