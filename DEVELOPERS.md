# Ronove 1.0 developer integration guide

This document is the public integration contract for Azuriom plugins, themes, and automated coding agents that need to work with Ronove.

Ronove has two complementary responsibilities:

1. It selects a locale independently for every visitor or authenticated user and applies that locale to Laravel language files.
2. It stores human-authored alternatives for visible database content registered by Azuriom or another plugin.

The original model remains the source of truth. Ronove does not translate or replace slugs, routes, identifiers, permissions, SEO metadata, or other operational data.

## Compatibility and dependency

An integrating plugin should declare Ronove as a required dependency in its `plugin.json`:

```json
{
    "dependencies": {
        "ronove": ">=1.0.0"
    }
}
```

This guarantees that Ronove's service provider, container binding, facade, routes, tables, and middleware are available before the integration is used. A required dependency is strongly preferred over optional `class_exists` checks because an unavailable provider would make the translated resource type disappear from the administration panel.

The public PHP namespaces used by integrations are:

```text
Azuriom\Plugin\Ronove\Contracts\FilterableResourceProvider
Azuriom\Plugin\Ronove\Contracts\ResourceProvider
Azuriom\Plugin\Ronove\Events\*
Azuriom\Plugin\Ronove\Facades\Ronove
Azuriom\Plugin\Ronove\RonoveManager
Azuriom\Plugin\Ronove\Support\LocaleOption
Azuriom\Plugin\Ronove\Support\TranslatableField
Azuriom\Plugin\Ronove\Support\TranslationCoverageReport
```

Integrations should use these contracts and APIs instead of querying Ronove's internal models or database tables.

## Choose the correct integration mechanism

| Requirement | Correct mechanism |
| --- | --- |
| Translate static interface text from Blade, controllers, validation, or notifications | Normal Laravel/Azuriom language files and `trans()` or `__()` |
| Translate visible fields stored on an Eloquent model | Register a Ronove `ResourceProvider` |
| Translate multiple visible settings represented by `Azuriom\Models\Setting` | Register a provider with a stable key for each setting |
| Add a language selector to an existing Bootstrap navbar | Include `ronove::language-selector` |
| Add language choices to a page, footer, or mobile menu | Include `ronove::language-buttons` |
| Build completely custom selector markup | Use `app('ronove')->languageOptions()` and `languageUpdateUrl()` |
| Render one translated field | Use `translate()` |
| Render several translated fields from one model | Use `translatedValues()` |
| Localize a collection without N+1 Ronove queries | Use `overlay()` |
| React to translation lifecycle changes | Listen to Ronove events |
| Inspect translation completeness | Use `coverage()` |

Do not register a database resource provider for text that already belongs in a plugin language file.

## Resolution model

For each visible field, Ronove resolves values independently in this order:

```text
selected visitor locale
    -> configured regional fallback(s)
        -> Azuriom original value
```

Azuriom's globally configured locale represents the original content language. It appears to visitors as the `Original (...)` option and is not stored as a second Ronove translation target.

Only a published or previously approved value can be returned publicly. Empty fields fall through to the next language or to the original value. A pending edit never replaces the last approved public version.

Ronove's web middleware also configures Laravel's translator with the same selected locale and regional fallback chain. Consequently, compatible language files and registered database content follow the same visitor preference.

## Complete plugin integration

The following example integrates a fictional `projects` plugin whose `Project` model exposes `name`, `summary`, and `content` as visible translated fields.

### 1. Create a resource provider

```php
<?php

namespace Azuriom\Plugin\Projects\Ronove;

use Azuriom\Plugin\Projects\Models\Project;
use Azuriom\Plugin\Ronove\Contracts\FilterableResourceProvider;
use Azuriom\Plugin\Ronove\Contracts\ResourceProvider;
use Azuriom\Plugin\Ronove\Support\TranslatableField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class ProjectResourceProvider implements FilterableResourceProvider, ResourceProvider
{
    public function type(): string
    {
        return 'projects.project';
    }

    public function model(): string
    {
        return Project::class;
    }

    public function label(): string
    {
        return trans('projects::admin.projects.title');
    }

    public function permission(): ?string
    {
        return 'projects.admin';
    }

    public function fields(): array
    {
        return [
            'name' => TranslatableField::text(
                trans('projects::admin.fields.name'),
                150,
            ),
            'summary' => TranslatableField::textarea(
                trans('projects::admin.fields.summary'),
                500,
            ),
            'content' => TranslatableField::markdown(
                trans('projects::admin.fields.content'),
            ),
        ];
    }

    public function query(): Builder
    {
        return Project::query()->latest('updated_at')->latest('id');
    }

    public function find(string $key): ?Model
    {
        return Project::query()->find($key);
    }

    public function key(Model $resource): string
    {
        return (string) $resource->getKey();
    }

    public function title(Model $resource): string
    {
        return (string) $resource->getRawOriginal('name');
    }

    public function original(Model $resource, string $field): ?string
    {
        $value = $resource->getRawOriginal($field);

        return $value === null ? null : (string) $value;
    }

    public function applySearch(Builder $query, string $search): Builder
    {
        return $query->where('name', 'like', '%'.$search.'%');
    }

    public function applyResourceKeys(Builder $query, Collection $keys): Builder
    {
        return $query->whereKey($keys);
    }
}
```

The provider is an adapter. It tells Ronove how to list and identify original records; it does not own translation persistence.

### 2. Register the integration and provider

Register one integration group before registering any providers assigned to it. A plugin with several resource types should put them under the same integration.

```php
use Azuriom\Plugin\Projects\Ronove\ProjectResourceProvider;
use Azuriom\Plugin\Ronove\Facades\Ronove;

public function boot(): void
{
    Ronove::registerIntegration(
        id: 'projects',
        name: 'projects::admin.ronove.title',
        icon: 'bi bi-kanban',
        permission: 'projects.admin',
        order: 50,
    );

    Ronove::registerResourceType(
        new ProjectResourceProvider(),
        'projects',
    );
}
```

The integration becomes a card in Ronove's Translation center. Multiple registered providers become tabs inside that integration.

Registration rules are strict:

- Integration IDs and resource types must match `^[a-z0-9]+(?:[._-][a-z0-9]+)*$`.
- IDs are permanent public identifiers. Do not rename them after release.
- A provider type may be registered only once.
- The integration must exist before its providers are registered.
- `model()` must return an Eloquent model class.
- A provider must expose at least one field.
- Field identifiers must match `^[a-z][a-z0-9_]*$`.
- Every field value must be a `TranslatableField`.

Providers registered without an explicit integration are placed in Ronove's built-in `other` group. Explicit integrations are recommended for released plugins.

### 3. Remove Ronove data with the source model

When the original resource is permanently deleted, remove its Ronove container:

```php
use Azuriom\Plugin\Projects\Models\Project;

Project::deleted(function (Project $project): void {
    app('ronove')->forget('projects.project', $project);
});
```

For a model using Laravel `SoftDeletes`, decide whether translations should survive restoration:

- Use `deleted` when a soft-deleted resource must immediately lose its translations.
- Use `forceDeleted` when translations should survive soft deletion and only disappear after permanent deletion.

`forget()` deletes only Ronove's container for the exact provider type and stable key. Database cascades then remove its translations, internal notes, and revision history. It never deletes the original plugin model.

### 4. Render translated values

For a single field:

```php
$name = app('ronove')->translate(
    'projects.project',
    $project,
    'name',
);
```

For all registered fields on one model:

```php
$translated = app('ronove')->translatedValues(
    'projects.project',
    $project,
);

return view('projects::show', [
    'project' => $project,
    'translated' => $translated,
]);
```

```blade
<h1>{{ $translated['name'] }}</h1>
<p>{{ $translated['summary'] }}</p>
```

For a collection or paginator, prefer `overlay()` so Ronove loads all matching translation records in one batch:

```php
$projects = Project::query()
    ->where('is_published', true)
    ->latest()
    ->paginate(12);

app('ronove')->overlay(
    'projects.project',
    $projects->getCollection(),
);

return view('projects::index', compact('projects'));
```

`overlay()` modifies only the in-memory attributes of the provided models. Never call `save()`, `update()`, or another persistence method on those overlaid instances. Fetch a fresh model before performing a later write.

All three APIs accept an optional explicit locale:

```php
$values = app('ronove')->translatedValues(
    'projects.project',
    $project,
    'es_MX',
);
```

When omitted, they use the locale already selected by Ronove's middleware for the current request.

### 5. Add an optional administration shortcut

Ronove owns the translation controllers, validation, permissions, revision history, and storage. An integrating plugin may expose a shortcut but should not copy those controllers.

```php
use Illuminate\Support\Facades\Route;

Route::get('/translations', function () {
    return to_route('ronove.admin.translations.integration', [
        'integration' => 'projects',
    ]);
})->name('translations');
```

The integrating plugin can point its own administration navigation item at that plugin-owned route.

## ResourceProvider reference

### `type(): string`

Returns the stable global resource type, normally `<plugin-id>.<singular-resource>`, for example `projects.project` or `projects.changelog`.

This identifier is stored with translations. Changing it disconnects existing data.

### `model(): class-string<Model>`

Returns the exact Eloquent class accepted by the provider. Ronove rejects model instances of another class before translating them.

### `label(): string`

Returns the human-readable content-type label used in the administration interface. Returning a translated label is recommended.

### `permission(): ?string`

Returns an optional Azuriom permission required to manage this provider. Ronove enforces both the integration permission and the provider permission.

Use `null` only when the general `ronove.translations` permission is sufficient. This method protects administration access; it does not change whether already published content can be displayed publicly.

### `fields(): array`

Maps permanent field identifiers to `TranslatableField` definitions. Only visible presentation text belongs here.

Do not expose:

- slugs or route fragments;
- database IDs, UUIDs, or foreign keys;
- permission names or role identifiers;
- SEO descriptions, keywords, or canonical metadata;
- file paths, class names, or configuration keys;
- booleans, dates, prices, stock, or other operational values;
- server, social-network, or brand names whose identity must remain unchanged.

### `query(): Builder`

Returns the base query for the resources visible in Ronove's administration interface and coverage report. Apply deterministic ordering. Scope out records that should never be translated.

Do not execute the query in this method and do not return a collection.

### `find(string $key): ?Model`

Looks up one source resource from the stable key. Return `null` for an invalid, removed, or inaccessible resource.

### `key(Model $resource): string`

Returns a stable string no longer than 100 characters. A database primary key or immutable UUID is ideal. Never use a localized title or mutable slug.

### `title(Model $resource): string`

Returns the source label shown to administrators while choosing a resource. It must come from the original model rather than from a Ronove overlay.

For ordinary attributes, prefer `getRawOriginal()`.

### `original(Model $resource, string $field): ?string`

Returns the original visible value for one registered field. This value is used for display, fallback, comparison, and the source hash that identifies outdated translations.

It must be deterministic and must not call Ronove translation APIs; doing so creates recursive or unstable source hashes. For Eloquent attributes that may have been overlaid, use `getRawOriginal()`.

## FilterableResourceProvider reference

Implementing this optional contract enables search plus coverage and review-state filtering.

### `applySearch(Builder $query, string $search): Builder`

Modify and return the supplied base query. Search only appropriate original fields and keep the query parameterized through Eloquent.

### `applyResourceKeys(Builder $query, Collection $keys): Builder`

Restrict the supplied query to the exact provider keys calculated by Ronove. An empty collection must return no resources; never interpret it as "do not filter."

If `key()` does not use the primary key, replace `whereKey()` with a `whereIn()` against the stable key column:

```php
public function applyResourceKeys(Builder $query, Collection $keys): Builder
{
    return $query->whereIn('uuid', $keys);
}
```

## Translatable field types

```php
TranslatableField::text('Name', 150);
TranslatableField::textarea('Summary', 500);
TranslatableField::markdown('Documentation');
TranslatableField::richText('Content');
```

| Type | Administration editor | Intended content |
| --- | --- | --- |
| `text` | Single-line input | Titles, names, concise labels |
| `textarea` | Plain multiline textarea | Plain summaries or descriptions |
| `markdown` | Markdown textarea | Human-authored Markdown |
| `rich_text` | Azuriom rich-text editor | Trusted administrator-authored HTML |

`text()` and `textarea()` accept an optional positive maximum length. Ronove enforces this maximum server-side.

The field definition controls the translation editor, not the integrating plugin's public renderer. Render returned values according to the same trust and formatting rules as the original field:

- Escape plain text with Blade `{{ ... }}`.
- Pass Markdown through the same Markdown renderer used for the original value.
- Render rich HTML unescaped only when the original field is also trusted administrator-authored HTML.
- Never change a plain field to raw HTML merely because its value came from Ronove.

## Public manager API

The manager is available through either the container or the facade:

```php
$ronove = app('ronove');

use Azuriom\Plugin\Ronove\Facades\Ronove;
```

### Registration and inspection

```php
$ronove->registerIntegration(
    id: 'projects',
    name: 'projects::admin.ronove.title',
    icon: 'bi bi-kanban',
    permission: 'projects.admin',
    order: 50,
);

$ronove->registerResourceType(new ProjectResourceProvider(), 'projects');

$registry = $ronove->resources();
$provider = $registry->get('projects.project');
```

`name` may be a Laravel translation key or a literal label. `icon` uses the Bootstrap Icons class convention. Lower `order` values appear first.

### Language presentation API

```php
$options = $ronove->languageOptions();
$current = $ronove->currentLanguage();
$updateUrl = $ronove->languageUpdateUrl();
```

Each `LocaleOption` exposes:

```php
$option->code;        // "es_MX"
$option->name;        // Administrative name
$option->nativeName;  // Visitor-facing name
$option->isCurrent;   // bool
$option->flagCode;    // "MX" or null
```

Its JSON representation uses:

```json
{
    "code": "es_MX",
    "name": "Spanish (Mexico)",
    "native_name": "Español (México)",
    "is_current": true,
    "flag_code": "MX"
}
```

The option collection includes Azuriom's global language as `Original (...)` followed by every enabled Ronove target language. The original option has no Ronove database translation and normally uses the globe fallback instead of a country flag.

### Runtime settings

```php
$reviewRequired = $ronove->reviewWorkflowEnabled();
$publicPageVisible = $ronove->publicLanguagePageEnabled();
```

Disabling the public page makes `GET /ronove` return 404. It does not disable `POST /ronove/locale`, theme selectors, locale persistence, or translation resolution.

### Content resolution

```php
$value = $ronove->translate($type, $model, $field, $locale = null);
$values = $ronove->translatedValues($type, $model, $locale = null);
$ronove->overlay($type, $models, $locale = null);
```

`translate()` throws an `InvalidArgumentException` for an unknown registered field or type. All content methods validate the model class against the provider.

### Coverage

```php
$coverage = $ronove->coverage('projects.project', 'es_ES');

$coverage->total();
$coverage->count('missing');
$coverage->count('draft');
$coverage->count('published');
$coverage->count('outdated');
$coverage->status((string) $project->getKey());
$coverage->isOutdated((string) $project->getKey());
$coverage->keysFor('outdated');
```

The locale must be an enabled Ronove translation target. `outdated` overlaps the persistence states: a translation can be published and outdated simultaneously. Outdated content remains public until a human saves and publishes an updated version.

Coverage loads the provider's complete base query. Avoid invoking it repeatedly in a loop or on a high-traffic public request.

### Cleanup

```php
$ronove->forget('projects.project', $project);
```

Use this only when the matching source resource is being deleted permanently.

## Theme integration

Ronove owns language resolution and preference persistence. The theme owns placement and visual design.

### Bootstrap navbar dropdown

Place the reusable selector inside a navbar `<ul>` because it renders a complete `<li>`:

```blade
<ul class="navbar-nav ms-auto">
    @include('ronove::language-selector')
</ul>
```

For a compact icon-only navbar:

```blade
<ul class="navbar-nav ms-auto">
    @include('ronove::language-selector', ['showCurrentName' => false])
</ul>
```

The packaged selector uses Bootstrap 5 dropdown classes, Bootstrap Icons, native language names, configured country flags, CSRF-protected forms, `aria-current`, and visually hidden accessible labels.

### Button grid

For a settings page, footer, off-canvas menu, or custom language page:

```blade
@include('ronove::language-buttons')
```

The partial optionally accepts a prepared `languageOptions` collection:

```blade
@include('ronove::language-buttons', [
    'languageOptions' => app('ronove')->languageOptions(),
])
```

### Fully custom selector

```blade
@php($languages = app('ronove')->languageOptions())

<div class="language-picker" aria-label="{{ trans('ronove::messages.available_languages') }}">
    @foreach($languages as $language)
        <form action="{{ app('ronove')->languageUpdateUrl() }}" method="POST">
            @csrf
            <input type="hidden" name="locale" value="{{ $language->code }}">

            <button
                type="submit"
                class="btn {{ $language->isCurrent ? 'btn-primary' : 'btn-outline-primary' }}"
                @if($language->isCurrent) aria-current="true" @endif
            >
                {{ $language->nativeName }}
            </button>
        </form>
    @endforeach
</div>
```

Required custom-selector behavior:

- Submit a `POST` request to `languageUpdateUrl()`.
- Include a valid CSRF token.
- Send one `locale` value from `languageOptions()`.
- Let Ronove redirect back to the originating page.
- Mark the current choice accessibly.
- Display `nativeName` to visitors.
- Treat `flagCode` as optional presentation metadata, never as the locale identifier.

Do not write the `ronove_locale` cookie, session key, or user-preference table directly. Ronove centralizes validation, cookie lifetime, authenticated persistence, and `LocaleChanged` dispatch.

### Theme view overrides

An active Azuriom theme may replace the reusable views without modifying Ronove:

```text
resources/themes/<theme-id>/views/plugins/ronove/language-selector.blade.php
resources/themes/<theme-id>/views/plugins/ronove/language-buttons.blade.php
```

Keep the POST/CSRF contract intact when overriding markup. A theme may also create its own independent partial using the public manager API.

### Standalone public page

When enabled, `GET /ronove` displays the packaged button grid. Administrators may disable that page and rely exclusively on theme placement. A theme integration must therefore use `languageUpdateUrl()` for selection and must not assume that the standalone page is available.

## Laravel language files

Static plugin interface text requires no resource provider. Ship ordinary localized files in the integrating plugin:

```text
plugins/projects/resources/lang/en/messages.php
plugins/projects/resources/lang/es_ES/messages.php
plugins/projects/resources/lang/es_MX/messages.php
```

Then use the normal namespaced helpers:

```php
trans('projects::messages.project_created');
__('projects::messages.project_created');
```

Ronove sets `app()->getLocale()` for each web request and configures Laravel's translator fallback chain. If `es_MX` lacks a key and its configured fallback is `es_ES`, Laravel can resolve the `es_ES` key before falling back to Azuriom's original locale.

Use language files for fixed interface strings and a `ResourceProvider` for administrator-created database text. A page can use both mechanisms at the same time.

## Locale preference lifecycle

Ronove resolves a request locale in this priority order:

1. Authenticated user's stored Ronove preference.
2. Current session preference.
3. Ronove cookie.
4. Browser `Accept-Language`, including a language-only match.
5. Azuriom's globally configured locale.

Selecting a Ronove target stores the session and one-year cookie. For an authenticated user it also stores the preference by user ID. Selecting the original language removes the authenticated preference so Azuriom's global language remains the source of truth.

This selection never updates Azuriom's global `locale` setting and never changes another visitor's language.

## Review workflow and published snapshots

The optional workflow has four review states:

```text
draft -> pending -> approved
                   -> changes_requested -> draft -> pending
```

When review is disabled, a user with `ronove.publish` can publish directly. When enabled, a translator saves a draft or submits it, and approval requires both `ronove.review` and `ronove.publish`.

Revision history is always active, even when the workflow is disabled. If an already approved translation is edited, Ronove stores the proposal separately from its last published snapshot. Visitors continue seeing the approved snapshot until the proposal is approved.

Plugins should not reproduce workflow state or revision tables. Listen to public events when external behavior is required.

## Public events

All payload properties are readonly scalars or arrays. They intentionally do not expose mutable Eloquent models.

| Event | When dispatched | Payload |
| --- | --- | --- |
| `LocaleChanged` | A visitor selects a valid option, including Original | `locale`, `userId` |
| `TranslationSaved` | A translation save succeeds | `resourceType`, `resourceKey`, `locale`, `status`, `values`, `previousStatus` |
| `TranslationPublished` | Direct publication first occurs or a reviewed proposal is approved | `resourceType`, `resourceKey`, `locale`, `values`, `previousStatus` |
| `TranslationDeleted` | An existing translation is deleted | `resourceType`, `resourceKey`, `locale`, `previousStatus` |
| `TranslationSubmittedForReview` | A proposal enters pending review | `resourceType`, `resourceKey`, `locale`, `userId` |
| `TranslationReviewCompleted` | A reviewer approves or requests changes | `resourceType`, `resourceKey`, `locale`, `decision`, `feedback`, `reviewerId` |

`status` and `previousStatus` use `draft` or `published`. `TranslationReviewCompleted::$decision` uses `approved` or `changes_requested`.

Example listener:

```php
use Azuriom\Plugin\Ronove\Events\TranslationPublished;
use Illuminate\Support\Facades\Event;

public function boot(): void
{
    Event::listen(TranslationPublished::class, function (TranslationPublished $event): void {
        if ($event->resourceType !== 'projects.project') {
            return;
        }

        cache()->forget('projects.public.'.$event->resourceKey.'.'.$event->locale);
    });
}
```

Listeners performing network requests or expensive work should dispatch a queued job. Do not make translation publication depend on an unreliable external service.

## Permissions

Ronove registers these permissions:

```text
ronove.settings
ronove.translations
ronove.publish
ronove.review
ronove.audit
```

An external integration can add two more authorization layers:

1. `registerIntegration(..., permission: 'projects.admin')` protects the entire integration.
2. `ResourceProvider::permission()` protects one provider inside that integration.

Ronove applies its own permissions and both external layers to administration routes. Use existing plugin permissions rather than inventing duplicate Ronove-specific roles in the integrating plugin.

## Storage ownership and safety boundaries

Ronove owns all `ronove_*` tables. Integrating plugins and themes must not:

- create, modify, or drop Ronove tables;
- write translations, locales, fallbacks, notes, glossary terms, or revisions directly;
- depend on internal Ronove model IDs;
- copy Ronove controllers or validation rules;
- create alternate locale cookies or preference tables;
- mutate the original model to persist a translated value;
- add automatic or machine-generated translations on behalf of Ronove;
- translate routes, slugs, SEO metadata, or identifiers.

The single migration shipped in Ronove 1.0 is the immutable public baseline. After the 1.0 release, every Ronove schema change must be a new, dated, reversible migration so existing installations can upgrade without rebuilding tables or losing translations.

MariaDB-compatible migration rules include explicit index and foreign-key names, matching unsigned key types, portable strings instead of database-specific enums, and reverse-order table removal.

## Testing an integrating plugin

At minimum, add coverage for the following behaviors:

1. The integration and every provider register without exceptions.
2. Duplicate or malformed identifiers are not introduced.
3. Original raw values are returned by the provider before any overlay.
4. A published translation renders for the selected locale.
5. An empty field falls back without hiding valid original content.
6. A draft or pending proposal is not exposed publicly.
7. A collection overlay does not persist translated values to source tables.
8. Search and key filters return only the intended resources.
9. An empty key collection returns no resources.
10. Permanent model deletion removes its Ronove data.
11. Existing plugin authorization still protects the integration.
12. Plain text, Markdown, and trusted HTML keep their original escaping model.

Useful registration assertions:

```php
$registry = app('ronove')->resources();

$this->assertTrue($registry->hasIntegration('projects'));
$this->assertTrue($registry->has('projects.project'));
$this->assertSame(
    'projects',
    $registry->integrationFor('projects.project')->id,
);
```

Useful non-persistence assertion after `overlay()`:

```php
$original = $project->getRawOriginal('name');

app('ronove')->overlay('projects.project', [$project], 'es_ES');

$this->assertNotSame($original, $project->name);
$this->assertSame($original, $project->fresh()->name);
```

## Automation guide for Codex and other coding agents

An automated integration should follow this sequence without skipping discovery.

### Required discovery

Before editing, identify:

```yaml
plugin_id: projects
plugin_namespace: Azuriom\\Plugin\\Projects
service_provider: src/Providers/ProjectsServiceProvider.php
source_model: src/Models/Project.php
public_controllers:
  - src/Controllers/ProjectController.php
public_views:
  - resources/views/projects/index.blade.php
  - resources/views/projects/show.blade.php
admin_permission: projects.admin
stable_key: projects.id
visible_fields:
  name: text(150)
  summary: textarea(500)
  content: markdown
excluded_fields:
  - slug
  - seo_description
  - visibility
  - owner_id
```

Read the actual model, casts, accessors, public controllers, public views, service provider, permissions, deletion behavior, and plugin manifest. Do not infer field trust or storage from names alone.

### Generated artifacts

A normal plugin integration should change only the integrating plugin and usually creates or modifies:

```text
plugin.json
src/Ronove/<Resource>ResourceProvider.php
src/Providers/<Plugin>ServiceProvider.php
src/Controllers/... public rendering points
tests/... Ronove integration coverage
resources/lang/... provider and navigation labels
```

It should not modify Ronove, Azuriom core, generated public assets, caches, or the root repository.

### Agent implementation algorithm

1. Add the `ronove >=1.0.0` manifest dependency.
2. Select only visible human-language fields.
3. Choose permanent integration, provider, and field identifiers.
4. Implement `ResourceProvider` with raw original reads.
5. Implement `FilterableResourceProvider` when the model has a meaningful administration list.
6. Register the integration before its providers.
7. Register permanent-deletion cleanup using `forget()`.
8. Locate every public rendering path for the model.
9. Use `overlay()` for lists and `translatedValues()` or `translate()` for a single model.
10. Preserve the original escaping and formatter for every field.
11. Add an optional theme selector using the public API; never write locale state directly.
12. Add tests for registration, rendering, fallback, non-persistence, filtering, deletion, and authorization.
13. Run the integrating plugin's formatter and complete test suite.
14. Review the diff for core edits, generated files, unstable IDs, or accidental persistence.

### Hard prohibitions for agents

An agent must stop or revise its approach if it is about to:

- edit Azuriom core to add a translation hook;
- add a translation column to the source model;
- create a second locale selector backend;
- write to a `ronove_*` table;
- translate a slug, URL, route, SEO field, permission, or internal identifier;
- call `overlay()` and then save the same model instance;
- render a plain-text translation with unescaped HTML;
- make automatic translation part of the integration;
- rename a released integration, provider type, or field identifier;
- ignore an empty `applyResourceKeys()` collection;
- omit permanent-deletion cleanup.

### Agent acceptance criteria

The integration is complete only when all statements below are true:

```text
[ ] Ronove is declared as >=1.0.0 dependency.
[ ] Integration and provider identifiers are valid and stable.
[ ] Only visible text fields are registered.
[ ] Provider originals are raw and deterministic.
[ ] Administration permissions are preserved.
[ ] List rendering uses a batched overlay.
[ ] Single-resource rendering uses the public manager API.
[ ] No translated value is persisted to the source model.
[ ] Source deletion removes Ronove data at the intended lifecycle point.
[ ] Theme forms use POST, CSRF, locale options, and languageUpdateUrl().
[ ] Draft and pending text stay private.
[ ] Existing approved text remains public during review.
[ ] Tests cover fallbacks, filters, deletion, and escaping.
[ ] Formatter and test suite pass.
[ ] Changes are isolated to the integrating plugin or theme.
```

## Troubleshooting

### The integration does not appear

Check that Ronove is an enabled dependency, the integration is registered before the provider, IDs pass the required pattern, the current administrator has `ronove.translations`, and both external permission methods allow access.

### A resource appears but cannot be opened

Ensure `key()` and `find()` are exact inverses. If `key()` returns a UUID, `find()` must query that UUID rather than the primary key.

### Search works but coverage filters do not

Verify `applyResourceKeys()` filters on the same stable identifier returned by `key()`. Confirm that an empty collection produces an empty query result.

### The original value changes after localization

`overlay()` intentionally changes in-memory attributes. Use `getRawOriginal()` to inspect the source value and never save the overlaid instance.

### A translation is shown in the wrong locale

Do not read the session or cookie yourself. Confirm the request uses the `web` middleware, then inspect `app()->getLocale()` and `app('ronove')->currentLanguage()`.

### The public language page returns 404

The administrator may have disabled it. Theme selectors still work through the URL returned by `languageUpdateUrl()`.

### A regional locale falls back directly to original

Confirm the target and fallback locales are enabled, the fallback chain has no cycle, and the intermediate translation is published. Ronove never exposes drafts from a fallback language.

### Rich text is escaped or plain text renders HTML

Match the public renderer to the source field's established trust model. The provider's `TranslatableField` type configures Ronove's editor but cannot safely change the integrating view by itself.

## Release compatibility policy

Ronove 1.0 establishes these durable integration guarantees:

- original source records remain authoritative;
- public IDs are stable and string-based;
- locale selection remains visitor-specific;
- the public manager API is preferred over internal persistence;
- event payloads remain scalar and serializable;
- published text is resolved per field with human-controlled fallbacks;
- schema upgrades after 1.0 use additive, dated migrations;
- automatic translation is outside Ronove's scope.

Integrating plugins should pin a compatible minimum version and treat changes to their own integration ID, provider types, stable resource keys, or field IDs as data migrations rather than simple renames.
