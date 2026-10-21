{{--
    The page blueprint's flexible "sections": each block renders with cms/blocks/{type}.blade.php (the fieldset's
    handle, underscores or dashes). A block type without a view renders nothing on the live site (and a note in
    previews), so adding a block type in the CMS never breaks a page. See README → "Adding a block type".
    @include('cms.partials.blocks', ['blocks' => $values['sections']])
--}}
@foreach ($blocks as $block)
    @php($view = 'cms.blocks.'.str_replace('_', '-', $block->type))
    @if (view()->exists($view))
        @include($view, ['block' => $block, 'first' => $loop->first, 'blockId' => $block->key ?: 'section-'.$loop->iteration])
    @elseif (cms_page()?->preview || cms_page()?->draft)
        <div class="container-site py-6"><p class="rounded-lg border border-dashed border-sand-300 p-4 text-sm text-sand-600">{{ __('site.blocks.missing', ['type' => $block->type]) }}</p></div>
    @endif
@endforeach
