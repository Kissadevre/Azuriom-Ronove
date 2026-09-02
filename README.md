# Ronove

Ronove provides per-visitor locales and optional translated alternatives for visible Azuriom content. Original records remain the source of truth: Ronove never changes slugs, routes, or SEO-only fields.

## Registering plugin content

An integrating plugin first registers its translation integration and then associates one or more resource providers with it:

```php
use Azuriom\Plugin\Ronove\Facades\Ronove;

Ronove::registerIntegration(
    id: 'projects',
    name: 'projects::admin.title',
    icon: 'bi bi-kanban',
    permission: 'projects.admin',
);

Ronove::registerResourceType(new ProjectResourceProvider(), 'projects');
Ronove::registerResourceType(new ChangelogResourceProvider(), 'projects');
```

Integration IDs are permanent public identifiers. Register the integration before its providers. The name can be a translation key or a literal label. Its optional permission protects the complete integration in addition to each provider's own permission.

Ronove displays integrations as cards in its translation center and displays multiple providers from one integration as tabs. Providers registered without an integration remain compatible and appear under the built-in `other` group.

An external plugin may optionally add a shortcut in its own admin menu. That shortcut should use a plugin-owned named route which redirects to `ronove.admin.translations.integration` with the integration ID; translation controllers and storage must remain owned by Ronove:

```php
return to_route('ronove.admin.translations.integration', 'projects');
```

The provider implements `Azuriom\Plugin\Ronove\Contracts\ResourceProvider` and defines a stable type, fields, listing query, lookup, and original values. Types and field names are public identifiers and should not be renamed after release.

Supported field definitions are available through `TranslatableField`:

```php
return [
    'name' => TranslatableField::text('Name', 150),
    'summary' => TranslatableField::textarea('Summary', 500),
    'content' => TranslatableField::markdown('Content'),
];
```

Providers can also use `richText()` when their original field is trusted HTML managed by an administrative rich-text editor. The provider must declare its Eloquent model class through `model()` so Ronove can reject mismatched resources.

Render a translated value explicitly without mutating the source record:

```php
$name = app('ronove')->translate('projects.project', $project, 'name');
```

Published values resolve per field in this order: selected locale, Azuriom global locale, original value. Drafts are never shown publicly.

## Language selector integration

Ronove owns locale resolution and persistence while the active theme owns the selector's placement and presentation. This keeps locale behavior consistent without coupling Ronove to a particular navbar.

### Universal page

The `ronove.index` route displays a standalone language page. It is registered in Azuriom's plugin route descriptions, so administrators can add it to the navbar without editing a theme.

### Reusable theme views

Bootstrap-compatible themes can render Ronove's accessible dropdown wherever their navigation expects list items:

```blade
@include('ronove::language-selector')
```

The dropdown displays the current language by default. Compact navbars can keep only the icon:

```blade
@include('ronove::language-selector', ['showCurrentName' => false])
```

Because the selector renders a `<li>`, place it inside a navbar `<ul>`. A grid of language buttons is also available for menus, footers, or account pages:

```blade
@include('ronove::language-buttons')
```

An active Azuriom theme can replace either view without changing Ronove. For example:

```text
resources/themes/my-theme/views/plugins/ronove/language-selector.blade.php
resources/themes/my-theme/views/plugins/ronove/language-buttons.blade.php
```

### Custom theme API

Themes that need custom markup can consume presentation-safe options without depending on Ronove's database models:

```blade
@php($languages = app('ronove')->languageOptions())

@foreach($languages as $language)
    <form action="{{ app('ronove')->languageUpdateUrl() }}" method="POST">
        @csrf
        <input type="hidden" name="locale" value="{{ $language->code }}">
        <button type="submit" @if($language->isCurrent) aria-current="true" @endif>
            {{ $language->nativeName }}
        </button>
    </form>
@endforeach
```

Each option exposes `code`, `name`, `nativeName`, and `isCurrent`. The current option is also available through `app('ronove')->currentLanguage()`. Locale changes must use the POST endpoint returned by `languageUpdateUrl()` so CSRF validation and guest or authenticated persistence remain centralized in Ronove.

Themes must not write Ronove cookies or user preferences directly.

## Plugin integration

An integrating plugin should include the following manifest dependency so it cannot be enabled before Ronove:

```json
{
    "dependencies": {
        "ronove": ">=0.5.0"
    }
}
```

Ronove stores only translated alternatives and source hashes. Deleting or disabling a locale does not modify the original resource.

## Built-in Azuriom content

Ronove registers the following Core providers under the `core` integration:

- `core.post`: visible news title and content.
- `core.page`: visible page title and content. Page descriptions, slugs, routes, restrictions, and attachments remain original.
- `core.site-message`: home, welcome, and maintenance HTML messages.
- `core.registration-conditions`: inline registration conditions written as Markdown. URL-based conditions remain original and are not offered for translation.
- `core.footer`: the visible copyright text.

General settings are overlaid only for the current public request. Ronove never updates Azuriom's source settings and never overlays translated settings inside the administration panel. Site name, SEO description, keywords, URLs, navbar labels, server names, and social network names are intentionally excluded.

Integrations must remove orphaned Ronove records from their model deletion event:

```php
Project::deleted(fn (Project $project) => app('ronove')->forget('projects.project', $project));
```
