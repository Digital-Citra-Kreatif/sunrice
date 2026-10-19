<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Closure;
use Sunrice\Admin\Contracts\Plugin;
use Sunrice\Admin\Panel;

/** A configurable plugin for tests; records the calls it receives. */
final class TestPlugin implements Plugin
{
    /** @var list<string> */
    public static array $calls = [];

    /**
     * @param  list<string>  $requires
     * @param  (Closure(Panel): void)|null  $register
     */
    public function __construct(
        private string $id,
        private array $requires = [],
        private ?Closure $register = null,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function requires(): array
    {
        return $this->requires;
    }

    public function register(Panel $panel): void
    {
        self::$calls[] = "register {$this->id}";
        if ($this->register !== null) {
            ($this->register)($panel);
        }
    }

    public function boot(Panel $panel): void
    {
        self::$calls[] = "boot {$this->id}";
    }
}
