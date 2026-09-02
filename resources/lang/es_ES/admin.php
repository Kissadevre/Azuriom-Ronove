<?php

return [
    'title' => 'Ronove',
    'nav' => [
        'languages' => 'Idiomas',
        'translations' => 'Centro de traducciones',
    ],
    'integrations' => [
        'core' => 'Azuriom',
        'other' => 'Otras integraciones',
    ],
    'resources' => [
        'posts' => 'Publicaciones de noticias',
    ],
    'permissions' => [
        'settings' => 'Administrar los idiomas de Ronove',
        'translations' => 'Administrar las traducciones de Ronove',
        'publish' => 'Publicar las traducciones de Ronove',
    ],
    'languages' => [
        'title' => 'Idiomas',
        'description' => 'Elige los idiomas que pueden usar los visitantes sin cambiar Azuriom globalmente.',
        'global' => 'Idioma de respaldo de Azuriom: :locale',
        'updated' => 'Los idiomas disponibles han sido actualizados.',
    ],
    'logs' => [
        'settings_updated' => 'Actualizó la configuración de idiomas de Ronove.',
        'translation_saved' => 'Guardó una traducción de Ronove.',
        'translation_deleted' => 'Eliminó una traducción de Ronove.',
    ],
    'translations' => [
        'title' => 'Centro de traducciones',
        'description' => 'Administra alternativas visibles sin cambiar el contenido original, slugs, rutas ni metadatos SEO.',
        'content_types' => '{1} :count tipo de contenido|[2,*] :count tipos de contenido',
        'manage_integration' => 'Administrar :integration',
        'no_integrations' => 'No hay integraciones de traducción disponibles para tus permisos.',
        'back_to_center' => 'Regresar al centro de traducciones',
        'integration_description' => 'Elige un tipo de contenido y administra sus traducciones.',
        'resource_type' => 'Tipo de contenido',
        'resource' => 'Recurso original',
        'coverage' => 'Cobertura de idiomas',
        'empty' => 'No hay recursos disponibles para este tipo de contenido.',
        'edit' => 'Traducir :resource',
        'back' => 'Regresar a traducciones',
        'original' => 'Original',
        'original_help' => 'Este es el contenido original almacenado por Azuriom. Ronove nunca lo modifica.',
        'empty_fallback' => 'Deja este campo vacío para mostrar el valor original o la traducción de respaldo configurada.',
        'source_changed' => 'El contenido original cambió desde la última vez que se guardó esta traducción. Revísala antes de publicarla.',
        'status_label' => 'Estado de la traducción',
        'status' => [
            'draft' => 'Borrador',
            'published' => 'Publicada',
        ],
        'updated' => 'La traducción ha sido guardada.',
        'deleted' => 'La traducción ha sido eliminada.',
        'no_languages' => 'Habilita al menos un idioma de Ronove antes de agregar traducciones.',
    ],
];
