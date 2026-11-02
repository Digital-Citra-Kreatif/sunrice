{{--
    The top of an HTML email of the panel's or a plugin's own (not a markdown one): Panel::mailBrand()'s logo,
    with the name as its alternative text, or the name. Inline styles only, for email clients.

        <x-sunrice-admin::mail-brand />                    the logo, else the name
        <x-sunrice-admin::mail-brand :fallback="false" />  the logo, else nothing (the email names the brand itself)

    @api
--}}
@props(['fallback' => true])
@php($brand = app(\Sunrice\Admin\Panel::class)->mailBrand())
@if ($brand->logo !== null)
<div style="margin: 0 0 20px;"><img src="{{ $brand->logo }}" alt="{{ $brand->name }}" width="{{ $brand->width }}" height="{{ $brand->height }}" style="display: block; border: 0; outline: none; text-decoration: none; width: {{ $brand->width }}px; height: {{ $brand->height }}px; max-width: 100%;"></div>
@elseif ($fallback)
<div style="margin: 0 0 20px; font-size: 19px; font-weight: bold; color: #111;">{{ $brand->name }}</div>
@endif
