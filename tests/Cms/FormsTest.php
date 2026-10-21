<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Honeypot\Honeypot;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Forms\SubmitForm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\Types\FileField;
use Sunrice\Cms\Mail\SubmissionReceived;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\Submission;
use Sunrice\Cms\Support\Locales;
use Sunrice\Cms\Support\RoleMembers;

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
        ->assertRedirect('/contact-page#form-contact-status')->assertSessionHas('sunrice-cms.form.contact', 'Thanks!');

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

it('says in the notification where and in which language the form was sent, links to the submissions and leaves out what was left empty', function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    $submission = app(SubmitForm::class)->handle($this->form, contactInput(['topic' => '', 'newsletter' => '0']), 'id', '10.0.0.1', 'Firefox', 'https://example.com/kontak');

    $html = (new SubmissionReceived($submission))->render();

    expect($html)->toContain('form in '.Locales::name('id').',')->toContain('from <a href="https://example.com/kontak">')
        ->toContain('href="'.route('sunrice.admin.cms.forms.submissions.index', 'contact').'"')
        ->not->toContain('>Topic<')
        ->not->toContain('Photo');
});

it('sends the visitor back with errors under the form\'s bag', function () {
    from('/contact-page')->post('/forms/contact', contactInput(['email' => 'nope', 'message' => '']))
        ->assertRedirect('/contact-page#form-contact-errors')->assertSessionHasErrors(['email', 'message'], errorBag: 'contact');

    postJson('/forms/contact', contactInput(['name' => '']))->assertUnprocessable()->assertJsonValidationErrors(['name' => 'Your name']);
    postJson('/forms/contact', contactInput())->assertCreated()->assertJson(['message' => 'Thanks!']);
    expect(Submission::query()->count())->toBe(1);
});

it('never sends the visitor to another site that sent them', function () {
    $evil = fn () => test()->withHeader('referer', 'https://evil.example/phish');
    $evil()->post('/forms/contact', contactInput())->assertRedirect('/#form-contact-status');
    $evil()->post('/forms/contact', contactInput(['email' => 'nope']))->assertRedirect('/#form-contact-errors');
    test()->withHeader('referer', '//evil.example/x')->post('/forms/contact', contactInput())->assertRedirect('/#form-contact-status');
    from('/contact-page')->post('/forms/contact', contactInput())->assertRedirect('/contact-page#form-contact-status');
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

    from('/contact-page')->post('/forms/contact', contactInput($fields()))->assertRedirect('/contact-page#form-contact-status');
    expect(Submission::query()->count())->toBe(1);
});

it('limits how often one visitor sends', function () {
    config(['sunrice-cms.forms.per_minute' => 2]);
    RateLimiter::clear('sunrice-forms|127.0.0.1');

    postJson('/forms/contact', contactInput())->assertCreated();
    postJson('/forms/contact', contactInput())->assertCreated();
    postJson('/forms/contact', contactInput())->assertTooManyRequests()->assertJsonValidationErrors(['_limit']);
});

it('counts only what was kept against the limit, and sends the visitor back to the form when over it', function () {
    config(['sunrice-cms.forms.per_minute' => 2]);

    // Tries that fail validation don't count.
    foreach (range(1, 5) as $try) {
        from('/contact-page')->post('/forms/contact', contactInput(['email' => 'nope']))->assertSessionHasErrors(['email'], errorBag: 'contact');
    }
    from('/contact-page')->post('/forms/contact', contactInput())->assertRedirect('/contact-page#form-contact-status');
    from('/contact-page')->post('/forms/contact', contactInput())->assertRedirect('/contact-page#form-contact-status');
    // Over it: back to the form with a message, what was typed kept.
    from('/contact-page')->post('/forms/contact', contactInput(['message' => 'Kept text']))
        ->assertRedirect('/contact-page#form-contact-errors')
        ->assertSessionHasErrors(['_limit'], errorBag: 'contact')
        ->assertSessionHasInput('message', 'Kept text');
    expect(Submission::query()->count())->toBe(2)
        ->and(session('errors')->getBag('contact')->first('_limit'))->toContain('Please wait');
    // A flood of failing tries hits a ceiling too.
    travel(2)->minutes();
    foreach (range(1, 20) as $try) {
        post('/forms/contact', contactInput(['email' => 'nope']));
    }
    post('/forms/contact', contactInput())->assertSessionHasErrors(['_limit'], errorBag: 'contact');
});

it('lands the visitor on the form, with the message or an error summary to read first', function () {
    Route::get('/contact-page', fn () => Blade::render('<main style="height:4000px"></main><x-sunrice-cms::form handle="contact" />'))->middleware('web');

    $html = (string) from('/contact-page')->followingRedirects()->post('/forms/contact', contactInput(['email' => 'nope', 'message' => '', '_form' => 'contact']))->getContent();
    expect($html)->toContain('<div id="form-contact-errors" role="alert" tabindex="-1" autofocus>')
        ->toContain('<a href="#form-contact-email">')
        ->toContain('<a href="#form-contact-message">');

    $html = (string) from('/contact-page')->followingRedirects()->post('/forms/contact', contactInput())->getContent();
    expect($html)->toContain('<p id="form-contact-status" role="status" tabindex="-1" autofocus>Thanks!</p>')->not->toContain('form-contact-errors');
});

it('says how large a file may be, on the form and when one is too large for PHP', function () {
    Blueprint::query()->where('handle', 'contact')->firstOrFail()->update(['fields' => [
        ['handle' => 'name', 'type' => 'text'],
        ['handle' => 'photos', 'type' => 'file', 'label' => 'Photos', 'config' => ['types' => 'jpg, png', 'max_kb' => 5120]],
    ]]);
    $php = FileField::maxKb(new Field('photos', 'file', config: ['max_kb' => 5120]));
    $size = FileField::size($php);

    expect(Blade::render('<x-sunrice-cms::form handle="contact" />'))->toContain('Accepted: JPG, PNG, up to '.$size.'.')
        ->toContain('aria-describedby="form-contact-photos-help"');
    $big = new UploadedFile(__FILE__, 'villa.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);
    from('/contact-page')->post('/forms/contact', ['data' => ['name' => 'Ana', 'photos' => $big]])
        ->assertSessionHasErrors(['photos' => 'The Photos file is larger than '.$size.', the most this site accepts.'], errorBag: 'contact');
    expect(FileField::size(2048))->toBe('2 MB')
        ->and(FileField::size(1536))->toBe('1.5 MB')
        ->and(FileField::size(500))->toBe('500 KB');
});

it('names a field of several files over PHP\'s limit and says the size, in the summary and under the field', function () {
    Blueprint::query()->where('handle', 'contact')->firstOrFail()->update(['fields' => [
        ['handle' => 'name', 'type' => 'text'],
        ['handle' => 'photos', 'type' => 'file', 'label' => 'Photos', 'config' => ['types' => 'jpg', 'max_kb' => 5120, 'multiple' => true]],
    ]]);
    $size = FileField::size(FileField::maxKb(new Field('photos', 'file', config: ['max_kb' => 5120])));
    Route::get('/contact-page', fn () => Blade::render('<x-sunrice-cms::form handle="contact" />'))->middleware('web');
    $ok = UploadedFile::fake()->image('ok.jpg');
    $big = new UploadedFile(__FILE__, 'villa.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);
    $message = 'One of the Photos files is larger than '.$size.', the most this site accepts for each file.';

    from('/contact-page')->post('/forms/contact', ['_form' => 'contact', 'data' => ['name' => 'Ana', 'photos' => [$ok, $big]]])
        ->assertRedirect('/contact-page#form-contact-errors')
        ->assertSessionHasErrors(['photos' => $message], errorBag: 'contact');
    $html = (string) get('/contact-page')->getContent();
    expect($html)->toContain('<a href="#form-contact-photos">'.$message.'</a>')
        ->toContain('<p id="form-contact-photos-error" role="alert">'.$message.'</p>')
        ->not->toContain('The * ');

    // Other errors for one of several files name the field too.
    $text = UploadedFile::fake()->create('notes.txt', 1, 'text/plain');
    from('/contact-page')->post('/forms/contact', ['data' => ['name' => 'Ana', 'photos' => [$text]]]);
    expect(session('errors')->getBag('contact')->first('photos'))->toContain('The Photos field')->not->toContain('*');
    expect(Submission::query()->count())->toBe(0);
});

it('lands the visitor on the error summary of a slot\'s markup, or on the slot\'s own', function () {
    $slot = '<x-sunrice-cms::form handle="contact"><input id="form-contact-email" name="data[email]"><button>Go</button></x-sunrice-cms::form>';
    Route::get('/contact-page', fn () => Blade::render($slot))->middleware('web');

    from('/contact-page')->post('/forms/contact', ['_form' => 'contact', 'data' => contactInput(['email' => 'nope', 'message' => ''])])
        ->assertRedirect('/contact-page#form-contact-errors');
    $html = (string) get('/contact-page')->getContent();
    // Linked only where the slot has the input.
    expect($html)->toContain('<div id="form-contact-errors" role="alert" tabindex="-1" autofocus>')
        ->toContain('<a href="#form-contact-email">')
        ->not->toContain('href="#form-contact-message"')
        ->toContain('<li>The Message field is required.</li>');

    // The slot's own summary: the component prints none.
    $own = '<x-sunrice-cms::form handle="contact">@if ($component->failed())<p id="form-contact-errors" tabindex="-1">Check the fields.</p>@endif<button>Go</button></x-sunrice-cms::form>';
    Route::get('/own-page', fn () => Blade::render($own))->middleware('web');
    from('/own-page')->post('/forms/contact', ['_form' => 'contact', 'data' => contactInput(['email' => 'nope'])])->assertRedirect('/own-page#form-contact-errors');
    expect(substr_count((string) get('/own-page')->getContent(), 'id="form-contact-errors"'))->toBe(1);
});

it('heads the summary of the send limit as a form not sent, not one to correct', function () {
    config(['sunrice-cms.forms.per_minute' => 1]);
    Route::get('/contact-page', fn () => Blade::render('<x-sunrice-cms::form handle="contact" />'))->middleware('web');
    from('/contact-page')->post('/forms/contact', contactInput());

    from('/contact-page')->post('/forms/contact', contactInput(['_form' => 'contact']))->assertRedirect('/contact-page#form-contact-errors');
    $html = (string) get('/contact-page')->getContent();
    expect($html)->toContain('<p>This form wasn&#039;t sent:</p>')->not->toContain('Please correct')
        ->toContain('Please wait');

    // With errors to correct as well, it asks for them.
    travel(2)->minutes();
    from('/contact-page')->post('/forms/contact', contactInput(['email' => 'nope', '_form' => 'contact']));
    expect((string) get('/contact-page')->getContent())->toContain('Please correct the following');
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

    post('/admin/cms/forms', ['title' => 'Quote', 'handle' => 'quote', 'blueprint' => 'contact', 'notify' => "a@example.com\nb@example.com"])->assertSessionHasNoErrors();
    expect(Form::query()->where('handle', 'quote')->sole()->notify)->toBe(['a@example.com', 'b@example.com']);

    post('/admin/cms/forms', ['title' => 'Bad', 'handle' => 'bad', 'blueprint' => 'contact', 'notify' => 'not-an-email'])->assertSessionHasErrors('notify');
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
    expect($newsletter)->toContain('<p id="form-newsletter-status" role="status" tabindex="-1" autofocus>Welcome!</p>')->not->toContain('<form')
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
    post('/admin/cms/forms', ['title' => 'Quote', 'handle' => 'quote', 'blueprint' => 'contact', 'notify_roles' => ['sales']])->assertSessionHasNoErrors();
    expect(Form::query()->where('handle', 'quote')->sole()->notify_roles)->toBe(['sales']);
    post('/admin/cms/forms', ['title' => 'Bad', 'handle' => 'bad', 'blueprint' => 'contact', 'notify_roles' => ['nobody']])->assertSessionHasErrors('notify_roles.0');

    // Roles by the names people read, kept by their own.
    Role::findOrCreate('super-admin', 'web');
    expect(RoleMembers::options())->toMatchArray(['sales' => 'Sales', 'super-admin' => 'Super admin']);
});

it('offers to create a form when there are none, to those who may', function () {
    Submission::query()->delete();
    Form::query()->delete();

    actingAs(adminUser(Panel::ACCESS, 'cms.submissions.view', 'cms.forms.create'));
    get('/admin/cms/submissions')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Submissions/Index', shouldExist: false)->where('form', null)->where('createForm', '/admin/cms/forms/create'));

    actingAs(adminUser(Panel::ACCESS, 'cms.submissions.view'));
    get('/admin/cms/submissions')->assertInertia(fn (Assert $page) => $page->where('createForm', null));
});

it('refuses days before today in a date field set to "today or later"', function () {
    Date::setTestNow('2026-10-21 09:00:00');
    Blueprint::query()->where('handle', 'contact')->firstOrFail()->update(['fields' => [
        ['handle' => 'name', 'type' => 'text'],
        ['handle' => 'preferred_date', 'type' => 'date', 'config' => ['future' => true]],
    ]]);

    expect(Blade::render('<x-sunrice-cms::form handle="contact" />'))->toContain('type="date" id="form-contact-preferred_date"')->toContain('min="2026-10-21"');
    postJson('/forms/contact', ['name' => 'Ana', 'preferred_date' => '2020-01-01'])->assertUnprocessable()->assertJsonValidationErrors(['preferred_date']);
    postJson('/forms/contact', ['name' => 'Ana', 'preferred_date' => '2026-10-21'])->assertCreated();

    // Said in the visitor's language, without the rule's English "today" (NEW2-i18n-1).
    config(['sunrice-cms.locales' => ['en', 'id']]);
    postJson('/forms/contact', ['name' => 'Ana', 'preferred_date' => '2026-10-20'])
        ->assertJsonValidationErrors(['preferred_date' => 'The Preferred Date must be today or a later date.']);
    postJson('/forms/contact', ['locale' => 'id', 'name' => 'Ana', 'preferred_date' => '2026-10-20'])
        ->assertJsonValidationErrors(['preferred_date' => 'Preferred Date harus berupa tanggal hari ini atau setelahnya.']);
    postJson('/forms/contact', ['name' => 'Ana', 'preferred_date' => 'soon'])->assertJsonValidationErrors(['preferred_date']);
});

it('shows and checks a form in the visitor\'s language', function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    $blueprint = Blueprint::query()->where('handle', 'contact')->firstOrFail();
    $fields = $blueprint->fields;
    $fields[0]['translations'] = ['id' => ['label' => 'Nama Anda', 'instructions' => 'Nama lengkap']];
    $fields[1]['label'] = 'Email';
    $fields[1]['translations'] = ['id' => ['label' => 'Surel']];
    $fields[2]['translations'] = ['id' => ['options' => ['help' => 'Bantuan']]];
    $blueprint->update(['fields' => $fields]);

    app()->setLocale('id');
    $html = Blade::render('<x-sunrice-cms::form handle="contact" />');
    expect($html)->toContain('<label for="form-contact-name">Nama Anda')
        ->toContain('<p id="form-contact-name-help">Nama lengkap</p>')
        ->toContain('<label for="form-contact-email">Surel')
        ->toContain('>Bantuan</option>')
        ->toContain('>Sales</option>')
        ->toContain('<input type="hidden" name="locale" value="id">');
    app()->setLocale('en');
    expect(Blade::render('<x-sunrice-cms::form handle="contact" />'))->toContain('<label for="form-contact-name">Your name')->toContain('>Help</option>');

    // Errors in the visitor's language, naming the fields as they read them.
    from('/id/kontak')->post('/forms/contact', ['locale' => 'id', 'name' => '', 'email' => 'nope', 'message' => 'Hi'])
        ->assertSessionHasErrorsIn('contact', ['name' => 'Nama Anda wajib diisi.', 'email' => 'Surel harus berupa alamat email yang valid.']);
    from('/kontak')->post('/forms/contact', ['locale' => 'en', 'name' => '', 'email' => 'ana@example.com', 'message' => 'Hi'])
        ->assertSessionHasErrorsIn('contact', ['name' => 'The Your name field is required.']);
});

it('gives a select\'s option labels in a language', function () {
    $field = Field::fromArray(['handle' => 'status', 'type' => 'select', 'config' => ['options' => [['value' => 'under_offer', 'label' => 'Under offer'], ['value' => 'sold']]],
        'translations' => ['id' => ['label' => 'Status', 'options' => ['under_offer' => 'Dalam penawaran']]]]);

    expect($field->optionLabel('under_offer', 'en'))->toBe('Under offer')
        ->and($field->optionLabel('under_offer', 'id'))->toBe('Dalam penawaran')
        ->and($field->optionLabel('sold', 'id'))->toBe('sold')
        ->and($field->optionLabel('gone', 'id'))->toBeNull()
        ->and($field->labelIn('id'))->toBe('Status')
        ->and($field->toArray()['translations'])->toBe(['id' => ['label' => 'Status', 'options' => ['under_offer' => 'Dalam penawaran']]])
        ->and($field->withConfig([])->translations)->toBe($field->translations);
});

it('thanks visitors in their language', function () {
    config(['sunrice-cms.locales' => ['en', 'id', 'fr']]);

    postJson('/forms/contact', [...contactInput(), 'locale' => 'id'])->assertCreated()->assertJson(['message' => 'Terima kasih, pesan Anda sudah kami terima.']);
    // A language the site's words have no line in: the form's own message.
    postJson('/forms/contact', [...contactInput(), 'locale' => 'fr'])->assertCreated()->assertJson(['message' => 'Thanks!']);
    $this->form->translate('message', 'id', 'Terima kasih!')->save();
    from('/id/kontak')->post('/forms/contact', [...contactInput(), 'locale' => 'id'])->assertSessionHas('sunrice-cms.form.contact', 'Terima kasih!');
    postJson('/forms/contact', [...contactInput(), 'locale' => 'en'])->assertJson(['message' => 'Thanks!']);
    $this->form->update(['message' => null]);
    postJson('/forms/contact', [...contactInput(), 'locale' => 'en'])->assertJson(['message' => 'Thank you, we got your message.']);
});
