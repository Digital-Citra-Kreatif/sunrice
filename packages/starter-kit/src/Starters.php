<?php

declare(strict_types=1);

namespace Sunrice\StarterKit;

use InvalidArgumentException;

/**
 * The starters this app can install: the starter kit's own and those
 * packages register from their service provider, by folder:
 *
 *     public function boot(): void
 *     {
 *         $this->callAfterResolving(Starters::class, fn (Starters $starters) => $starters->register(dirname(__DIR__).'/starter'));
 *     }
 *
 * Folders are read when asked (sunrice:starters), not at boot.
 *
 * @api
 */
final class Starters
{
    /** @var list<string> */
    private array $paths = [];

    public function register(string $path): self
    {
        $this->paths[] = rtrim($path, '/');

        return $this;
    }

    /**
     * @return array<string, Starter> by name
     *
     * @throws InvalidArgumentException when a registered folder isn't a starter, or two have one name
     */
    public function all(): array
    {
        $starters = [];
        foreach (array_unique($this->paths) as $path) {
            $starter = Starter::load($path);
            if (isset($starters[$starter->name])) {
                throw new InvalidArgumentException("Two starters are called \"{$starter->name}\": {$starters[$starter->name]->path} and {$path}.");
            }
            $starters[$starter->name] = $starter;
        }
        ksort($starters);

        return $starters;
    }

    public function find(string $name): ?Starter
    {
        return $this->all()[$name] ?? null;
    }
}
