# Ronove

Ronove provides per-visitor locales and optional translated alternatives for visible Azuriom content. Original records remain the source of truth: Ronove never changes slugs, routes, or SEO-only fields.

## Registering plugin content

An integrating plugin registers a provider from its service provider after declaring Ronove as a dependency:

```php
use Azuriom\Plugin\Ronove\Facades\Ronove;

Ronove::registerResourceType(new ProjectResourceProvider());
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

Themes can render Ronove's accessible Bootstrap selector wherever their navigation expects list items:

```blade
@include('ronove::language-selector')
```

An integrating plugin should include the following manifest dependency so it cannot be enabled before Ronove:

```json
{
    "dependencies": {
        "ronove": ">=0.3.0"
    }
}
```

Ronove stores only translated alternatives and source hashes. Deleting or disabling a locale does not modify the original resource.

Integrations must remove orphaned Ronove records from their model deletion event:

```php
Project::deleted(fn (Project $project) => app('ronove')->forget('projects.project', $project));
```
