# Good Bye Laravel Collective

[Laravel Collective](https://laravelcollective.com/docs/6.x/html) was great in their era. Now, their era has end and we should move forward by use the platform (Blade HTML from Laravel).

## About

These are a migration scripts to change `Html` and `Form` from Laravel Collective into Blade HTML. Thanks to GPT and minor tweaks from real human to make it happens.

## Caveats

Although the `Form::model` can be capture and converted into pure Blade HTML, the main challenge with `Form::model` is that it's more than just creating a form - it's also about pre-filling form fields with model values. The standard HTML form doesn't have a direct equivalent to this binding functionality, so your converted form fields will need to manually include the model values.

## How to?

1. Run command below.

```sh
php artisan make:command MigrateFormCollective
php artisan make:command MigrateHtmlCollective
```

Those commands will generate files `MigrateFormCollective.php` and `MigrateHtmlCollective.php` inside the `app/Console/Commands` directory.

2. Copy paste the code program in this repository and put them in the respective files.
Migrate Laravel Collective syntax into Blade HTML.

3. Run command below in order to make migration works.

```sh
php artisan app:migrate-html-collective
php artisan app:migrate-form-collective
```
