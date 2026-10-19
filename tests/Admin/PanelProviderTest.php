<?php

declare(strict_types=1);

use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Admin\PanelProvider;

final class ExamplePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->path('backend')->brand('Acme');
    }
}

it('sets up and boots the app\'s panel', function () {
    $this->app->register(ExamplePanelProvider::class);

    $panel = $this->app->make(Panel::class);
    expect($panel->getPath())->toBe('backend')
        ->and($panel->getBrand())->toBe('Acme')
        ->and($panel->isBooted())->toBeTrue()
        ->and($this->app->make(Panel::class))->toBe($panel);
});

it('allows one panel per app', function () {
    $this->app->register(ExamplePanelProvider::class);

    expect(fn () => (new class($this->app) extends PanelProvider
    {
        public function panel(Panel $panel): Panel
        {
            return $panel;
        }
    })->register())->toThrow(PanelMisconfigured::class, 'An app has one panel');
});
