{{-- The top of the panel's markdown emails: Panel::mailBrand()'s logo (an image, never an SVG) or its name. --}}
@php($brand = app(\Sunrice\Admin\Panel::class)->mailBrand())
<tr>
<td class="header">
<a href="{{ config('app.url') }}" style="display: inline-block;">
@if ($brand->logo !== null)
<img src="{{ $brand->logo }}" alt="{{ $brand->name }}" width="{{ $brand->width }}" height="{{ $brand->height }}" style="display: block; border: 0; outline: none; text-decoration: none; width: {{ $brand->width }}px; height: {{ $brand->height }}px; max-width: 100%; max-height: {{ \Sunrice\Admin\Mail\MailBrand::HEIGHT }}px;">
@else
{{ $brand->name }}
@endif
</a>
</td>
</tr>
