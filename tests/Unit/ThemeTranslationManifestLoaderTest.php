<?php

namespace Azuriom\Plugin\Ronove\Tests\Unit;

use Azuriom\Extensions\Theme\ThemeManager;
use Azuriom\Plugin\Ronove\Services\ThemeTranslationManifestLoader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ThemeTranslationManifestLoaderTest extends TestCase
{
    public function test_it_loads_and_normalizes_a_theme_translation_manifest(): void
    {
        $themes = $this->createMock(ThemeManager::class);
        $loader = new ThemeTranslationManifestLoader($themes);
        $manifest = $loader->load(
            'fixture',
            dirname(__DIR__).'/fixtures/theme/ronove.php',
            ['home' => ['hero' => ['title' => 'Original title']]],
        );

        $this->assertSame('theme.fixture', $manifest->integrationId);
        $this->assertSame('themes.config.fixture', $manifest->settingName);
        $this->assertSame('admin.themes', $manifest->permission);
        $this->assertSame(['hero', 'services'], array_keys($manifest->blocks));
        $this->assertSame('home.hero.title', $manifest->blocks['hero']->fields['title']->path);
        $this->assertSame('textarea', $manifest->blocks['hero']->fields['description']->type);
    }

    public function test_it_rejects_duplicate_configuration_paths(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ronove-theme-');
        file_put_contents($path, <<<'PHP'
<?php

return [
    'label' => 'Invalid theme',
    'blocks' => [
        'one' => ['label' => 'One', 'fields' => [
            'title' => ['path' => 'home.title', 'label' => 'Title'],
        ]],
        'two' => ['label' => 'Two', 'fields' => [
            'other_title' => ['path' => 'home.title', 'label' => 'Other title'],
        ]],
    ],
];
PHP);

        try {
            $loader = new ThemeTranslationManifestLoader($this->createMock(ThemeManager::class));

            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Duplicate configuration path [home.title]');
            $loader->load('invalid', $path, []);
        } finally {
            unlink($path);
        }
    }
}
