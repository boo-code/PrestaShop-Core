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

namespace PrestaShop\PrestaShop\Adapter\Cache\Clearer;

use PrestaShop\PrestaShop\Core\Cache\Clearer\CacheClearerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Removes the parsed copies of each theme's configuration.
 *
 * A theme's `config/theme.yml` is parsed once and written next to it as `theme.json`, or as
 * `shop<id>.json` when a shop is in context, so that later reads skip the yml parsing. Nothing
 * invalidates those copies when the yml changes, which is why an edited theme configuration appears
 * to be ignored until the file is removed by hand.
 */
final class ThemeJsonCacheClearer implements CacheClearerInterface
{
    use SafeLoggerTrait;

    public function __construct(
        protected readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem,
        private readonly string $themesConfigDir,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        if (!$this->filesystem->exists($this->themesConfigDir)) {
            return;
        }

        // WHY: only the files directly inside a theme's own directory are these parsed copies.
        // Anything deeper belongs to the theme rather than to this cache.
        $finder = (new Finder())
            ->files()
            ->in($this->themesConfigDir)
            ->depth('== 1')
            ->name('*.json');

        foreach ($finder as $file) {
            try {
                $this->filesystem->remove($file->getPathname());
            } catch (Throwable $e) {
                // A file that cannot be removed must not abort the rest of the cache clear.
                $this->logWarning(sprintf(
                    'ThemeJsonCacheClearer: could not remove the parsed theme configuration "%s": %s',
                    $file->getPathname(),
                    $e->getMessage()
                ));
            }
        }
    }
}
