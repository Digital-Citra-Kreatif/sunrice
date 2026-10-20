<?php

declare(strict_types=1);

namespace Tests\Support;

use Database\Seeders\StructureSeeder;
use Illuminate\Support\Facades\Artisan;
use Sunrice\Admin\Actions\Settings\SaveSettings;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Entries\SaveEntrySettings;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;
use Sunrice\Cms\Settings\SiteSettings;

/**
 * Builds a small Lumina site for the tests: the real content model (database/structure.json, as a fresh
 * install imports it) and a little content written through the CMS's own actions, in English and Indonesian.
 */
final class LuminaSite
{
    /** @var array<string, Term> by "taxonomy/slug" */
    public array $terms = [];

    /** @var array<string, EntryTranslation> by "collection/slug" */
    public array $entries = [];

    public static function build(): self
    {
        $site = new self;
        Artisan::call('db:seed', ['--class' => StructureSeeder::class, '--force' => true]);
        $site->content();

        return $site;
    }

    public function entry(string $key): EntryTranslation
    {
        return $this->entries[$key];
    }

    public function term(string $key): Term
    {
        return $this->terms[$key];
    }

    private function content(): void
    {
        foreach (['Villa', 'House', 'Apartment'] as $title) {
            $this->addTerm('property_types', $title);
        }
        $this->addTerm('listing_types', 'For sale', 'for-sale');
        $this->addTerm('listing_types', 'For rent', 'for-rent');
        foreach (['Private pool', 'Garden', 'Ocean view'] as $title) {
            $this->addTerm('amenities', $title);
        }
        $bali = $this->addTerm('locations', 'Bali');
        $badung = $this->addTerm('locations', 'Badung', parent: $bali);
        $this->addTerm('locations', 'Canggu', parent: $badung);
        $this->addTerm('locations', 'Seminyak', parent: $badung);
        $jakarta = $this->addTerm('locations', 'Jakarta');
        $south = $this->addTerm('locations', 'South Jakarta', parent: $jakarta);
        $this->addTerm('locations', 'Kemang', parent: $south);
        $this->addTerm('topics', 'Market reports');
        $this->addTerm('topics', 'Buying guides');
        $this->translateTerm('locations/bali', 'Bali', 'bali');
        $this->translateTerm('property_types/villa', 'Vila', 'vila');

        $ayu = $this->addEntry('agents', 'Ayu Pratiwi', ['role' => 'Founder & Principal', 'phone' => '+62 21 555 0101', 'whatsapp' => '+62 811 555 0101', 'email' => 'ayu@lumina-estates.test', 'languages' => ['english', 'indonesian'], 'bio' => '<p>Twenty years in Jakarta and Bali.</p>']);
        $budi = $this->addEntry('agents', 'Budi Santoso', ['role' => 'Senior Agent, Bali']);
        $canggu = $this->addEntry('neighbourhoods', 'Canggu', ['intro' => 'Rice fields, surf and slow mornings.', 'body' => '<p>Canggu is Bali’s most sought-after coast.</p>', 'highlights' => [['label' => 'Beaches', 'text' => 'Echo Beach and Batu Bolong.']], 'average_price' => 18_500_000_000]);

        $this->addEntry('properties', 'Villa Samudra', [
            'summary' => 'A five-bedroom villa above the rice fields of Canggu.',
            'price' => 28_500_000_000, 'price_suffix' => 'none', 'property_status' => 'available',
            'bedrooms' => 5, 'bathrooms' => 6, 'building_area' => 620, 'land_area' => 1200, 'year_built' => 2021,
            'address' => 'Jalan Pantai Berawa, Canggu',
            'map' => ['type' => 'url', 'url' => 'https://maps.google.com/?q=Canggu', 'label' => null, 'new_tab' => true],
            'description' => '<p>Designed by a Balinese studio, open to the garden.</p>',
            'features' => [['label' => 'Infinity pool'], ['label' => 'Yoga pavilion']],
            'agent' => $ayu->entry_id, 'neighbourhood' => $canggu->entry_id, 'featured' => true,
            'property_type' => $this->terms['property_types/villa']->id, 'listing_type' => $this->terms['listing_types/for-sale']->id,
            'location' => $this->terms['locations/canggu']->id,
            'amenities' => [$this->terms['amenities/private-pool']->id, $this->terms['amenities/garden']->id],
        ]);
        $this->addEntry('properties', 'Seminyak Pool Villa', [
            'summary' => 'A walled pool villa, five minutes from the beach.', 'price' => 95_000_000, 'price_suffix' => 'per_month', 'property_status' => 'available',
            'bedrooms' => 3, 'bathrooms' => 3, 'building_area' => 280, 'agent' => $budi->entry_id, 'featured' => true,
            'property_type' => $this->terms['property_types/villa']->id, 'listing_type' => $this->terms['listing_types/for-rent']->id,
            'location' => $this->terms['locations/seminyak']->id,
        ]);
        $this->addEntry('properties', 'Kemang Garden House', [
            'summary' => 'A family house with a mature garden in Kemang.', 'price' => 14_200_000_000, 'price_suffix' => 'none', 'property_status' => 'under_offer',
            'bedrooms' => 4, 'bathrooms' => 4, 'building_area' => 450, 'land_area' => 600, 'agent' => $ayu->entry_id,
            'property_type' => $this->terms['property_types/house']->id, 'listing_type' => $this->terms['listing_types/for-sale']->id,
            'location' => $this->terms['locations/kemang']->id,
        ]);
        $this->translate('properties/villa-samudra', 'Vila Samudra', 'vila-samudra', ['summary' => 'Vila lima kamar di atas sawah Canggu.', 'description' => '<p>Dirancang oleh studio Bali.</p>', 'features' => [['label' => 'Kolam infinity']], 'address' => 'Jalan Pantai Berawa, Canggu']);

        $testimonial = $this->addEntry('testimonials', 'Sarah & Tom', ['quote' => 'They found us a home we didn’t know existed.', 'client' => 'Sarah & Tom Whitfield', 'client_role' => 'Bought in Canggu', 'rating' => 5]);

        foreach (range(1, 7) as $n) {
            $this->addEntry('journal', "Market report {$n}", [
                'excerpt' => "What moved Bali’s market, part {$n}.", 'body' => '<p>Prices held steady.</p>',
                'author_agent' => $ayu->entry_id, 'topics' => [$this->terms['topics/market-reports']->id],
                'related' => $n === 7 ? [$this->entries['properties/villa-samudra']->entry_id] : [],
            ], publishedAt: now()->subDays(10 - $n));
        }

        $home = $this->addEntry('pages', 'Home', [
            'intro' => 'Homes in Jakarta and Bali.',
            'sections' => [
                ['id' => 'b1', 'type' => 'hero', 'key' => null, 'hidden' => false, 'values' => ['heading' => 'Find the home that feels like yours', 'subheading' => 'Villas, houses and apartments in Jakarta and Bali.', 'button' => ['type' => 'url', 'url' => '/properties', 'label' => 'View properties', 'new_tab' => false]]],
                ['id' => 'b2', 'type' => 'stats', 'key' => null, 'hidden' => false, 'values' => ['items' => [['value' => '20', 'label' => 'Years'], ['value' => '450+', 'label' => 'Homes sold']]]],
                ['id' => 'b3', 'type' => 'featured_properties', 'key' => null, 'hidden' => false, 'values' => ['heading' => 'Featured homes', 'properties' => []]],
                ['id' => 'b4', 'type' => 'team', 'key' => null, 'hidden' => false, 'values' => ['heading' => 'Meet the team', 'agents' => [$ayu->entry_id, $budi->entry_id]]],
                ['id' => 'b5', 'type' => 'testimonials', 'key' => null, 'hidden' => false, 'values' => ['heading' => 'Kind words', 'testimonials' => [$testimonial->entry_id]]],
                ['id' => 'b6', 'type' => 'image_text', 'key' => null, 'hidden' => false, 'values' => ['heading' => 'Our approach', 'body' => '<p>Calm and thorough.</p>', 'image_side' => 'right']],
                ['id' => 'b7', 'type' => 'faq', 'key' => 'faq', 'hidden' => false, 'values' => ['items' => [['question' => 'Can foreigners buy property in Indonesia?', 'answer' => '<p>Through a leasehold or a PT PMA.</p>']]]],
                ['id' => 'b8', 'type' => 'cta', 'key' => null, 'hidden' => false, 'values' => ['heading' => 'Thinking of selling?', 'text' => 'Ask for a free valuation.', 'button' => ['type' => 'url', 'url' => '/sell-with-us', 'label' => 'Get a valuation', 'new_tab' => false]]],
                ['id' => 'b9', 'type' => 'rich_text', 'key' => null, 'hidden' => false, 'values' => ['body' => '<p>Licensed agents since 2006.</p>']],
            ],
        ]);
        $this->translate('pages/home', 'Beranda', 'beranda', ['intro' => 'Rumah di Jakarta dan Bali.', 'sections' => [
            ['id' => 'b1', 'type' => 'hero', 'key' => null, 'hidden' => false, 'values' => ['heading' => 'Temukan rumah yang terasa milik Anda']],
        ]]);
        $about = $this->addEntry('pages', 'About', ['intro' => 'Who we are.']);
        $this->addEntry('pages', 'Our story', ['intro' => 'Since 2006.'], parent: $about->entry_id);
        $contact = $this->addEntry('pages', 'Contact', ['intro' => 'Talk to us.']);
        $sell = $this->addEntry('pages', 'Sell with us', ['intro' => 'A confidential valuation.']);
        app(SaveEntrySettings::class)->handle($contact->entry, ['template' => 'cms.contact']);
        app(SaveEntrySettings::class)->handle($sell->entry, ['template' => 'cms.sell']);

        // SaveSettings, not $settings->save(): only the action announces the change (SettingsSaved), which
        // makes the CMS forget the homepage it read.
        app(SaveSettings::class)->handle(app(SiteSettings::class), ['homepage' => $home->entry_id, 'name' => 'Lumina Estates']);

        $this->globals();
        $this->menus();
    }

    private function globals(): void
    {
        $company = GlobalSet::query()->where('handle', 'company')->first();
        if ($company !== null) {
            app(SaveGlobals::class)->handle($company, ['legal_name' => 'PT Lumina Estates Indonesia', 'tagline' => 'Homes with light', 'phone' => '+62 21 555 0100', 'whatsapp' => '+62 811 555 0100', 'email' => 'hello@lumina-estates.test', 'address' => "Jl. Kemang Raya 8\nJakarta 12730", 'office_hours' => 'Mon–Sat 9:00–18:00'], 'en');
            app(SaveGlobals::class)->handle($company, ['tagline' => 'Rumah penuh cahaya', 'office_hours' => 'Sen–Sab 9.00–18.00'], 'id');
        }
        $footer = GlobalSet::query()->where('handle', 'footer')->first();
        if ($footer !== null) {
            app(SaveGlobals::class)->handle($footer, ['about' => 'A premium residential agency.', 'newsletter_heading' => 'Market notes, monthly'], 'en');
            app(SaveGlobals::class)->handle($footer, ['about' => 'Agen properti premium.', 'newsletter_heading' => 'Catatan pasar, bulanan'], 'id');
        }
        $social = GlobalSet::query()->where('handle', 'social')->first();
        if ($social !== null) {
            app(SaveGlobals::class)->handle($social, ['links' => [['network' => 'instagram', 'url' => ['type' => 'url', 'url' => 'https://instagram.com/lumina', 'label' => null, 'new_tab' => true]]]], 'en');
        }
    }

    private function menus(): void
    {
        $properties = Collection::query()->where('handle', 'properties')->sole();
        $journal = Collection::query()->where('handle', 'journal')->sole();
        if (($main = Menu::query()->where('handle', 'main')->first()) !== null) {
            app(SaveMenu::class)->handle($main, [
                ['collection_id' => $properties->id, 'labels' => ['en' => 'Properties', 'id' => 'Properti'], 'children' => [
                    ['url' => '/properties?listing_type=for-sale', 'labels' => ['en' => 'For sale', 'id' => 'Dijual']],
                    ['term_id' => $this->terms['property_types/villa']->id, 'labels' => ['en' => 'Villas', 'id' => 'Vila']],
                ]],
                ['collection_id' => $journal->id, 'labels' => ['en' => 'Journal', 'id' => 'Jurnal']],
                ['entry_id' => $this->entries['pages/about']->entry_id],
                ['entry_id' => $this->entries['pages/contact']->entry_id],
            ]);
        }
        if (($legal = Menu::query()->where('handle', 'legal')->first()) !== null) {
            app(SaveMenu::class)->handle($legal, [['url' => '/privacy-policy', 'labels' => ['en' => 'Privacy', 'id' => 'Privasi']]]);
        }
    }

    private function addTerm(string $taxonomy, string $title, ?string $slug = null, ?Term $parent = null): Term
    {
        $model = Taxonomy::query()->where('handle', $taxonomy)->sole();
        $term = app(SaveTerm::class)->handle($model, ['title' => $title, 'slug' => $slug, 'locale' => 'en', 'parent_id' => $parent?->id]);

        return $this->terms[$taxonomy.'/'.$term->translation('en')?->slug] = $term;
    }

    private function translateTerm(string $key, string $title, string $slug): void
    {
        $term = $this->terms[$key];
        app(SaveTerm::class)->handle($term->taxonomy, ['title' => $title, 'slug' => $slug, 'locale' => 'id'], $term);
    }

    /** @param  array<string, mixed>  $data */
    private function addEntry(string $collection, string $title, array $data, ?int $parent = null, mixed $publishedAt = null): EntryTranslation
    {
        $model = Collection::query()->where('handle', $collection)->sole();
        $translation = app(CreateEntry::class)->handle($model, ['title' => $title, 'data' => $data]);
        if ($parent !== null) {
            app(SaveEntrySettings::class)->handle($translation->entry, ['parent_id' => $parent]);
        }
        $translation = app(PublishEntry::class)->handle($translation, $publishedAt);

        return $this->entries[$collection.'/'.$translation->slug] = $translation->fresh();
    }

    /** @param  array<string, mixed>  $data */
    private function translate(string $key, string $title, string $slug, array $data): EntryTranslation
    {
        $translation = app(CreateTranslation::class)->handle($this->entries[$key]->entry, 'id');
        $translation = app(SaveDraft::class)->handle($translation, ['title' => $title, 'slug' => $slug, 'data' => $data, 'version' => $translation->version]);

        return $this->entries[$key.'@id'] = app(PublishEntry::class)->handle($translation)->fresh();
    }
}
