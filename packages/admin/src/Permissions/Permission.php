<?php

declare(strict_types=1);

namespace Sunrice\Admin\Permissions;

use InvalidArgumentException;
use Sunrice\Admin\Support\Say;

/**
 * A permission roles can be given, shown in the role editor under its
 * group. Names are dotted and lowercase: "shop.orders.refund".
 *
 * The label and group are English, said in the user's language in the
 * role editor. A label with a name in it uses a placeholder, so the
 * sentence can be translated once: Permission::make("shop.{$handle}.orders",
 * 'Orders of :shop', 'Shops', ['shop' => $name]). The group takes the same
 * placeholders: Permission::make("shop.{$handle}.refund", 'Refund',
 * 'Shop: :shop', ['shop' => $name]).
 *
 * A permission whose action isn't one of the role editor's columns
 * (View, Create, Edit, Publish, Moderate, Delete, Export) shows as a
 * checkbox with its label; give a long label a short one for it, the
 * full label staying its description: ->short("Refund").
 *
 * @api
 */
final readonly class Permission
{
    /**
     * @param  string  $label  in English, with the replacements filled in
     * @param  string  $group  in English, with the replacements filled in
     * @param  string  $words  the label as written, with its placeholders
     * @param  string  $groupWords  the group as written, with its placeholders
     * @param  array<string, string>  $replace  the placeholders' values
     */
    private function __construct(
        public string $name,
        public string $label,
        public string $group,
        private string $words,
        private string $groupWords,
        private array $replace,
        public ?string $short = null,
    ) {}

    /** @param  array<string, string>  $replace  values for the label's and the group's placeholders (":shop") */
    public static function make(string $name, string $label, string $group = 'Other', array $replace = []): self
    {
        if (preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*(?:\.[a-z0-9]+(?:[-_][a-z0-9]+)*)+$/', $name) !== 1) {
            throw new InvalidArgumentException("Permission \"{$name}\": use lowercase dotted names like \"shop.orders.refund\".");
        }
        if (trim($label) === '') {
            throw new InvalidArgumentException("Permission \"{$name}\" needs a label.");
        }

        $fill = function (string $words) use ($replace): string {
            foreach ($replace as $key => $value) {
                $words = str_replace(':'.$key, $value, $words);
            }

            return $words;
        };

        return new self($name, $fill($label), $fill($group), $label, $group, $replace);
    }

    /**
     * The same permission with a short label (English, a word or two) for the
     * role editor, where the full label is its description.
     */
    public function short(string $short): self
    {
        if (trim($short) === '') {
            throw new InvalidArgumentException("Permission \"{$this->name}\": a short label can't be empty.");
        }

        return new self($this->name, $this->label, $this->group, $this->words, $this->groupWords, $this->replace, $short);
    }

    /** The label in the signed-in user's language; names in it are said too. */
    public function saidLabel(): string
    {
        $said = __($this->words, array_map(fn (string $value): string => Say::it($value), $this->replace));

        return is_string($said) ? $said : $this->label;
    }

    /**
     * The group in the signed-in user's language ("Entries: :collection" →
     * "Entri: Pages"); the names in it are data (a collection's title), as they are.
     */
    public function saidGroup(): string
    {
        $said = __($this->groupWords, $this->replace);

        return is_string($said) ? $said : $this->group;
    }

    /** The part before the first dot: the owner's prefix. */
    public function prefix(): string
    {
        return explode('.', $this->name, 2)[0];
    }
}
