{{--
    The Sell with us page (Page settings → Template: cms.sell): the page's sections, then the "valuation" form,
    printed by the CMS from the form's blueprint (no slot: labels, help texts, file input and errors come from
    the form itself).
--}}
@extends('cms.pages')

@section('after-sections')
    <section class="section bg-sand-50" id="valuation">
        <div class="container-site grid gap-10 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <p class="eyebrow">{{ __('site.sell.eyebrow') }}</p>
                <h2 class="h2 mt-3">{{ __('site.sell.title') }}</h2>
                <p class="lead mt-5">{{ __('site.sell.text') }}</p>
            </div>
            <div class="rounded-[var(--radius-card)] bg-paper p-6 shadow-[var(--shadow-soft)] sm:p-10 lg:col-span-8">
                <x-sunrice-cms::form handle="valuation" class="form-auto" :submit="__('site.sell.submit')" />
            </div>
        </div>
    </section>
@endsection
