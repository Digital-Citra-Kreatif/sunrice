<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><title>{{ $saidHeadline }}</title></head>
<body style="margin:0;padding:24px;background:#f6f6f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1a1a1a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;">
    <tr><td style="padding:28px;">
        <p style="margin:0 0 4px;font-size:13px;color:#b42318;font-weight:600;">{{ __('Security alert') }} · {{ $brand }}</p>
        <h1 style="margin:0 0 16px;font-size:20px;">{{ $saidHeadline }}</h1>
        <p style="margin:0 0 16px;line-height:1.5;">{{ $saidWhat }}</p>
        <p style="margin:0 0 16px;line-height:1.5;"><strong>{{ __('What to do:') }}</strong> {{ $saidAdvice }}</p>
        @if ($saidDetails !== [])
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px;font-size:14px;">
                @foreach ($saidDetails as $label => $value)
                    <tr><td style="padding:2px 16px 2px 0;color:#666;">{{ $label }}</td><td style="padding:2px 0;">{{ $value }}</td></tr>
                @endforeach
            </table>
        @endif
        <p style="margin:0;"><a href="{{ $logUrl }}" style="color:#1a1a1a;">{{ __('See the security events in the activity log') }}</a></p>
    </td></tr>
</table>
<p style="max-width:560px;margin:12px auto 0;font-size:12px;color:#888;">{{ __('You get this because you look after the security of :brand.', ['brand' => $brand]) }}</p>
</body>
</html>
