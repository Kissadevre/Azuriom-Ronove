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

Providers may additionally implement `FilterableResourceProvider` to enable search and coverage-status filters in the translation center:

```php
use Azuriom\Plugin\Ronove\Contracts\FilterableResourceProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

public function applySearch(Builder $query, string $search): Builder
{
    return $query->where('name', 'like', '%'.$search.'%');
}

public function applyResourceKeys(Builder $query, Collection $keys): Builder
{
    return $query->whereKey($keys);
}
```

This contract is optional. Providers that only implement `ResourceProvider` remain compatible and continue to use the regular paginated listing.

Render a translated value explicitly without mutating the source record:

```php
$name = app('ronove')->translate('projects.project', $project, 'name');
```

Resolve every registered field at once, or apply published translations to a collection that is about to be rendered:

```php
$values = app('ronove')->translatedValues('projects.project', $project);

app('ronove')->overlay('projects.project', $projects, 'es_ES');
```

`overlay()` changes only the in-memory Eloquent instances supplied by the caller. It never persists translated values to the integrating plugin's tables.

Coverage for an enabled language is also available to integrations:

```php
$coverage = app('ronove')->coverage('projects.project', 'es_ES');

$coverage->total();
$coverage->count('missing');
$coverage->count('draft');
$coverage->count('published');
$coverage->count('outdated');
$coverage->keysFor('outdated');
```

A translation is outdated when its saved source hash no longer matches the provider's current original visible fields. This status is informative: published translations remain publicly available until a human reviews and saves them again. `outdated` can overlap `draft` or `published`; it is not a third persistence status.

Published values resolve independently per field in this order: selected locale, its configured regional fallback chain, Azuriom global locale, original value. Drafts are never shown publicly, including drafts stored in fallback languages.

## Regional fallbacks

Administrators configure regional fallback chains from Ronove's Languages page. Each enabled locale can point to another enabled locale, for example `es_MX` to `es_ES`. Chains may contain multiple levels, but Ronove rejects self-references and cycles.

The global Azuriom locale is always tried after the configured regional chain. A defensive cycle guard is also applied while resolving content in case locale records were modified outside Ronove. The same chain is installed in Laravel's translator for Azuriom and compatible plugin language files, so interface strings and translated content follow consistent rules.

Disabling a locale clears fallbacks owned by it and references pointing to it. Existing source content and translations are never changed.

## Preview and comparison

Every translation editor includes a field-by-field comparison between the original text and the value visitors would receive if the current form were published. Each resolved value identifies its source as the selected locale, a regional fallback, Azuriom's global locale, or the original text.

The **Update preview** action accepts the current unsaved form values, renders rich text and Markdown with their corresponding presentation, and applies the public fallback chain without writing to the database, changing publication status, adding action logs, or dispatching translation events.

## Glossary and internal notes

The translation center links to a human-maintained glossary. Terms are stored for one target locale and can be global or scoped to a registered integration. An integration-specific term is therefore available to all of that integration's resource providers without leaking into unrelated plugins.

When an original resource contains a glossary term, its preferred translation and optional usage context appear in the translation editor. Matching is case-insensitive, but glossary entries are advisory: Ronove never inserts or replaces text automatically.

Each resource and target locale can also have one internal note. Notes are stored independently from translations, remain private to administrators, and do not create drafts, affect coverage, participate in fallbacks, or appear on public pages. Deleting the source resource removes its notes through the same Ronove resource lifecycle.

## Integration events

Plugins may listen to the following public events:

- `TranslationSaved`: dispatched after every successful translation save with `resourceType`, `resourceKey`, `locale`, `status`, `values`, and `previousStatus`.
- `TranslationPublished`: dispatched only when a translation enters the `published` status. Re-saving an already published translation does not emit it again.
- `TranslationDeleted`: dispatched after an existing translation is deleted with its previous status.
- `LocaleChanged`: dispatched after a visitor's preference is persisted, with the selected locale and the authenticated user ID when available.

The event payloads contain stable scalar values and arrays instead of mutable Eloquent models. A listener can therefore decide whether an event belongs to its registered resource type without depending on Ronove's internal storage models:

```php
use Azuriom\Plugin\Ronove\Events\TranslationPublished;
use Illuminate\Support\Facades\Event;

Event::listen(TranslationPublished::class, function (TranslationPublished $event) {
    if ($event->resourceType !== 'projects.project') {
        return;
    }

    // React to the newly published human translation.
});
```

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
        "ronove": ">=0.9.0"
    }
}
```

Ronove stores only translated alternatives and source hashes. Deleting or disabling a locale does not modify the original resource.

Ronove intentionally has no automatic or machine-translation workflow. Translation text is authored and reviewed by administrators so wording, voice, and context remain under human control.

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
