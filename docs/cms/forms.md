# Forms

**Forms** take messages from visitors: a contact form, a quote request, a sign-up. A form's fields come from a blueprint; what visitors send is kept as **submissions**, emailed to the addresses you choose and exported as CSV.

## Setting up

1. Make a blueprint with the form's fields (Structure → Blueprints). Visitors get the `text`, `textarea`, `number`, `toggle`, `select`, `date` and `file` fields; fields of other types are left out of the form. Required fields and extra validation rules (like `email`) apply as for entries. A text field with the `email` rule, or with the handle `email`, is an email input, and the notification's replies go to it.
2. Under **Structure → Forms** (permissions `cms.forms.view`, `.create`, `.update`, `.delete`), add a form: a title, a handle that templates use and that can't change later, the blueprint, the addresses to email (one per line, at most 10), a thank-you message, and optionally a page to go to once it's sent (**After sending, go to**), how often one visitor may send it (**Sends per visitor a minute**), and whether it needs a [captcha](#captcha).

## On the site

```blade
<x-sunrice-cms::form handle="contact" />
<x-sunrice-cms::form handle="contact" submit="Send message" class="contact-form" />
```

The component prints the form with its labels, help texts and errors, the CSRF token and the honeypot, and the thank-you message once it's sent. Other attributes go on the `<form>`. It has no styles of its own: style it from your stylesheet, or publish the CMS's views to change its markup.

Forms post to `POST /forms/{handle}` (route `sunrice.cms.forms.submit`). After sending, visitors go back to the page they were on, with the thank-you message in the session (`session('sunrice-cms.form.contact')`); errors are in the form's own error bag (`$errors->contact`), so two forms on a page don't mix them up. Requests that ask for JSON get `201 {"message": "…"}` or `422` with the errors.

### Files

A **File (forms)** field takes one file per field. Its settings: **Accepted file types** (extensions, like `pdf, jpg, docx`; checked against the name and the content) and **Largest file** (KB; empty: `sunrice-cms.forms.upload_max_kb`, 10 MB). Files that could run as code (`.php`, `.html`, `.svg`, `.js`, `.exe` …) are always refused. PHP's own `upload_max_filesize` and `post_max_size` (and your web server's body limit) apply too.

Files are kept on the private disk `sunrice-cms.forms.upload_disk` (`local`, `SUNRICE_FORMS_DISK`) under `form-uploads/{handle}` with names of their own, never served to the public: people with `cms.submissions.view` download them from the submission. The submission keeps `{path, name, size, mime}`; the email and CSV show the name. Deleting a submission deletes its files. Entries don't use this field: use a media field.

### Captcha

A form can ask visitors to pass Cloudflare Turnstile (the default), Google reCAPTCHA (v2) or hCaptcha. Add the provider's keys to `.env` (the panel's `sunrice-admin.captcha` config):

```dotenv
SUNRICE_CAPTCHA_PROVIDER=turnstile   # or recaptcha, hcaptcha
SUNRICE_CAPTCHA_SITE_KEY=…
SUNRICE_CAPTCHA_SECRET_KEY=…
```

then turn on **Require a captcha** on the form (it stays off until both keys are set). The component prints the widget and the provider's script; the answer is checked before anything else, and a missing or wrong one comes back as the `_captcha` error ("Please confirm you're not a robot …"; `422` with JSON). When the provider can't be reached, the form is refused, not let through. Without the keys, no form asks for it.

Plugins use the same captcha: `app(\Sunrice\Admin\Security\Captcha::class)->widget()` and `->passes($request)`. Turnstile's test keys (site `1x00000000000000000000AA`, secret `1x0000000000000000000000000000000AA`) always pass.

### Spam

- Every form post must carry the honeypot fields ([spatie/laravel-honeypot](https://github.com/spatie/laravel-honeypot), which the component prints). Bots that fill in the hidden field, send too fast or leave the fields out get an empty page and nothing is kept. Configure it with the package's `honeypot` config.
- One visitor (IP address) may send a form 5 times a minute (`sunrice-cms.forms.per_minute`, or the form's own setting); more get `429`.
- A [captcha](#captcha), for forms that ask for it.

### After sending

Visitors go back to the page they were on, or to the form's **After sending, go to** page, with the thank-you message in the session. JSON requests get `201 {"message": "…", "redirect_url": "/thanks" | null}`.

## Submissions

Under **Content → Submissions** (`cms.submissions.view`): each form's submissions, newest first; open one to read all of it. **Export CSV** (`cms.submissions.export`, or `cms.submissions.{form}.export`) downloads them all, a column per field, with values spreadsheets would run as formulas escaped. Exports are recorded in the security log. `cms.submissions.delete` deletes them for good.

The notification email lists the fields with their labels. It's queued, so it's sent by your queue worker when the app has a queue, and right away when it doesn't.

In code:

```php
use Sunrice\Cms\Actions\Forms\SubmitForm;
use Sunrice\Cms\Models\Form;

$submission = app(SubmitForm::class)->handle(Form::query()->where('handle', 'contact')->sole(), $request->all(), 'en');
$submission->data;   // the values by field handle
```

`SubmitForm` validates against the form's fields (throwing a `ValidationException` keyed by field handle), ignores anything else that was sent, keeps the submission and emails it. `DeleteSubmission` deletes one.

### Keeping submissions

`SUNRICE_FORMS_PRUNE_DAYS=90` (`sunrice-cms.forms.prune_after_days`) deletes submissions older than that, with their files, every day (Laravel's scheduler runs `model:prune` for them; add `* * * * * php artisan schedule:run` to cron). Empty keeps them forever.
