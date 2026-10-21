<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Honeypot\Honeypot;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Forms\SubmitForm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Mail\SubmissionReceived;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\Submission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['honeypot.enabled' => false]);
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [
        ['handle' => 'name', 'type' => 'text', 'label' => 'Your name', 'required' => true],
        ['handle' => 'email', 'type' => 'text', 'required' => true, 'validation' => ['email']],
        ['handle' => 'topic', 'type' => 'select', 'config' => ['options' => [['value' => 'sales', 'label' => 'Sales'], ['value' => 'help', 'label' => 'Help']]]],
        ['handle' => 'message', 'type' => 'textarea', 'required' => true],
        ['handle' => 'newsletter', 'type' => 'toggle', 'label' => 'Send me news'],
        ['handle' => 'photo', 'type' => 'media'],
    ]]);
    $this->form = Form::query()->create(['handle' => 'contact', 'title' => 'Contact us', 'blueprint' => 'contact', 'notify' => ['team@example.com'], 'message' => 'Thanks!']);
});

function contactInput(array $changes = []): array
{
    return [...['name' => 'Ana', 'email' => 'ana@example.com', 'topic' => 'help', 'message' => "Hello\nthere", 'newsletter' => '1'], ...$changes];
}

it('keeps what visitors send and emails the form\'s addresses', function () {
    Mail::fake();
    $this->form->update(['notify' => ['team@example.com', 'boss@example.com']]);

    from('/contact-page')->post('/forms/contact', contactInput(['photo' => 5, 'extra' => 'ignored']))
        ->assertRedirect('/contact-page')->assertSessionHas('sunrice-cms.form.contact', 'Thanks!');

    $submission = Submission::query()->sole();
    expect($submission->data)->toEqual(['name' => 'Ana', 'email' => 'ana@example.com', 'topic' => 'help', 'message' => "Hello\nthere", 'newsletter' => true])
        ->and($submission->locale)->toBe('en');
    // One email each: the addresses aren't shown to each other.
    Mail::assertQueued(SubmissionReceived::class, 2);
    Mail::assertQueued(SubmissionReceived::class, fn (SubmissionReceived $mail) => $mail->hasTo('team@example.com') && ! $mail->hasTo('boss@example.com') && $mail->hasReplyTo('ana@example.com'));
    Mail::assertQueued(SubmissionReceived::class, fn (SubmissionReceived $mail) => $mail->hasTo('boss@example.com') && ! $mail->hasTo('team@example.com'));
});

it('renders the notification escaped, with labels', function () {
    $submission = app(SubmitForm::class)->handle($this->form, contactInput(['name' => '<b>Ana</b>']));

    $html = (new SubmissionReceived($submission))->render();

    expect($html)->toContain('Your name')->toContain('&lt;b&gt;Ana&lt;/b&gt;')->toContain('Help')->toContain('Send me news')->toContain('Yes')
        ->not->toContain('<b>Ana</b>');
});

it('sends the visitor back with errors under the form\'s bag', function () {
    from('/contact-page')->post('/forms/contact', contactInput(['email' => 'nope', 'message' => '']))
        ->assertRedirect('/contact-page')->assertSessionHasErrors(['email', 'message'], errorBag: 'contact');

    postJson('/forms/contact', contactInput(['name' => '']))->assertUnprocessable()->assertJsonValidationErrors(['name' => 'Your name']);
    postJson('/forms/contact', contactInput())->assertCreated()->assertJson(['message' => 'Thanks!']);
    expect(Submission::query()->count())->toBe(1);
});

it('answers 404 for an unknown form', function () {
    post('/forms/nope', contactInput())->assertNotFound();
});

it('turns away spam bots with the honeypot', function () {
    config(['honeypot.enabled' => true]);
    $honeypot = app(Honeypot::class);
    // As the form had them when the page was shown.
    $shown = $honeypot->encryptedValidFrom();
    $fields = fn (string $name = '') => [$honeypot->nameFieldName() => $name, $honeypot->validFromFieldName() => $shown];

    // Without the fields, with the trap filled in, or sent too fast.
    post('/forms/contact', contactInput())->assertOk()->assertContent('');
    travel(5)->seconds();
    post('/forms/contact', contactInput($fields('i am a bot')))->assertOk()->assertContent('');
    post('/forms/contact', contactInput([$honeypot->nameFieldName() => '', $honeypot->validFromFieldName() => app(Honeypot::class)->encryptedValidFrom()]))->assertContent('');
    expect(Submission::query()->count())->toBe(0);

    from('/contact-page')->post('/forms/contact', contactInput($fields()))->assertRedirect('/contact-page');
    expect(Submission::query()->count())->toBe(1);
});

it('limits how often one visitor sends', function () {
    config(['sunrice-cms.forms.per_minute' => 2]);
    RateLimiter::clear('sunrice-forms|127.0.0.1');

    postJson('/forms/contact', contactInput())->assertCreated();
    postJson('/forms/contact', contactInput())->assertCreated();
    postJson('/forms/contact', contactInput())->assertTooManyRequests();
});

it('renders the form for templates', function () {
    $html = Blade::render('<x-sunrice-cms::form handle="contact" class="contact" />');

    expect($html)->toContain('action="'.route('sunrice.cms.forms.submit', 'contact').'"')
        ->toContain('class="contact"')
        ->toContain('<label for="form-contact-name">Your name')
        ->toContain('type="email" id="form-contact-email"')
        ->toContain('<option value="help"')
        ->toContain('<textarea id="form-contact-message"')
        ->toContain('type="checkbox" id="form-contact-newsletter"')
        ->toContain('name="_token"')
        ->not->toContain('form-contact-photo');
    expect(Blade::render('<x-sunrice-cms::form handle="missing" />'))->toBe('');
});

it('lists, shows and deletes submissions for those allowed', function () {
    $submission = app(SubmitForm::class)->handle($this->form, contactInput());

    actingAs(adminUser(Panel::ACCESS));
    get('/admin/cms/submissions')->assertForbidden();
    get('/admin/cms/forms/contact/submissions/export')->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.submissions.view'));
    get('/admin/cms/submissions')->assertRedirect('/admin/cms/forms/contact/submissions');
    get('/admin/cms/forms/contact/submissions')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Submissions/Index', shouldExist: false)
        ->where('forms.0.count', 1)
        ->where('submissions.data.0.rows.0', ['handle' => 'name', 'label' => 'Your name', 'value' => 'Ana', 'file' => null, 'files' => []])
        ->where('submissions.data.0.rows.2.value', 'Help')
        ->where('can.delete', false));
    delete("/admin/cms/submissions/{$submission->id}")->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.submissions.view', 'cms.submissions.delete'));
    delete("/admin/cms/submissions/{$submission->id}")->assertRedirect();
    expect(Submission::query()->count())->toBe(0);
});

it('exports submissions as CSV safe for spreadsheets, with the export permission', function () {
    app(SubmitForm::class)->handle($this->form, contactInput(['name' => '=HYPERLINK("x")']));
    actingAs(adminUser(Panel::ACCESS, 'cms.submissions.view'));
    get('/admin/cms/forms/contact/submissions/export')->assertForbidden();
    get('/admin/cms/forms/contact/submissions')->assertInertia(fn (Assert $page) => $page->where('can.export', false));

    actingAs(adminUser(Panel::ACCESS, 'cms.submissions.contact.view', 'cms.submissions.contact.export'));
    get('/admin/cms/forms/contact/submissions')->assertInertia(fn (Assert $page) => $page->where('can.export', true));

    $csv = get('/admin/cms/forms/contact/submissions/export')->assertOk()->streamedContent();

    expect($csv)->toStartWith('Sent,Language,"Your name",Email,Topic,Message,"Send me news"')
        ->toContain("'=HYPERLINK")->toContain(',en,')->toContain('Help');
});

it('manages forms in Structure → Forms', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.forms.view', 'cms.forms.create', 'cms.forms.update'));

    post('/admin/forms', ['title' => 'Quote', 'handle' => 'quote', 'blueprint' => 'contact', 'notify' => "a@example.com\nb@example.com"])->assertSessionHasNoErrors();
    expect(Form::query()->where('handle', 'quote')->sole()->notify)->toBe(['a@example.com', 'b@example.com']);

    post('/admin/forms', ['title' => 'Bad', 'handle' => 'bad', 'blueprint' => 'contact', 'notify' => 'not-an-email'])->assertSessionHasErrors('notify');
});

it('takes markup of the template\'s own in a slot, with this form\'s errors and old input only', function () {
    Blueprint::query()->create(['handle' => 'signup', 'title' => 'Signup', 'fields' => [['handle' => 'email', 'type' => 'text', 'required' => true, 'validation' => ['email']]]]);
    Form::query()->create(['handle' => 'newsletter', 'title' => 'Newsletter', 'blueprint' => 'signup', 'message' => 'Welcome!']);
    $slot = fn (string $handle, string $extra = '') => '<x-sunrice-cms::form handle="'.$handle.'" '.$extra.'>'
        .'<input name="data[email]" value="{{ $component->old(\'email\') }}">[{{ $component->error(\'email\') }}]{{ $component->submitted() ? \'sent\' : \'\' }}'
        .'<button>Go</button></x-sunrice-cms::form>';
    Route::get('/two-forms', fn () => Blade::render('<div id="a">'.$slot('contact').'</div><div id="b">'.$slot('newsletter', 'replace').'</div>'));

    $html = (string) from('/two-forms')->followingRedirects()->post('/forms/contact', ['_form' => 'contact', 'data' => contactInput(['email' => 'not-an-email'])])->assertOk()->getContent();
    [$contact, $newsletter] = explode('<div id="b">', $html);

    expect($contact)->toContain('name="_token"')->toContain('name="_form" value="contact"')
        ->toContain('value="not-an-email"')->toContain('[The Email field must be a valid email address.]sent')
        ->not->toContain('<button type="submit">');
    // The other form, with a field of the same handle, keeps its own (empty) state.
    expect($newsletter)->toContain('<input name="data[email]" value="">[]<button>');
    expect(Submission::query()->count())->toBe(0);

    // Sent: the thank-you message above the form, or in its place with `replace`.
    $html = (string) from('/two-forms')->followingRedirects()->post('/forms/newsletter', ['_form' => 'newsletter', 'data' => ['email' => 'ana@example.com']])->assertOk()->getContent();
    [$contact, $newsletter] = explode('<div id="b">', $html);
    expect($newsletter)->toContain('<p id="form-newsletter" role="status">Welcome!</p>')->not->toContain('<form')
        ->and($contact)->toContain('<form')->not->toContain('Welcome!');
    expect(Submission::query()->sole()->data)->toBe(['email' => 'ana@example.com']);
});

it('refills the printed form after errors, and takes its fields as data[handle]', function () {
    Route::get('/contact-page', fn () => Blade::render('<x-sunrice-cms::form handle="contact" />'));

    $html = (string) from('/contact-page')->followingRedirects()->post('/forms/contact', ['_form' => 'contact', 'data' => contactInput(['message' => ''])])->getContent();

    expect($html)->toContain('name="data[name]" value="Ana"')->toContain('id="form-contact-message-error" role="alert"')
        ->toContain('<option value="help" selected>');
    postJson('/forms/contact', ['data' => contactInput()])->assertCreated()->assertJson(['message' => 'Thanks!', 'redirect_url' => null]);
    postJson('/forms/contact', ['data' => contactInput(['email' => 'x'])])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

it('keeps who sent it, shows it with the submission and in the CSV, unless turned off', function () {
    from('https://example.com/contact-page')->withHeader('User-Agent', 'TestBrowser/1.0')->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->post('/forms/contact', ['data' => contactInput()])->assertRedirect();
    $submission = Submission::query()->sole();
    expect($submission->only(['ip_address', 'user_agent', 'referrer']))->toBe(['ip_address' => '203.0.113.9', 'user_agent' => 'TestBrowser/1.0', 'referrer' => 'https://example.com/contact-page']);

    actingAs(adminUser(Panel::ACCESS, 'cms.submissions.view', 'cms.submissions.export'));
    get('/admin/cms/forms/contact/submissions')->assertInertia(fn (Assert $page) => $page
        ->where('submissions.data.0.visitor', ['ip' => '203.0.113.9', 'userAgent' => 'TestBrowser/1.0', 'referrer' => 'https://example.com/contact-page']));
    $csv = get('/admin/cms/forms/contact/submissions/export')->streamedContent();
    expect($csv)->toContain('"Send me news","IP address","User agent",Referrer')->toContain('203.0.113.9,TestBrowser/1.0,https://example.com/contact-page');

    // Off: nothing about the sender is kept; and only web addresses count as a referrer.
    config(['sunrice-cms.forms.visitor_details' => false]);
    $off = app(SubmitForm::class)->handle($this->form, contactInput(), 'en', '203.0.113.9', 'TestBrowser/1.0', 'https://example.com');
    config(['sunrice-cms.forms.visitor_details' => true]);
    $odd = app(SubmitForm::class)->handle($this->form, contactInput(), 'en', 'not-an-ip', str_repeat('a', 600), 'javascript:alert(1)');
    expect($off->only(['ip_address', 'user_agent', 'referrer']))->toBe(['ip_address' => null, 'user_agent' => null, 'referrer' => null])
        ->and($odd->ip_address)->toBeNull()->and(strlen((string) $odd->user_agent))->toBe(512)->and($odd->referrer)->toBeNull();
});

it('emails the members of the form\'s roles too, once each', function () {
    Mail::fake();
    $role = Role::findOrCreate('sales', 'web');
    $member = adminUser(Panel::ACCESS);
    $member->update(['email' => 'team@example.com']);
    $member->assignRole($role);
    $other = adminUser(Panel::ACCESS);
    $other->update(['email' => 'seller@example.com']);
    $other->assignRole($role);
    $this->form->update(['notify_roles' => ['sales']]);

    app(SubmitForm::class)->handle($this->form, contactInput());

    // team@example.com is an address and a member: one email.
    Mail::assertQueued(SubmissionReceived::class, 2);
    Mail::assertQueued(SubmissionReceived::class, fn (SubmissionReceived $mail) => $mail->hasTo('seller@example.com'));

    actingAs(adminUser(Panel::ACCESS, 'cms.forms.view', 'cms.forms.create', 'cms.forms.update'));
    post('/admin/forms', ['title' => 'Quote', 'handle' => 'quote', 'blueprint' => 'contact', 'notify_roles' => ['sales']])->assertSessionHasNoErrors();
    expect(Form::query()->where('handle', 'quote')->sole()->notify_roles)->toBe(['sales']);
    post('/admin/forms', ['title' => 'Bad', 'handle' => 'bad', 'blueprint' => 'contact', 'notify_roles' => ['nobody']])->assertSessionHasErrors('notify_roles.0');
});
