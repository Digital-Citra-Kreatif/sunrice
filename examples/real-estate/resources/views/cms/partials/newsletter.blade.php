{{--
    The "newsletter" form with our own markup (the component adds <form>, CSRF, honeypot, locale). After sending,
    `replace` swaps the form for the form's thank-you message (set in Structure → Forms).
--}}
<div>
    <p class="eyebrow !text-teal-200">{{ __('site.newsletter.eyebrow') }}</p>
    @if (filled($heading))
        <p class="mt-4 font-serif text-2xl leading-snug text-white">{{ $heading }}</p>
    @endif
    <x-sunrice-cms::form handle="newsletter" replace class="mt-5">
        {{-- Visitors land on the notice after a failed send; the email's own error shows under it. --}}
        @include('cms.partials.form-errors', ['form' => $component, 'class' => 'mb-3'])
        <div class="flex flex-col gap-2 sm:flex-row">
            <label for="newsletter-email" class="visually-hidden">{{ __('site.newsletter.email') }}</label>
            <input id="newsletter-email" type="email" name="data[email]" required autocomplete="email"
                value="{{ $component->old('email') }}" placeholder="{{ __('site.newsletter.placeholder') }}"
                class="input !border-white/20 !bg-white/10 !text-white placeholder:!text-white/50 focus:!border-white"
                @if ($component->error('email')) aria-invalid="true" aria-describedby="newsletter-email-error" @endif>
            <button type="submit" class="btn btn-light shrink-0">{{ __('site.newsletter.submit') }}</button>
        </div>
        @if ($message = $component->error('email'))
            <p id="newsletter-email-error" role="alert" class="mt-2 !text-[#f3c9bf]">{{ $message }}</p>
        @endif
        <p class="mt-3 text-xs text-white/50">{{ __('site.newsletter.note') }}</p>
    </x-sunrice-cms::form>
</div>
