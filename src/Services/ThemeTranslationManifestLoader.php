<?php

namespace Azuriom\Plugin\Ronove\Services;

use Azuriom\Extensions\Theme\ThemeManager;
use Azuriom\Plugin\Ronove\Support\ThemeTranslationBlock;
use Azuriom\Plugin\Ronove\Support\ThemeTranslationField;
use Azuriom\Plugin\Ronove\Support\ThemeTranslationManifest;
use Azuriom\Plugin\Ronove\Support\TranslatableField;
use InvalidArgumentException;

class ThemeTranslationManifestLoader
{
    private ?ThemeTranslationManifest $manifest = null;

    private ?string $loadedTheme = null;

    public function __construct(private readonly ThemeManager $themes) {}

    public function active(): ?ThemeTranslationManifest
    {
        $theme = $this->themes->currentTheme();

        if ($theme === null) {
            return null;
        }

        if ($this->loadedTheme === $theme) {
            return $this->manifest;
        }

        $path = $this->themes->path('ronove.php', $theme);

        if ($path === null || ! is_file($path)) {
            $this->loadedTheme = $theme;

            return $this->manifest = null;
        }

        $config = config('theme', []);

        if (! is_array($config)) {
            $config = [];
        }

        $this->loadedTheme = $theme;
        $this->manifest = null;

        return $this->manifest = $this->load($theme, $path, $config);
    }

    public function load(string $theme, string $path, array $config): ThemeTranslationManifest
    {
        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $theme) !== 1) {
            throw new InvalidArgumentException("Invalid Ronove theme id [{$theme}].");
        }

        if (! is_file($path)) {
            throw new InvalidArgumentException("The Ronove theme manifest [{$path}] does not exist.");
        }

        $definition = (static function (string $manifestPath, array $themeConfig): mixed {
            return require $manifestPath;
        })($path, $config);

        if (! is_array($definition)) {
            throw new InvalidArgumentException("The Ronove theme manifest [{$theme}] must return an array.");
        }

        $label = $this->requiredString($definition, 'label', "theme [{$theme}]");
        $icon = $this->optionalString($definition, 'icon', 'bi bi-palette');
        $permission = $this->nullableString($definition, 'permission', 'admin.themes');
        $order = $definition['order'] ?? 200;

        if (! is_int($order)) {
            throw new InvalidArgumentException("The Ronove theme manifest order [{$theme}] must be an integer.");
        }

        $blockDefinitions = $definition['blocks'] ?? null;

        if (! is_array($blockDefinitions) || $blockDefinitions === []) {
            throw new InvalidArgumentException("The Ronove theme manifest [{$theme}] must define at least one block.");
        }

        $blocks = [];
        $paths = [];

        foreach ($blockDefinitions as $blockId => $blockDefinition) {
            if (! is_string($blockId) || preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $blockId) !== 1) {
                throw new InvalidArgumentException("Invalid Ronove theme block [{$blockId}] for [{$theme}].");
            }

            if (! is_array($blockDefinition)) {
                throw new InvalidArgumentException("The Ronove theme block [{$theme}.{$blockId}] must be an array.");
            }

            $blockLabel = $this->requiredString($blockDefinition, 'label', "theme block [{$theme}.{$blockId}]");
            $fieldDefinitions = $blockDefinition['fields'] ?? null;

            if (! is_array($fieldDefinitions) || $fieldDefinitions === []) {
                throw new InvalidArgumentException("The Ronove theme block [{$theme}.{$blockId}] must define at least one field.");
            }

            $fields = [];

            foreach ($fieldDefinitions as $fieldId => $fieldDefinition) {
                if (! is_string($fieldId) || preg_match('/^[a-z][a-z0-9_]*$/', $fieldId) !== 1) {
                    throw new InvalidArgumentException("Invalid Ronove theme field [{$fieldId}] for [{$theme}.{$blockId}].");
                }

                if (! is_array($fieldDefinition)) {
                    throw new InvalidArgumentException("The Ronove theme field [{$theme}.{$blockId}.{$fieldId}] must be an array.");
                }

                $context = "theme field [{$theme}.{$blockId}.{$fieldId}]";
                $fieldPath = $this->requiredString($fieldDefinition, 'path', $context);

                if (preg_match('/^[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)*$/', $fieldPath) !== 1) {
                    throw new InvalidArgumentException("Invalid configuration path [{$fieldPath}] for {$context}.");
                }

                if (isset($paths[$fieldPath])) {
                    throw new InvalidArgumentException("Duplicate configuration path [{$fieldPath}] in [{$theme}].");
                }

                $fieldLabel = $this->requiredString($fieldDefinition, 'label', $context);
                $labelParameters = $fieldDefinition['label_parameters'] ?? [];

                if (! is_array($labelParameters)) {
                    throw new InvalidArgumentException("The label parameters for {$context} must be an array.");
                }

                foreach ($labelParameters as $parameter => $value) {
                    if (! is_string($parameter) || (! is_string($value) && ! is_int($value))) {
                        throw new InvalidArgumentException("Invalid label parameter for {$context}.");
                    }
                }

                $fieldType = $this->optionalString($fieldDefinition, 'type', TranslatableField::TEXT);

                if (! in_array($fieldType, [
                    TranslatableField::TEXT,
                    TranslatableField::TEXTAREA,
                    TranslatableField::MARKDOWN,
                    TranslatableField::RICH_TEXT,
                ], true)) {
                    throw new InvalidArgumentException("Unsupported field type [{$fieldType}] for {$context}.");
                }

                $maxLength = $fieldDefinition['max'] ?? null;

                if ($maxLength !== null && (! is_int($maxLength) || $maxLength < 1)) {
                    throw new InvalidArgumentException("The maximum length for {$context} must be a positive integer.");
                }

                if ($maxLength !== null && in_array($fieldType, [TranslatableField::MARKDOWN, TranslatableField::RICH_TEXT], true)) {
                    throw new InvalidArgumentException("The field type [{$fieldType}] does not support a maximum length for {$context}.");
                }

                $paths[$fieldPath] = true;
                $fields[$fieldId] = new ThemeTranslationField(
                    $fieldId,
                    $fieldPath,
                    $fieldLabel,
                    $labelParameters,
                    $fieldType,
                    $maxLength,
                );
            }

            $blocks[$blockId] = new ThemeTranslationBlock($blockId, $blockLabel, $fields);
        }

        return new ThemeTranslationManifest(
            $theme,
            'theme.'.$theme,
            $label,
            $icon,
            $permission,
            $order,
            'themes.config.'.$theme,
            $blocks,
        );
    }

    private function requiredString(array $definition, string $key, string $context): string
    {
        $value = $definition[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The Ronove {$context} must define a non-empty [{$key}].");
        }

        return $value;
    }

    private function optionalString(array $definition, string $key, string $default): string
    {
        if (! array_key_exists($key, $definition)) {
            return $default;
        }

        return $this->requiredString($definition, $key, 'theme manifest');
    }

    private function nullableString(array $definition, string $key, ?string $default): ?string
    {
        if (! array_key_exists($key, $definition)) {
            return $default;
        }

        $value = $definition[$key];

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The Ronove theme manifest [{$key}] must be null or a non-empty string.");
        }

        return $value;
    }
}
