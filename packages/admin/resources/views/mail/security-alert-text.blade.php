{{ __('Security alert') }} · {{ $brand }}

{{ $saidHeadline }}

{{ $saidWhat }}

{{ __('What to do:') }} {{ $saidAdvice }}

@foreach ($saidDetails as $label => $value)
{{ $label }}: {{ $value }}
@endforeach

{{ __('The security events in the activity log:') }} {{ $logUrl }}
