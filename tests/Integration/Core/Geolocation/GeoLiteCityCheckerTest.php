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

namespace Tests\Integration\Core\Geolocation;

use PrestaShop\PrestaShop\Core\Geolocation\GeoLite\GeoLiteCityCheckerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The geolocation screen tells a merchant where to put the MaxMind data, and the availability check
 * is an exact directory-plus-filename match. These pin the two together, because a message that does
 * not name the file leaves the merchant guessing and the screen keeps showing the same warning.
 */
class GeoLiteCityCheckerTest extends KernelTestCase
{
    private const TEMPLATE = '/src/PrestaShopBundle/Resources/views/Admin/Improve/International/Geolocation/index.html.twig';

    private ?string $createdFile = null;

    protected function tearDown(): void
    {
        if (null !== $this->createdFile && file_exists($this->createdFile)) {
            unlink($this->createdFile);
        }
        $this->createdFile = null;

        parent::tearDown();
    }

    public function testTheCheckLooksForExactlyTheDeclaredFileInTheDeclaredDirectory(): void
    {
        self::bootKernel();
        global $kernel;
        $kernel = self::$kernel;

        /** @var GeoLiteCityCheckerInterface $checker */
        $checker = self::getContainer()->get('prestashop.core.geolocation.geo_lite_city.checker');

        $expected = _PS_GEOIP_DIR_ . _PS_GEOIP_CITY_FILE_;

        // Control: without the file the screen must consider the database unavailable, otherwise the
        // assertion below would be true for the wrong reason.
        $this->assertFileDoesNotExist($expected, 'A GeoLite database is already installed, so this test cannot say anything.');
        $this->assertFalse($checker->isAvailable());

        $this->createdFile = $expected;
        file_put_contents($expected, 'not a real database');

        $this->assertTrue($checker->isAvailable(), 'The check did not accept the file at the path it declares.');
    }

    public function testTheWarningNamesTheFileTheCheckRequires(): void
    {
        $template = file_get_contents(_PS_ROOT_DIR_ . self::TEMPLATE);

        // Vacuity guard: if the template could not be read, or no longer carries the warning at all,
        // the assertion below would be meaningless rather than false.
        $this->assertIsString($template);
        $this->assertStringContainsString('geolocationDatabaseAvailable', $template, 'Failed to read the geolocation screen.');
        $this->assertStringContainsString('MaxMind', $template);

        $this->assertStringContainsString(
            "constant('_PS_GEOIP_CITY_FILE_')",
            $template,
            'The warning no longer takes the file name from the constant the availability check uses, so the two can drift apart.'
        );
    }
}
