{{-- A property's status (select values: available, under_offer, sold, rented), in the page's language. --}}
@php($status = is_string($status ?? null) && $status !== '' ? $status : 'available')
<span class="badge badge-{{ $status }} {{ $class ?? '' }}">{{ __('site.status.'.$status) }}</span>
