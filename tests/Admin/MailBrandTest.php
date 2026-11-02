<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Mail\MailBrand;
use Sunrice\Admin\Mail\SecurityAlert;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Notifications\LoginCode;
use Sunrice\Admin\Notifications\PanelInvitation;
use Sunrice\Admin\Notifications\ResetPanelPassword;
use Sunrice\Admin\Notifications\TestMail;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Settings\BrandingSettings;

beforeEach(function () {
    Storage::fake('public');
    config(['app.url' => 'https://acme.test', 'app.name' => 'Acme App']);
});

function mailBrandLogo(string $name, int $width = 400, int $height = 100): Media
{
    $file = str_ends_with($name, '.svg')
        ? UploadedFile::fake()->createWithContent($name, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 10"><rect width="40" height="10"/></svg>')
        : UploadedFile::fake()->image($name, $width, $height);

    return app(UploadMedia::class)->handle($file, []);
}

/** The notification's email, as HTML and as text. @return array{0: string, 1: string} */
function renderedNotification(Notification $notification): array
{
    $message = $notification->toMail((object) []);
    expect($message)->toBeInstanceOf(MailMessage::class);
    /** @var MailMessage $message */
    $markdown = app(Markdown::class);

    return [(string) $markdown->render($message->markdown, $message->data()), (string) $markdown->renderText($message->markdown, $message->data())];
}

it('fits a logo in the email header, absolute and never an SVG', function () {
    usePanel();
    $brand = MailBrand::image('Acme', '/storage/logo.png', 400, 100);
    expect($brand->logo)->toBe('https://acme.test/storage/logo.png')
        ->and([$brand->width, $brand->height])->toBe([192, 48]);

    // Very wide: held to MAX_WIDTH; small: never enlarged.
    expect([MailBrand::image('Acme', 'https://cdn.example.com/a.png', 2000, 100)->width, MailBrand::image('Acme', 'https://cdn.example.com/a.png', 2000, 100)->height])->toBe([240, 12])
        ->and(MailBrand::image('Acme', 'https://cdn.example.com/a.png', 2000, 100)->logo)->toBe('https://cdn.example.com/a.png')
        ->and([MailBrand::image('Acme', '/a.png', 60, 20)->width, MailBrand::image('Acme', '/a.png', 60, 20)->height])->toBe([60, 20])
        ->and(MailBrand::image('Acme', '/logo.svg', 40, 10)->logo)->toBeNull()
        ->and(MailBrand::name('Acme')->logo)->toBeNull();
});

it('shows Settings → Branding\'s logo at the top of the panel\'s notifications, else the name', function () {
    usePanel(fn (Panel $panel) => $panel->brand('Acme'));

    [$html, $text] = renderedNotification(new TestMail('Acme'));
    expect($html)->toContain('<a href="https://acme.test" style="')->toMatch('/<td class="header"[^>]*>\s*<a [^>]*>\s*Acme\s*<\/a>/')
        ->not->toContain('<img')
        ->and($text)->toStartWith('Acme: https://acme.test');

    $logo = mailBrandLogo('logo.png', 400, 100);
    app(BrandingSettings::class)->fill(['logo' => $logo->id])->save();

    $notifications = [
        new TestMail('Acme'),
        new LoginCode('123456', 'Acme'),
        new PanelInvitation('token', 'new@example.com', 'Acme', null, 60),
        new ResetPanelPassword('token', 'someone@example.com', 'Acme'),
    ];
    foreach ($notifications as $notification) {
        [$html, $text] = renderedNotification($notification);
        expect($html)->toContain('<img src="https://acme.test'.e($logo->url()).'" alt="Acme" width="192" height="48"')
            ->not->toContain('.svg')->not->toContain('<svg')
            // Plain text keeps the name, no markup.
            ->and($text)->toStartWith('Acme: https://acme.test')->not->toContain('<img')
            // Signed and footed by the same brand, never the app's name.
            ->and($text)->toContain("Regards,  \nAcme")->toContain('© '.date('Y').' Acme.')->not->toContain('Acme App');
    }
});

it('keeps the name alone for an SVG logo, which email clients don\'t show', function () {
    usePanel(fn (Panel $panel) => $panel->brand('Acme'));
    app(BrandingSettings::class)->fill(['logo' => mailBrandLogo('logo.svg')->id])->save();

    expect(app(Panel::class)->mailBrand()->logo)->toBeNull();
    [$html] = renderedNotification(new TestMail('Acme'));
    expect($html)->not->toContain('<img')->not->toContain('.svg');
});

it('takes a plugin\'s brand, once', function () {
    usePanel(fn (Panel $panel) => $panel->brand('Acme')->mailBrandUsing(fn (): MailBrand => MailBrand::image('Kebun', 'https://kebun.test/mail.png', 192, 96)));

    [$html, $text] = renderedNotification(new LoginCode('123456', 'Acme'));
    expect($html)->toContain('<img src="https://kebun.test/mail.png" alt="Kebun" width="96" height="48"')
        // The header, the sign-off and the footer name the same brand.
        ->toContain('© '.date('Y').' Kebun.')
        // Laravel's mail layout keeps the app's name in the HTML <title> only, which clients don't show.
        ->and(str($html)->after('<body')->toString())->not->toContain('Acme App')
        ->and($text)->toStartWith('Kebun: https://acme.test')->toContain("Regards,  \nKebun")->toContain('© '.date('Y').' Kebun.');

    // The security alert (an HTML mail of its own) shows it too.
    $alert = (new SecurityAlert('Headline', 'What', 'Advice', [], 'Acme', 'https://acme.test/admin/activity'))->render();
    expect($alert)->toContain('<img src="https://kebun.test/mail.png" alt="Kebun"');

    expect(fn () => Panel::make()->mailBrandUsing(fn () => MailBrand::name('a'))->mailBrandUsing(fn () => MailBrand::name('b')))
        ->toThrow(PanelMisconfigured::class);
});

it('leaves the security alert\'s header as it was without a logo', function () {
    usePanel(fn (Panel $panel) => $panel->brand('Acme'));

    $alert = (new SecurityAlert('Headline', 'What', 'Advice', [], 'Acme', 'https://acme.test/admin/activity'))->render();
    expect($alert)->not->toContain('<img')->toContain('Security alert · Acme');
});
