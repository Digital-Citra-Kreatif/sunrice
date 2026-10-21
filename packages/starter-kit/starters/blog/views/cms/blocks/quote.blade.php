{{--
    The "quote" block (fieldset "quote"): a view of its own, cms/blocks/{fieldset}.blade.php,
    instead of the plain list of its fields. It gets $block: $block->text, $block->author.
--}}
<figure class="block-quote">
    <blockquote><p>{{ $block->text }}</p></blockquote>
    @if (is_string($block->author) && $block->author !== '')
        <figcaption>{{ $block->author }}</figcaption>
    @endif
</figure>
