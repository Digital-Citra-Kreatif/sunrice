{{--
    The Contact page (Page settings → Template: cms.contact): the page's sections, the company's details from
    the "company" global and the "contact" form with our own markup.
--}}
@extends('cms.pages')

@section('before-sections')
    @php($company = cms_global('company'))
    <section class="section-tight pt-0">
        <div class="container-site grid gap-10 lg:grid-cols-12 lg:gap-16">
            <aside class="lg:col-span-5">
                <div class="rounded-[var(--radius-card)] bg-teal-900 p-8 text-white lg:p-10">
                    <h2 class="font-serif text-3xl !text-white">{{ $company['legal_name'] ?? \Sunrice\Cms\Content::site()->name() }}</h2>
                    <dl class="mt-8 space-y-6 text-sm">
                        @foreach (['address' => 'pin', 'phone' => 'phone', 'whatsapp' => 'whatsapp', 'email' => 'mail', 'office_hours' => 'clock'] as $key => $icon)
                            @if (filled($company[$key] ?? null))
                                <div class="flex gap-4">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-white/10 text-teal-100">@include('cms.partials.icon', ['name' => $icon, 'class' => 'size-5'])</span>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-[0.14em] text-teal-200 uppercase">{{ __('site.contact.'.$key) }}</dt>
                                        <dd class="mt-1 whitespace-pre-line text-white/90">
                                            @switch ($key)
                                                @case ('phone')<a href="tel:{{ preg_replace('/[^0-9+]/', '', $company[$key]) }}" class="hover:underline">{{ $company[$key] }}</a>@break
                                                @case ('whatsapp')<a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $company[$key]) }}" class="hover:underline" target="_blank" rel="noopener">{{ $company[$key] }}</a>@break
                                                @case ('email')<a href="mailto:{{ $company[$key] }}" class="hover:underline">{{ $company[$key] }}</a>@break
                                                @default{{ $company[$key] }}
                                            @endswitch
                                        </dd>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                    @if (filled($company['map']['url'] ?? null))
                        <a href="{{ $company['map']['url'] }}" target="_blank" rel="noopener" class="btn btn-ghost-light btn-sm mt-8">@include('cms.partials.icon', ['name' => 'map']) {{ $company['map']['label'] ?: __('site.contact.directions') }}</a>
                    @endif
                    @php($person = $company['primary_contact'] ?? null)
                    @if (is_array($person) && filled($person['name'] ?? null))
                        <div class="mt-10 border-t border-white/10 pt-8">
                            <p class="text-xs font-semibold tracking-[0.14em] text-teal-200 uppercase">{{ __('site.contact.primary') }}</p>
                            <p class="mt-2 font-serif text-2xl">{{ $person['name'] }}</p>
                            @if (filled($person['role'] ?? null))<p class="text-sm text-white/70">{{ $person['role'] }}</p>@endif
                            <p class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                                @if (filled($person['phone'] ?? null))<a href="tel:{{ preg_replace('/[^0-9+]/', '', $person['phone']) }}" class="hover:underline">{{ $person['phone'] }}</a>@endif
                                @if (filled($person['email'] ?? null))<a href="mailto:{{ $person['email'] }}" class="hover:underline">{{ $person['email'] }}</a>@endif
                            </p>
                        </div>
                    @endif
                </div>
            </aside>
            <div class="lg:col-span-7">
                <h2 class="h3">{{ __('site.contact.form_title') }}</h2>
                <x-sunrice-cms::form handle="contact" class="mt-6 grid gap-5 sm:grid-cols-2" replace>
                    @include('cms.partials.form-errors', ['form' => $component, 'class' => 'sm:col-span-2'])
                    @include('cms.partials.form-field', ['form' => $component, 'handle' => 'name', 'required' => true, 'autocomplete' => 'name'])
                    @include('cms.partials.form-field', ['form' => $component, 'handle' => 'email', 'type' => 'email', 'required' => true, 'autocomplete' => 'email'])
                    @include('cms.partials.form-field', ['form' => $component, 'handle' => 'phone', 'type' => 'tel', 'autocomplete' => 'tel'])
                    @include('cms.partials.form-field', ['form' => $component, 'handle' => 'topic', 'type' => 'select'])
                    @include('cms.partials.form-field', ['form' => $component, 'handle' => 'message', 'type' => 'textarea', 'required' => true, 'class' => 'sm:col-span-2'])
                    {{ $component->captcha() }}
                    <div class="sm:col-span-2">
                        <button type="submit" class="btn btn-primary">{{ __('site.forms.send') }} @include('cms.partials.icon', ['name' => 'arrow-right', 'class' => 'arrow'])</button>
                    </div>
                </x-sunrice-cms::form>
            </div>
        </div>
    </section>
@endsection
