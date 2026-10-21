<?php

declare(strict_types=1);

/*
 * The site's interface words (labels, buttons, headings of fixed sections) in English. Content (pages,
 * properties, menus, contact details…) lives in the CMS; the CMS's own words are in sunrice-cms::site.
 * Keep lang/id/site.php in step: tests/Feature/Site/TranslationsTest.php checks both have the same keys.
 */
return [
    'skip_to_content' => 'Skip to content',
    'home' => 'Home',
    'breadcrumbs' => 'Breadcrumbs',
    'read_more' => 'Read more',
    'empty' => 'Nothing here yet.',
    'results' => 'Results',   // the (visually hidden) heading above a list of cards, so headings don't jump from h1 to h3
    'fallback' => 'This page isn’t available in English yet, so it’s shown in its original language.',
    'rating' => 'Rated :rating out of 5',

    'nav' => [
        'main' => 'Main',
        'footer' => 'Footer',
        'legal' => 'Legal',
        'languages' => 'Languages',
        'toggle' => 'Menu',
    ],

    'collections' => [
        'properties' => 'Properties',
        'agents' => 'Our team',
        'neighbourhoods' => 'Neighbourhoods',
        'journal' => 'Journal',
        'pages' => 'Pages',
    ],

    'taxonomies' => [
        'property-types' => 'Property type',
        'locations' => 'Location',
        'topics' => 'Journal topic',
    ],

    'number' => ['thousands' => ',', 'decimal' => '.'],

    'price' => [
        'format' => 'IDR :amount',
        'month' => '/ month',
        'year' => '/ year',
        'on_request' => 'Price on request',
        'short' => [
            'million' => 'IDR :amount million',
            'billion' => 'IDR :amount billion',
            'trillion' => 'IDR :amount trillion',
        ],
    ],

    'status' => [
        'available' => 'Available',
        'under_offer' => 'Under offer',
        'sold' => 'Sold',
        'rented' => 'Rented',
    ],

    'facts' => [
        'bedrooms' => 'Bedrooms',
        'bathrooms' => 'Bathrooms',
        'building_area' => 'Building',
        'land_area' => 'Land',
        'year_built' => 'Year built',
        'property_type' => 'Type',
        'sqm' => ':value m²',
        'bedrooms_count' => ':count bed|:count beds',
        'bathrooms_count' => ':count bath|:count baths',
    ],

    'property' => [
        'price' => 'Asking price',
        'price_rent' => 'Rent',
        'price_sold' => 'Last asking price',
        'price_rented' => 'Last asking rent',
        'gone_sold' => 'This home has been sold',
        'gone_rented' => 'This home has been let',
        'gone_text' => 'Tell us what you’re looking for and we’ll let you know when something similar comes up.',
        'gone_link' => 'See the homes available now',
        'facts' => 'Key facts',
        'features' => 'Features',
        'amenities' => 'Amenities',
        'floor_plan' => 'Floor plan',
        'location' => 'Location',
        'map' => 'View on map',
        'video' => 'Watch the video',
        'agent' => 'Your agent',
        'enquire' => 'Arrange a viewing',
        'enquire_text' => 'Tell us when suits you and we’ll be in touch within one working day.',
        'message_default' => 'I’d like to know more about :title.',
        'send' => 'Send enquiry',
        'neighbourhood' => 'Neighbourhood guide',
        'nearby' => 'More properties nearby',
    ],

    'gallery' => [
        'open' => 'Open photo :number of :total',
        'count' => ':count photo|:count photos',
        'title' => 'Photos of :title',
        'close' => 'Close',
        'previous' => 'Previous photo',
        'next' => 'Next photo',
    ],

    'filter' => [
        'label' => 'Find a property',
        'search' => 'Keywords',
        'search_placeholder' => 'Pool, ocean view, Menteng…',
        'listing' => 'Buy or rent',
        'type' => 'Property type',
        'location' => 'Location',
        'bedrooms' => 'Bedrooms',
        'bedrooms_min' => 'Beds',
        'price' => 'Price',
        'price_max' => 'Max. price',
        'up_to' => 'Up to :amount',
        'any' => 'Any',
        'submit' => 'Search',
        'results' => ':count property|:count properties',
        'applied' => 'Applied filters',
        'remove' => 'Remove filter :filter: :value',
        'clear' => 'Clear all',
        'empty_title' => 'No properties match',
        'empty_text' => 'Try fewer filters, or ask us: many of our homes are offered privately.',
    ],

    'sort' => [
        'label' => 'Sort by',
        'newest' => 'Newest',
        'price_asc' => 'Price, low to high',
        'price_desc' => 'Price, high to low',
        'size' => 'Largest first',
    ],

    'pagination' => [
        'label' => 'Pages',
        'previous' => 'Previous page',
        'next' => 'Next page',
        'page' => 'Page :page',
    ],

    'blocks' => [
        'missing' => 'The block “:type” has no template yet (resources/views/cms/blocks). Visitors don’t see it.',
        'featured_eyebrow' => 'Selected properties',
        'all_properties' => 'View all properties',
        'team_eyebrow' => 'Our people',
        'testimonials_eyebrow' => 'Client stories',
        'faq_eyebrow' => 'Questions',
        'faq_title' => 'Frequently asked questions',
    ],

    'pages' => [
        'children' => 'More in :title',
    ],

    'team' => [
        'eyebrow' => 'The people behind Lumina',
    ],

    'agent' => [
        'email' => 'Email',
        'languages' => 'Speaks',
        'listings' => 'Properties with :name',
    ],

    'languages' => [
        'english' => 'English',
        'indonesian' => 'Indonesian',
        'mandarin' => 'Mandarin',
        'dutch' => 'Dutch',
    ],

    'neighbourhoods' => [
        'eyebrow' => 'Where to live',
        'guide' => 'Neighbourhood guide',
        'average_price' => 'Average price',
        'listings' => 'Properties in :name',
    ],

    'locations' => [
        'within' => 'Places in :place',
    ],

    'journal' => [
        'eyebrow' => 'Insights & stories',
        'topics' => 'Topics',
        'all' => 'All',
        'related' => 'Properties in this story',
        'more' => 'More from the journal',
    ],

    'contact' => [
        'address' => 'Office',
        'phone' => 'Phone',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'office_hours' => 'Opening hours',
        'directions' => 'Get directions',
        'primary' => 'Your contact',
        'form_title' => 'Send us a message',
    ],

    'sell' => [
        'eyebrow' => 'Free valuation',
        'title' => 'What is your home worth?',
        'text' => 'Share a few details and photos. A senior agent will prepare a confidential valuation, usually within 48 hours.',
        'submit' => 'Request my valuation',
    ],

    'forms' => [
        'choose' => 'Choose…',
        'send' => 'Send message',
        'failed' => 'Please check the fields marked below.',
        'fields' => [
            'name' => 'Your name',
            'email' => 'Email',
            'phone' => 'Phone',
            'topic' => 'I’m interested in',
            'message' => 'Message',
            'preferred_date' => 'Preferred viewing date',
        ],
        'options' => [
            'topic' => [
                'buying' => 'Buying',
                'selling' => 'Selling',
                'renting' => 'Renting',
                'other' => 'Something else',
            ],
        ],
    ],

    'newsletter' => [
        'eyebrow' => 'Newsletter',
        'email' => 'Email address',
        'placeholder' => 'you@example.com',
        'submit' => 'Subscribe',
        'note' => 'One considered email a month. Unsubscribe at any time.',
    ],

    'footer' => [
        'title' => 'About Lumina Estates',
        'explore' => 'Explore',
        'visit' => 'Visit us',
        'social' => 'Follow us',
    ],

    'search' => [
        'eyebrow' => 'Search',
        'title' => 'Search the site',
        'title_for' => 'Results for “:query”',
        'label' => 'Search the site',
        'placeholder' => 'Villas in Canggu, market report…',
        'submit' => 'Search',
        'results' => ':count result|:count results',
        'none' => 'Nothing found. Try another word, or browse our properties.',
    ],

    'errors' => [
        'home' => 'Back to the home page',
        '404_title' => 'We can’t find that page',
        '404_text' => 'The page you’re looking for isn’t here. It may have been sold, renamed or the address mistyped.',
        '500_title' => 'Something went wrong',
        '500_text' => 'Sorry, the page couldn’t be shown. Please try again in a moment.',
        '503_title' => 'Back shortly',
        '503_text' => 'We’re making a few improvements. Please come back in a few minutes.',
    ],
];
