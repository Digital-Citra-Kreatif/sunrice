{{--
    One input inside an <x-sunrice-cms::form> slot, with its label (lang/{locale}/site.php: forms.fields.{handle}),
    old input and error from the form component. Select options come from the form's blueprint; their labels
    from forms.options.{handle}.{value} when translated.
    @include('cms.partials.form-field', ['form' => $component, 'handle' => 'email', 'type' => 'email', 'required' => true])
--}}
@php
    $inputId = 'form-'.$form->form->handle.'-'.$handle;
    $error = $form->error($handle);
    $value = $form->old($handle, $default ?? null);
    $type ??= 'text';
    $labelKey = 'site.forms.fields.'.$handle;
    $label = $label ?? (__($labelKey) !== $labelKey ? __($labelKey) : \Illuminate\Support\Str::headline($handle));
    $field = in_array($type, ['select', 'date'], true) ? collect($form->form->schema()->fields())->firstWhere('handle', $handle) : null;
    // A date field set to "future only" in the blueprint: the browser offers today onwards.
    $min = $type === 'date' && ($field?->config['future'] ?? false) === true ? now()->toDateString() : null;
@endphp
<div class="field {{ $class ?? '' }}">
    <label for="{{ $inputId }}">{{ $label }}@if ($required ?? false) <span aria-hidden="true" class="text-teal-700">*</span>@endif</label>
    @if ($type === 'textarea')
        <textarea id="{{ $inputId }}" name="data[{{ $handle }}]" rows="{{ $rows ?? 5 }}" @required($required ?? false) @if ($error) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif>{{ is_scalar($value) ? $value : '' }}</textarea>
    @elseif ($type === 'select')
        <select id="{{ $inputId }}" name="data[{{ $handle }}]" @required($required ?? false) @if ($error) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif>
            <option value="">{{ __('site.forms.choose') }}</option>
            @foreach (($field?->config['options'] ?? []) as $option)
                @php($optionKey = 'site.forms.options.'.$handle.'.'.$option['value'])
                <option value="{{ $option['value'] }}" @selected((string) $value === (string) $option['value'])>{{ __($optionKey) !== $optionKey ? __($optionKey) : ($option['label'] ?: $option['value']) }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $inputId }}" type="{{ $type }}" name="data[{{ $handle }}]" value="{{ is_scalar($value) ? $value : '' }}" @required($required ?? false)
            @if ($autocomplete ?? null) autocomplete="{{ $autocomplete }}" @endif
            @if ($min) min="{{ $min }}" @endif
            @if ($error) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif>
    @endif
    @if ($error)
        <p id="{{ $inputId }}-error" role="alert" class="field-error">{{ $error }}</p>
    @endif
</div>
