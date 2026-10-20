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
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The Settings page: each section's form, for users with its permission.
 *
 * @internal
 */
final class SettingsController
{
    public function __construct(private Panel $panel) {}

    /** The first section the user may edit. */
    public function index(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        foreach ($this->panel->getSettings() as $section) {
            if ($section->allows($user)) {
                return redirect()->route('sunrice.admin.settings.edit', $section->key());
            }
        }
        abort(403);
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
            if ($field->keepsValueWhenEmpty()) {
                $secrets[$field->name] = $value !== null && $value !== '';
                $value = null;
            }
            $values[$field->name] = $value instanceof BackedEnum ? $value->value : $value;
        }

        return Inertia::render('Settings/Edit', [
            'section' => ['key' => $registered->key(), 'label' => $registered->class::label(), 'description' => $registered->class::description()],
            'sections' => array_values(array_map(
                fn (RegisteredSettings $other) => ['key' => $other->key(), 'label' => $other->class::label()],
                array_filter($this->panel->getSettings(), fn (RegisteredSettings $other) => $other->allows($user)),
            )),
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
        $message = $registered->class::label().' settings saved.';

        // The font and colour are in the page's <head>: load the whole page again to show them.
        if ($registered->class === BrandingSettings::class && $changed !== []) {
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
        abort_if($section === null, 404);
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
