<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Tests\Integration\Adapter\Cache\Clearer;

use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Adapter\Cache\Clearer\ThemeJsonCacheClearer;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

class ThemeJsonCacheClearerTest extends TestCase
{
    private string $root;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->root = sys_get_temp_dir() . '/ps-theme-json-' . uniqid('', false);

        $this->filesystem->mkdir([$this->root . '/classic', $this->root . '/hummingbird/assets']);
        $this->filesystem->dumpFile($this->root . '/classic/theme.json', '{}');
        $this->filesystem->dumpFile($this->root . '/classic/shop1.json', '{}');
        $this->filesystem->dumpFile($this->root . '/hummingbird/shop2.json', '{}');
        // Not a parsed configuration: it belongs to the theme, one level deeper.
        $this->filesystem->dumpFile($this->root . '/hummingbird/assets/manifest.json', '{}');
        // Not JSON at all.
        $this->filesystem->dumpFile($this->root . '/classic/theme.yml', 'name: classic');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->root);

        parent::tearDown();
    }

    private function clearer(?string $dir = null): ThemeJsonCacheClearer
    {
        return new ThemeJsonCacheClearer(new NullLogger(), $this->filesystem, $dir ?? $this->root);
    }

    public function testItRemovesTheParsedConfigurationOfEveryTheme(): void
    {
        // Control: the fixture has to be there, or the assertions below pass vacuously.
        $this->assertFileExists($this->root . '/classic/theme.json');
        $this->assertFileExists($this->root . '/hummingbird/shop2.json');

        $this->clearer()->clear();

        $this->assertFileDoesNotExist($this->root . '/classic/theme.json');
        $this->assertFileDoesNotExist($this->root . '/classic/shop1.json');
        $this->assertFileDoesNotExist($this->root . '/hummingbird/shop2.json');
    }

    public function testItLeavesTheThemesOwnFilesAlone(): void
    {
        $this->clearer()->clear();

        $this->assertFileExists(
            $this->root . '/hummingbird/assets/manifest.json',
            'A JSON file belonging to the theme was removed; only the parsed configuration beside it should go.'
        );
        $this->assertFileExists($this->root . '/classic/theme.yml');
        $this->assertDirectoryExists($this->root . '/classic');
    }

    public function testAMissingDirectoryIsNotAnError(): void
    {
        $this->clearer($this->root . '/does-not-exist')->clear();

        $this->assertDirectoryDoesNotExist($this->root . '/does-not-exist');
    }

    /**
     * Clearing on its own is not enough: the clearer has to be in the chain that a cache clear runs,
     * and that membership cannot be read back off the chain object.
     */
    public function testTheClearerIsPartOfTheCacheClearerChain(): void
    {
        $services = file_get_contents(_PS_ROOT_DIR_ . '/src/PrestaShopBundle/Resources/config/services/core/cache.yml');

        $this->assertIsString($services);
        // Vacuity guard: the chain this one is added to must still be readable in that file.
        $this->assertStringContainsString('cache_clearer_chain', $services, 'Failed to read the cache service definitions.');

        $this->assertStringContainsString(
            'prestashop.adapter.cache.clearer.theme_json_cache_clearer',
            $services,
            'The theme configuration clearer is no longer wired into the cache clearer chain, so clearing the cache leaves the parsed theme configuration behind.'
        );
    }
}
