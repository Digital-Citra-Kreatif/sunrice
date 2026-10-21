{{--
    The notice a form slot shows after a failed send, where visitors land: its id is the form's "-errors" one with
    tabindex="-1", so the browser focuses it as it goes to #form-{handle}-errors (and the CMS prints no summary of its
    own). Says the form wasn't sent when the send limit stopped it, with the limit's or the captcha's message (the
    fields' own errors show under them).
    @include('cms.partials.form-errors', ['form' => $component, 'class' => 'sm:col-span-2'])
--}}
@if ($form->failed())
    <div id="{{ $form->id }}-errors" role="alert" tabindex="-1" class="form-status !bg-[#f8ebe7] !text-sold {{ $class ?? '' }}">
        <p>{{ $form->limited() ? __('sunrice-cms::site.forms.limited') : __('site.forms.failed') }}</p>
        @foreach ($form->errorSummary() as [$errorField, $message])
            @if ($errorField === null)
                <p class="mt-1">{{ $message }}</p>
            @endif
        @endforeach
    </div>
@endif
