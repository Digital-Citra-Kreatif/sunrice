# Forms

**Forms** take messages from visitors: a contact form, a quote request, a sign-up. A form's fields come from a blueprint; what visitors send is kept as **submissions**, emailed to the addresses you choose and exported as CSV.

## Setting up

1. Make a blueprint with the form's fields (Structure → Blueprints). Visitors get the `text`, `textarea`, `number`, `toggle`, `select` and `date` fields; fields of other types are left out of the form. Required fields and extra validation rules (like `email`) apply as for entries. A text field with the `email` rule, or with the handle `email`, is an email input, and the notification's replies go to it.
2. Under **Structure → Forms** (permissions `cms.forms.view`, `.create`, `.update`, `.delete`), add a form: a title, a handle that templates use and that can't change later, the blueprint, the addresses to email (one per line, at most 10) and a thank-you message.

## On the site

```blade
<x-sunrice-cms::form handle="contact" />
<x-sunrice-cms::form handle="contact" submit="Send message" class="contact-form" />
```

The component prints the form with its labels, help texts and errors, the CSRF token and the honeypot, and the thank-you message once it's sent. Other attributes go on the `<form>`. It has no styles of its own: style it from your stylesheet, or publish the CMS's views to change its markup.

Forms post to `POST /forms/{handle}` (route `sunrice.cms.forms.submit`). After sending, visitors go back to the page they were on, with the thank-you message in the session (`session('sunrice-cms.form.contact')`); errors are in the form's own error bag (`$errors->contact`), so two forms on a page don't mix them up. Requests that ask for JSON get `201 {"message": "…"}` or `422` with the errors.

### Spam

- Every form post must carry the honeypot fields ([spatie/laravel-honeypot](https://github.com/spatie/laravel-honeypot), which the component prints). Bots that fill in the hidden field, send too fast or leave the fields out get an empty page and nothing is kept. Configure it with the package's `honeypot` config.
- One visitor (IP address) may send 5 forms a minute; change it with `sunrice-cms.forms.per_minute`.

## Submissions

Under **Content → Submissions** (`cms.submissions.view`): each form's submissions, newest first; open one to read all of it. **Export CSV** downloads them all, a column per field, with values spreadsheets would run as formulas escaped. Exports are recorded in the security log. `cms.submissions.delete` deletes them for good.

The notification email lists the fields with their labels. It's queued, so it's sent by your queue worker when the app has a queue, and right away when it doesn't.

In code:

```php
use Sunrice\Cms\Actions\Forms\SubmitForm;
use Sunrice\Cms\Models\Form;

$submission = app(SubmitForm::class)->handle(Form::query()->where('handle', 'contact')->sole(), $request->all(), 'en');
$submission->data;   // the values by field handle
```

`SubmitForm` validates against the form's fields (throwing a `ValidationException` keyed by field handle), ignores anything else that was sent, keeps the submission and emails it. `DeleteSubmission` deletes one.
