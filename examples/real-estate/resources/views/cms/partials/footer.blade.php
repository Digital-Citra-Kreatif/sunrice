{{--
    The site footer: globals "company" (contact details), "social" (links), "footer" (about, legal, newsletter
    heading), the menus "footer" and "legal", and the "newsletter" form (own markup).
--}}
@php
    $footer = cms_global('footer');
    $social = cms_global('social');
    $links = collect($social['links'] ?? [])->filter(fn ($row) => filled($row['url']['url'] ?? null));
    $footerMenu = cms_menu('footer');
    $legalMenu = cms_menu('legal');
@endphp
<footer class="mt-auto bg-teal-900 text-white/80" aria-labelledby="footer-title">
    <h2 id="footer-title" class="visually-hidden">{{ __('site.footer.title') }}</h2>

    <div class="container-site grid gap-12 py-16 lg:grid-cols-12 lg:gap-10 lg:py-20">
        <div class="lg:col-span-4">
            <a href="{{ $home }}" class="inline-block text-white">
                @include('cms.partials.logo', ['id' => 'logo-footer', 'label' => $siteName, 'class' => 'h-11 w-auto'])
            </a>
            @if (filled($company['tagline'] ?? null))
                <p class="mt-6 font-serif text-2xl leading-snug text-white">{{ $company['tagline'] }}</p>
            @endif
            @if (filled($footer['about'] ?? null))
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-white/70">{{ $footer['about'] }}</p>
            @endif
            @if ($links->isNotEmpty())
                <ul class="mt-6 flex gap-2" aria-label="{{ __('site.footer.social') }}">
                    @foreach ($links as $row)
                        @php($network = is_string($row['network'] ?? null) ? strtolower($row['network']) : 'globe')
                        <li>
                            <a href="{{ $row['url']['url'] }}" target="_blank" rel="noopener me" class="grid size-11 place-items-center rounded-full border border-white/20 text-white transition hover:-translate-y-0.5 hover:border-white hover:bg-white hover:text-teal-900">
                                @include('cms.partials.icon', ['name' => in_array($network, ['instagram', 'linkedin', 'facebook', 'youtube', 'tiktok', 'whatsapp'], true) ? $network : 'globe', 'class' => 'size-5'])
                                <span class="visually-hidden">{{ $row['url']['label'] ?: ucfirst($network) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="grid gap-10 sm:grid-cols-2 lg:col-span-4">
            @if ($footerMenu !== [])
                <nav aria-label="{{ __('site.nav.footer') }}">
                    <p class="eyebrow !text-teal-200">{{ __('site.footer.explore') }}</p>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        @foreach ($footerMenu as $item)
                            <li><a href="{{ $item['url'] }}" class="transition hover:text-white" @if ($item['active']) aria-current="page" @endif @if ($item['new_tab']) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
            @if (collect(['address', 'phone', 'email', 'office_hours'])->contains(fn ($key) => filled($company[$key] ?? null)))
            <div>
                <p class="eyebrow !text-teal-200">{{ __('site.footer.visit') }}</p>
                <address class="mt-4 space-y-2.5 text-sm not-italic">
                    @if (filled($company['address'] ?? null))
                        <p class="whitespace-pre-line">{{ $company['address'] }}</p>
                    @endif
                    @if (filled($company['phone'] ?? null))
                        <p><a href="tel:{{ preg_replace('/[^0-9+]/', '', $company['phone']) }}" class="transition hover:text-white">{{ $company['phone'] }}</a></p>
                    @endif
                    @if (filled($company['email'] ?? null))
                        <p><a href="mailto:{{ $company['email'] }}" class="transition hover:text-white">{{ $company['email'] }}</a></p>
                    @endif
                    @if (filled($company['office_hours'] ?? null))
                        <p class="whitespace-pre-line text-white/60">{{ $company['office_hours'] }}</p>
                    @endif
                </address>
            </div>
            @endif
        </div>

        <div class="lg:col-span-4">
            @include('cms.partials.newsletter', ['heading' => $footer['newsletter_heading'] ?? null])
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="container-site flex flex-col gap-4 py-6 text-xs text-white/55 md:flex-row md:items-center md:justify-between">
            <p>&copy; {{ now()->year }} {{ filled($company['legal_name'] ?? null) ? $company['legal_name'] : $siteName }}</p>
            @if (filled($footer['legal'] ?? null))
                <div class="prose prose-invert max-w-2xl text-xs leading-relaxed [&_*]:!text-xs [&_p]:!m-0">{!! $footer['legal'] !!}</div>
            @endif
            @if ($legalMenu !== [])
                <nav aria-label="{{ __('site.nav.legal') }}">
                    <ul class="flex flex-wrap gap-x-6 gap-y-2">
                        @foreach ($legalMenu as $item)
                            <li><a href="{{ $item['url'] }}" class="transition hover:text-white" @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </div>
</footer>
