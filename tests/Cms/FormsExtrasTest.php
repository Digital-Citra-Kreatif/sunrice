<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Panel;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\Submission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\delete;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['honeypot.enabled' => false]);
    Mail::fake();
    Storage::fake('local');
    Blueprint::query()->create(['handle' => 'apply', 'title' => 'Apply', 'fields' => [
        ['handle' => 'name', 'type' => 'text', 'required' => true],
        ['handle' => 'cv', 'type' => 'file', 'label' => 'Your CV', 'config' => ['types' => 'pdf, docx, .PHP', 'max_kb' => 200]],
    ]]);
    $this->form = Form::query()->create(['handle' => 'apply', 'title' => 'Apply', 'blueprint' => 'apply']);
});

it('takes files, kept privately, downloaded by people who may see submissions', function () {
    $html = Blade::render('<x-sunrice-cms::form handle="apply" />');
    expect($html)->toContain('enctype="multipart/form-data"')
        ->toContain('<input type="file" id="form-apply-cv" name="data[cv]"')->toContain('accept=".pdf,.docx"');

    post('/forms/apply', ['name' => 'Ana', 'cv' => UploadedFile::fake()->create('my cv.pdf', 120, 'application/pdf')])->assertSessionHasNoErrors();
    $file = Submission::query()->sole()->data['cv'];
    expect($file)->toMatchArray(['name' => 'my cv.pdf', 'size' => 120 * 1024])
        ->and($file['path'])->toStartWith('form-uploads/apply/')->not->toContain('my cv');
    Storage::disk('local')->assertExists($file['path']);

    $submission = Submission::query()->sole();
    get("/admin/cms/submissions/{$submission->id}/files/cv")->assertRedirect(); // signed out
    actingAs(adminUser(Panel::ACCESS));
    get("/admin/cms/submissions/{$submission->id}/files/cv")->assertForbidden();
    actingAs(adminUser(Panel::ACCESS, 'cms.submissions.view', 'cms.submissions.delete'));
    get('/admin/cms/forms/apply/submissions')->assertInertia(fn ($page) => $page
        ->where('submissions.data.0.rows.1.value', 'my cv.pdf')
        ->where('submissions.data.0.rows.1.file', route('sunrice.admin.cms.submissions.file', [$submission->id, 'cv'])));
    $download = get("/admin/cms/submissions/{$submission->id}/files/cv")->assertOk()->assertDownload('my cv.pdf');
    expect($download->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    get("/admin/cms/submissions/{$submission->id}/files/name")->assertNotFound();

    // Deleting the submission deletes its file.
    delete("/admin/cms/submissions/{$submission->id}");
    Storage::disk('local')->assertMissing($file['path']);
});

it('refuses files of other types, too big, or that could run as code', function () {
    $send = fn (UploadedFile $file) => from('/apply')->post('/forms/apply', ['name' => 'Ana', 'cv' => $file]);

    $send(UploadedFile::fake()->create('cv.txt', 10, 'text/plain'))->assertSessionHasErrors(['cv'], null, 'apply');
    $send(UploadedFile::fake()->create('cv.pdf', 500, 'application/pdf'))->assertSessionHasErrors(['cv'], null, 'apply');
    $send(UploadedFile::fake()->createWithContent('cv.php', '<?php echo 1;'))->assertSessionHasErrors(['cv'], null, 'apply');

    // Any type allowed: still never scripts.
    Blueprint::query()->where('handle', 'apply')->update(['fields' => [['handle' => 'name', 'type' => 'text'], ['handle' => 'cv', 'type' => 'file']]]);
    from('/apply')->post('/forms/apply', ['name' => 'Ana', 'cv' => UploadedFile::fake()->createWithContent('page.html', '<script>x</script>')])->assertSessionHasErrors(['cv'], null, 'apply');
    from('/apply')->post('/forms/apply', ['name' => 'Ana', 'cv' => UploadedFile::fake()->createWithContent('notes.txt', 'Hello')])->assertSessionHasNoErrors();
    expect(Submission::query()->count())->toBe(1);
});

it('goes to the form\'s own page once sent, and says so in JSON', function () {
    $this->form->update(['redirect_url' => '/thanks', 'message' => 'Got it!']);

    from('/apply')->post('/forms/apply', ['name' => 'Ana'])->assertRedirect('/thanks')->assertSessionHas('sunrice-cms.form.apply', 'Got it!');
    postJson('/forms/apply', ['name' => 'Budi'])->assertCreated()->assertJson(['message' => 'Got it!', 'redirect_url' => '/thanks']);
});

it('limits sends per visitor at the form\'s own rate', function () {
    $this->form->update(['per_minute' => 2]);
    Form::query()->create(['handle' => 'other', 'title' => 'Other', 'blueprint' => 'apply']);

    postJson('/forms/apply', ['name' => 'A'])->assertCreated();
    postJson('/forms/apply', ['name' => 'B'])->assertCreated();
    postJson('/forms/apply', ['name' => 'C'])->assertStatus(429);
    postJson('/forms/other', ['name' => 'D'])->assertCreated(); // its own count
});

it('asks for the captcha when the form wants it and the keys are set', function () {
    config(['sunrice-admin.captcha' => ['provider' => 'turnstile', 'site_key' => 'site-key', 'secret_key' => 'secret']]);
    Http::fake(['challenges.cloudflare.com/*' => fn ($request) => Http::response(['success' => $request['response'] === 'good'])]);
    $this->form->update(['captcha' => true]);

    expect(Blade::render('<x-sunrice-cms::form handle="apply" />'))
        ->toContain('<div class="cf-turnstile" data-sitekey="site-key" data-language="en"></div>')
        ->toContain('<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>');

    from('/apply')->post('/forms/apply', ['name' => 'Ana'])->assertSessionHasErrors(['_captcha'], null, 'apply');
    from('/apply')->post('/forms/apply', ['name' => 'Ana', 'cf-turnstile-response' => 'bad'])->assertSessionHasErrors(['_captcha'], null, 'apply');
    postJson('/forms/apply', ['name' => 'Ana', 'cf-turnstile-response' => 'bad'])->assertStatus(422)->assertJsonValidationErrors('_captcha');
    from('/apply')->post('/forms/apply', ['name' => 'Ana', 'cf-turnstile-response' => 'good'])->assertSessionHasNoErrors();
    expect(Submission::query()->sole()->data)->not->toHaveKey('cf-turnstile-response');

    // Without keys there's no captcha to ask for.
    config(['sunrice-admin.captcha.secret_key' => null]);
    from('/apply')->post('/forms/apply', ['name' => 'Budi'])->assertSessionHasNoErrors();
});

it('prunes old submissions with their files', function () {
    post('/forms/apply', ['name' => 'Old', 'cv' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
    $old = Submission::query()->sole();
    travel(40)->days();
    post('/forms/apply', ['name' => 'New']);

    artisan('model:prune', ['--model' => [Submission::class]])->assertSuccessful();
    expect(Submission::query()->count())->toBe(2); // kept forever by default

    config(['sunrice-cms.forms.prune_after_days' => 30]);
    artisan('model:prune', ['--model' => [Submission::class]])->assertSuccessful();
    expect(Submission::query()->pluck('data')->pluck('name')->all())->toBe(['New']);
    Storage::disk('local')->assertMissing($old->data['cv']['path']);
});

it('takes several files when the field allows, up to its limit', function () {
    Blueprint::query()->where('handle', 'apply')->update(['fields' => [
        ['handle' => 'name', 'type' => 'text'],
        ['handle' => 'photos', 'type' => 'file', 'required' => true, 'config' => ['types' => 'jpg', 'multiple' => true, 'max_files' => 3]],
    ]]);
    expect(Blade::render('<x-sunrice-cms::form handle="apply" />'))->toContain('name="data[photos][]"')->toContain(' multiple ');

    $photo = fn (string $name) => UploadedFile::fake()->image($name);
    from('/apply')->post('/forms/apply', ['data' => ['name' => 'Ana', 'photos' => [$photo('a.jpg'), $photo('b.jpg'), $photo('c.jpg'), $photo('d.jpg')]]])->assertSessionHasErrors(['photos'], null, 'apply');
    from('/apply')->post('/forms/apply', ['data' => ['name' => 'Ana', 'photos' => [$photo('a.jpg'), UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')]]])->assertSessionHasErrors(['photos'], null, 'apply');
    from('/apply')->post('/forms/apply', ['data' => ['name' => 'Ana', 'photos' => []]])->assertSessionHasErrors(['photos'], null, 'apply');
    from('/apply')->post('/forms/apply', ['data' => ['name' => 'Ana', 'photos' => [$photo('front.jpg'), $photo('garden.jpg')]]])->assertSessionHasNoErrors();

    $submission = Submission::query()->sole();
    $files = $submission->data['photos'];
    expect(array_column($files, 'name'))->toBe(['front.jpg', 'garden.jpg']);
    Storage::disk('local')->assertExists(array_column($files, 'path'));

    actingAs(adminUser(Panel::ACCESS, 'cms.submissions.view', 'cms.submissions.delete'));
    get('/admin/cms/forms/apply/submissions')->assertInertia(fn ($page) => $page
        ->where('submissions.data.0.rows.1.value', 'front.jpg, garden.jpg')
        ->where('submissions.data.0.rows.1.files.1', ['name' => 'garden.jpg', 'url' => route('sunrice.admin.cms.submissions.file', [$submission->id, 'photos', 'index' => 1])]));
    get("/admin/cms/submissions/{$submission->id}/files/photos?index=1")->assertOk()->assertDownload('garden.jpg');
    get("/admin/cms/submissions/{$submission->id}/files/photos?index=5")->assertNotFound();
    get("/admin/cms/submissions/{$submission->id}/files/photos")->assertNotFound();

    delete("/admin/cms/submissions/{$submission->id}");
    Storage::disk('local')->assertMissing(array_column($files, 'path'));
});
