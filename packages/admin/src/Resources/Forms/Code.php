<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use InvalidArgumentException;

/**
 * Code in the panel's code editor (CodeMirror): line numbers, highlighting
 * for its language, bracket matching and indentation. The value is the
 * text as typed, a string: escape it wherever you show it, and print it
 * unescaped only where its authors may run code on those pages.
 *
 *     Code::make('snippet')->language('html')->lines(6, 24)->maxLength(20_000);
 *
 * @api
 */
final class Code extends Field
{
    /** What the editor highlights. */
    public const LANGUAGES = ['html', 'css', 'javascript', 'json', 'plain'];

    private string $language = 'plain';

    private int $minLines = 4;

    private int $maxLines = 20;

    private int $maxLength = 65535;

    public function type(): string
    {
        return 'code';
    }

    /** html, css, javascript, json or plain (the default). */
    public function language(string $language): self
    {
        if (! in_array($language, self::LANGUAGES, true)) {
            throw new InvalidArgumentException("The code field \"{$this->name}\" has the language \"{$language}\": use one of ".implode(', ', self::LANGUAGES).'.');
        }
        $this->language = $language;

        return $this;
    }

    /** The editor shows `$min` lines when empty and grows to `$max`, then scrolls. */
    public function lines(int $min, ?int $max = null): self
    {
        $this->minLines = max(1, $min);
        $this->maxLines = max($this->minLines, $max ?? $this->maxLines);

        return $this;
    }

    public function maxLength(int $length): self
    {
        $this->maxLength = max(1, $length);

        return $this;
    }

    protected function typeRules(): array
    {
        return ['string', "max:{$this->maxLength}"];
    }

    protected function props(): array
    {
        return ['language' => $this->language, 'minLines' => $this->minLines, 'maxLines' => $this->maxLines, 'maxLength' => $this->maxLength];
    }
}
