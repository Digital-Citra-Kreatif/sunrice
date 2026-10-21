# Wanted Unsplash photos

Photos to download from Unsplash and import into the media library (with alt text) once images.unsplash.com is reachable. Fields stay empty until then.

## From the developer (design needs)

The templates are designed around large photography; these slots render a quiet placeholder until filled.

| Where (field) | Orientation, min size | Search terms |
| --- | --- | --- |
| Home → hero block `image` | landscape, 2400×1400+ | "bali villa infinity pool sunset", "tropical modern villa dusk" |
| Home → image-text block `image` | portrait 4:5, 1600×2000 | "luxury living room natural light wood", "interior architect tropical" |
| Properties listing → `hero` (listing page) | landscape, 2400×1000, darkish | "jakarta skyline dusk", "aerial rice terraces bali" |
| Neighbourhood `hero` (Canggu, Seminyak, Ubud, Kemang, Menteng, Pondok Indah) | landscape 2400×1400 | "canggu rice field", "seminyak beach sunset", "ubud jungle", "kemang jakarta street", "menteng colonial house", "jakarta residential green" |
| Property `gallery` (5–12 per property) | landscape 3:2, 2000px | "modern villa exterior pool", "villa bedroom tropical", "luxury bathroom stone", "open kitchen villa", "garden villa bali", "apartment jakarta view" |
| Agent `photo` | portrait 4:5, 1200×1500 | "professional portrait asian woman smiling", "professional portrait indonesian man suit" |
| Journal `cover` | landscape 3:2, 2000px | "real estate market chart", "house keys hand", "bali architecture detail" |
| Testimonial `photo` | square 400×400 | "portrait couple smiling outdoor" |
| Settings → Site share image and Organization logo | 1200×630 / the logo SVG | share: "bali villa pool wide"; logo: public/images/lumina-logo.svg (export to PNG for the media library) |

## From the admin (panel branding)

| Where (field) | Orientation, min size | Search terms |
| --- | --- | --- |
| Settings → Branding → Sign-in page → Image (side panel, admin only) | portrait ~3:4, 1400×1800, calm/neutral | "minimal tropical architecture detail", "white villa wall shadow palm", "bali villa courtyard morning" |
| Settings → Branding → Logo / Browser icon | square SVG/PNG | not a photo: `public/images/lumina-logo.svg` once the developer adds it (it was missing during the admin run) |

## From the editor (per entry)

Every media field below was left empty on purpose. Alt text to use is given in quotes. Search terms are for unsplash.com.

| Entry | Field | What the photo should show | Unsplash search terms |
| --- | --- | --- | --- |
| Agent: Anindya Wirawan (Principal & Head of Sales, Jakarta) | `photo` (portrait 4:5) | Indonesian woman, 40s, smart blazer, warm neutral background, calm smile. "Anindya Wirawan, Principal" | "professional portrait indonesian woman blazer", "asian businesswoman portrait neutral background" |
| Agent: Rafael Hartono (Senior Partner, Villas & Land, Bali) | `photo` | Indonesian man, 40s, linen shirt, tropical greenery out of focus. "Rafael Hartono" | "indonesian man portrait linen shirt", "asian man portrait tropical background" |
| Agent: Sekar Ayuningtyas (Leasing Director, Jakarta) | `photo` | Indonesian woman, 30s, professional, bright office. "Sekar Ayuningtyas" | "young asian woman professional portrait office" |
| Agent: Maarten de Vries (Client Advisor, Ubud) | `photo` | European man, 40s–50s, relaxed smart-casual, rice field or garden behind. "Maarten de Vries" | "european man portrait outdoor smart casual", "man portrait bali" |
| Neighbourhood: Kemang | `hero` | Leafy South Jakarta street, low-rise houses, cafés | "jakarta street trees", "kemang jakarta", "jakarta residential street" |
| Neighbourhood: Menteng | `hero` | Dutch-era villa or tree-lined boulevard, Taman Suropati | "menteng jakarta", "colonial house jakarta", "taman suropati" |
| Neighbourhood: Pondok Indah | `hero` | Green golf fairway with houses, or wide residential street | "golf course houses asia", "jakarta suburb green" |
| Neighbourhood: Canggu | `hero` | Rice fields with palms, or Echo Beach surf | "canggu rice field", "echo beach bali", "bali rice field palm" |
| Neighbourhood: Ubud | `hero` | Jungle valley / rice terraces at morning light | "ubud rice terraces", "ubud jungle valley", "tegallalang" |
| Property: Villa Arunika, Pererenan (and Villa Arunika Dua) | `gallery` (6–10) + `floor_plan` | Tropical modern villa: pool facing rice fields, open living pavilion, teak and concrete, bedroom, garden bathroom, dusk exterior | "modern villa pool rice field", "bali villa living room open", "tropical bathroom stone", "bali villa dusk" |
| Property: Kemang Garden House | `gallery` | Large modern family house with lawn and pool, double-height living room | "modern house garden pool", "double height living room", "family house lawn" |
| Property: Menteng Heritage Residence | `gallery` | Colonial/Indies villa facade, terrazzo floors, high ceilings, formal garden | "colonial villa white facade", "terrazzo floor interior", "heritage house garden fountain" |
| Property: Sayan Ridge Villa, Ubud | `gallery` | Infinity pool over jungle valley, joglo pavilion, yoga deck | "infinity pool jungle", "joglo house", "ubud villa valley view" |
| Property: Pondok Indah Golf-view Family Home | `gallery` | House facing a golf fairway, bright living room, pool | "house golf course view", "bright living room large windows" |
| Property: Signature Apartment, Kebayoran Baru | `gallery` | High-floor apartment with city views, refined interior | "jakarta apartment view", "luxury apartment city view night" |
| Property: Kemang Townhouse for Lease | `gallery` | Modern townhouse, roof terrace, shared pool | "modern townhouse roof terrace", "compound shared pool" |
| Property: Menteng Park Apartment, Two Bedrooms | `gallery` | Furnished apartment, balcony over park | "apartment balcony park view", "furnished apartment living room" |
| Property: Rice-field Land in Pererenan | `gallery` | Flat green rice field plot, palms | "bali rice field aerial", "rice paddy land plot" |
| Property: Riverside Land near Ubud | `gallery` | Sloping jungle land, river below | "bali river jungle", "tropical land river" |
| Property: Seminyak Beachside Villa | `gallery` | Balinese villa with alang-alang roof, long pool, garden | "balinese villa thatched roof pool", "seminyak villa" |
| Property: Berawa Boutique Retail Space | `gallery` | Glass-fronted shop/café space, tropical street | "boutique shop front tropical", "cafe interior bali" |
| Property: Kemang Raya Corner Office Building | `gallery` | Low-rise modern office building on a corner | "modern office building corner street", "small office building facade" |
| Property: Villa Tirta, Penestanan (draft) | `gallery` | Small villa among rice terraces, plunge pool | "plunge pool villa rice terrace", "ubud villa small" |
| Property: Pondok Indah Garden Penthouse | `gallery` | Penthouse roof garden with plunge pool, skyline | "penthouse roof garden pool", "jakarta skyline penthouse" |
| Journal: Jakarta prime residential market: Q3 2026 review | `cover` | Jakarta skyline / residential aerial | "jakarta skyline", "jakarta aerial residential" |
| Journal: Buying property in Bali as a foreigner | `cover` | Signing documents, keys, villa | "signing contract house keys", "bali villa entrance" |
| Journal: Living in Ubud: a year in the hills | `cover` | Ubud morning, offerings, rice terrace path | "ubud morning", "balinese offering canang", "rice terrace path" |
| Journal: Renting a family home in South Jakarta | `cover` | Family moving into a house, bright home | "family new home", "moving boxes house" |
| Journal: Lumina Estates opens a new office in Canggu | `cover` | Bright modern office interior with tropical plants | "modern office tropical plants", "small office interior bali" |
| Journal: Five things to check before renewing a villa lease | `cover` | Contract on a table with villa in background | "contract pen table", "lease agreement" |
| Testimonials (4) | `photo` (square) | Leave empty unless real client photos exist; otherwise "couple portrait outdoor" for the Whitfields only | "couple portrait outdoor smiling" |
| Home → hero / image-text | `image` | See the developer's table above | (above) |
| Properties listing page | `hero` | See the developer's table above | (above) |
| Term pages Villa, Canggu | SEO share image | Villa pool wide; Canggu rice field | "bali villa pool wide", "canggu rice field" |
