<?php

return [
    'label' => 'Fixture theme',
    'icon' => 'bi bi-palette',
    'permission' => 'admin.themes',
    'order' => 250,
    'blocks' => [
        'hero' => [
            'label' => 'Hero',
            'fields' => [
                'title' => [
                    'path' => 'home.hero.title',
                    'label' => 'Title',
                    'type' => 'text',
                    'max' => 160,
                ],
                'description' => [
                    'path' => 'home.hero.description',
                    'label' => 'Description',
                    'type' => 'textarea',
                    'max' => 600,
                ],
            ],
        ],
        'services' => [
            'label' => 'Services',
            'fields' => [
                'service_alpha_title' => [
                    'path' => 'home.services.items.0.title',
                    'label' => 'Alpha service title',
                    'type' => 'text',
                    'max' => 120,
                ],
            ],
        ],
    ],
];
