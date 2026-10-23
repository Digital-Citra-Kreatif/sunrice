<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use BackedEnum;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Actions\Settings\SendTestMail;
use Sunrice\Admin\Actions\Settings\UpdateSettings;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Tables\ActionFailed;
use Sunrice\Admin\Settings\BrandingSettings;
use Sunrice\Admin\Settings\RegisteredSettings;
use Sunrice\Admin\Settings\Settings;
use Sunrice\Admin\Support\Card;
use Sunrice\Admin\Support\Say;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The Settings page: each section's form, for users with its permission.
 *
 * @internal
 */
final class SettingsController
{
    public function __construct(private Panel $panel) {}

    /** The first section (or page among them) the user may open. */
    public function index(Request $request): RedirectResponse
    {
        $first = $this->panel->settingsNavigation($this->user($request))[0] ?? null;
        abort_if($first === null, 403);

        return redirect()->to(url($this->panel->getPath().'/'.$first['url']));
    }

    public function edit(Request $request, string $section): Response
    {
        $user = $this->user($request);
        $registered = $this->section($user, $section);
        $settings = $registered->load();
        $form = $registered->class::form(new Form);

        $current = $settings->toArray();
        $values = [];
        $secrets = [];
        foreach ($registered->fields($form) as $field) {
            $value = $current[$field->name];
            // Nothing saved yet: the field's default, when it has one (what applies until then).
            if (($value === '' || $value === null) && $field->formValue(null) !== null) {
                $value = $field->formValue(null);
            }
            if ($field->keepsValueWhenEmpty()) {
                $secrets[$field->name] = $value !== null && $value !== '';
                $value = null;
            }
            $values[$field->name] = $value instanceof BackedEnum ? $value->value : $value;
        }

        return Inertia::render('Settings/Edit', [
            'section' => ['key' => $registered->key(), 'label' => Say::it($registered->class::label()), 'description' => Say::it($registered->class::description()), 'warning' => Say::it($registered->class::warning())],
            // The Settings menu: the sections, and pages put among them (NavigationItem::inSettings()).
            'sections' => $this->panel->settingsNavigation($user),
            'form' => $form->toSections('edit'),
            'values' => $values,
            'secrets' => $secrets,
            'cards' => array_map(fn (Card $card) => $card->toArray(), $registered->class::cards($user)),
        ]);
    }

    public function update(Request $request, string $section, UpdateSettings $update): RedirectResponse|SymfonyResponse
    {
        $registered = $this->section($this->user($request), $section);
        $changed = $update->handle($registered, $request->all());
        $message = __(':label settings saved.', ['label' => Say::it($registered->class::label())]);

        // The font, colour and icon are in the page's <head> (the icon follows the logo and the name):
        // load the whole page again to show them. Words alone (tagline, sign-in texts) update in place.
        if ($registered->class === BrandingSettings::class && array_intersect($changed, BrandingSettings::IN_HEAD) !== []) {
            $request->session()->flash('success', $message);

            return Inertia::location(route('sunrice.admin.settings.edit', $registered->key()));
        }

        return back()->with('success', $message);
    }

    /** Settings → Mail → Send a test email, to the signed-in user. */
    public function testMail(Request $request, SendTestMail $send): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($user->can('sunrice.settings.mail'), 403);
        $email = data_get($user, 'email');
        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return back()->with('error', 'Your account has no email address to send the test to.');
        }
        $id = data_get($user, 'id');

        try {
            return back()->with('success', $send->handle($email, is_scalar($id) ? (string) $id : $email));
        } catch (ActionFailed $failed) {
            return back()->with('error', $failed->getMessage());
        }
    }

    private function section(Authorizable $user, string $key): RegisteredSettings
    {
        $section = $this->panel->getSettings()[$key] ?? null;
        // A section that's switched off (Settings::available()) isn't there.
        abort_if($section === null || ! $section->available(), 404);
        abort_unless($section->allows($user), 403);

        return $section;
    }

    private function user(Request $request): Authorizable
    {
        $user = $request->user();
        abort_unless($user instanceof Authorizable, 403);

        return $user;
    }
}
