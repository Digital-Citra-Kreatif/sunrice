# sunrice/starter-kit

Starter sites for [`sunrice/cms`](https://github.com/Digital-Citra-Kreatif/sunrice/tree/main/packages/cms): install a whole site (structure, sample content, media, templates and assets) in one command, and make your own starters from a site.

Developed in [Digital-Citra-Kreatif/sunrice](https://github.com/Digital-Citra-Kreatif/sunrice); this package's own repository is a read-only copy. In development, before 1.0.

```bash
composer require sunrice/starter-kit
php artisan sunrice:starters                     # list them
php artisan sunrice:starter blog --dry-run       # what installing would do
php artisan sunrice:starter blog                 # install the example blog
php artisan sunrice:make-starter my-site --public=site   # make one from this site
```

The guide, [docs/starters.md](docs/starters.md), covers what a starter is, installing one, making one, writing a good one, shipping one as a Composer package and testing it.
