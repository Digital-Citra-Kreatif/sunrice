Security alert · {{ $brand }}

{{ $headline }}

{{ $what }}

What to do: {{ $advice }}

@foreach ($details as $label => $value)
{{ $label }}: {{ $value }}
@endforeach

The security events in the activity log: {{ $logUrl }}
