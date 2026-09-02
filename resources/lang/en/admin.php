<?php

return [
    'title' => 'Ronove',
    'nav' => [
        'languages' => 'Languages',
        'translations' => 'Translation center',
    ],
    'integrations' => [
        'core' => 'Azuriom',
        'other' => 'Other integrations',
    ],
    'resources' => [
        'posts' => 'News posts',
    ],
    'permissions' => [
        'settings' => 'Manage Ronove languages',
        'translations' => 'Manage Ronove translations',
        'publish' => 'Publish Ronove translations',
    ],
    'languages' => [
        'title' => 'Languages',
        'description' => 'Choose the languages visitors can use without changing Azuriom globally.',
        'global' => 'Azuriom fallback: :locale',
        'updated' => 'The available languages have been updated.',
    ],
    'logs' => [
        'settings_updated' => 'Updated Ronove language settings.',
        'translation_saved' => 'Saved a Ronove translation.',
        'translation_deleted' => 'Deleted a Ronove translation.',
    ],
    'translations' => [
        'title' => 'Translation center',
        'description' => 'Manage visible alternatives without changing the original content, slugs, routes, or SEO metadata.',
        'content_types' => '{1} :count content type|[2,*] :count content types',
        'manage_integration' => 'Manage :integration',
        'no_integrations' => 'No translation integrations are available for your permissions.',
        'back_to_center' => 'Back to translation center',
        'integration_description' => 'Choose a content type and manage its translations.',
        'resource_type' => 'Content type',
        'resource' => 'Original resource',
        'coverage' => 'Language coverage',
        'empty' => 'No resources are available for this content type.',
        'edit' => 'Translate :resource',
        'back' => 'Back to translations',
        'original' => 'Original',
        'original_help' => 'This is the original content stored by Azuriom. Ronove never changes it.',
        'empty_fallback' => 'Leave this field empty to display the original value or the configured fallback translation.',
        'source_changed' => 'The original content changed after this translation was last saved. Review it before publishing.',
        'status_label' => 'Translation status',
        'status' => [
            'draft' => 'Draft',
            'published' => 'Published',
        ],
        'updated' => 'The translation has been saved.',
        'deleted' => 'The translation has been deleted.',
        'no_languages' => 'Enable at least one Ronove language before adding translations.',
    ],
];
