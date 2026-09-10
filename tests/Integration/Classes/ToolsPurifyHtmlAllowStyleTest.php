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

namespace Tests\Integration\Classes;

use Configuration;
use PHPUnit\Framework\TestCase;
use Tools;

class ToolsPurifyHtmlAllowStyleTest extends TestCase
{
    private const MARKUP = '<style type="text/css">.t{color:red}</style><p>text</p>';

    /**
     * @var string|bool
     */
    private $initialPurifierSetting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initialPurifierSetting = Configuration::get('PS_USE_HTMLPURIFIER');
        Configuration::updateValue('PS_USE_HTMLPURIFIER', 1);
    }

    protected function tearDown(): void
    {
        Configuration::updateValue('PS_USE_HTMLPURIFIER', $this->initialPurifierSetting);

        parent::tearDown();
    }

    /**
     * The purifier is built once and cached, but $allow_style changes the element definition it is
     * built from. A strict call must not make every later permissive call strict.
     */
    public function testStyleIsAllowedEvenWhenAStrictCallCameFirst(): void
    {
        $strict = Tools::purifyHTML(self::MARKUP, null, false);

        // Control: this doubles as the check that purification is actually running.
        $this->assertStringNotContainsString('<style', $strict, 'Purification is not running, the rest of this test would be vacuous.');

        $permissive = Tools::purifyHTML(self::MARKUP, null, true);

        $this->assertStringContainsString('<style', $permissive, 'purifyHTML() ignored $allow_style because an earlier call had built the cached purifier without it.');
    }

    /**
     * The reverse leak: a permissive call must not relax purification for the callers that follow it.
     */
    public function testStyleIsStrippedEvenWhenAPermissiveCallCameFirst(): void
    {
        $permissive = Tools::purifyHTML(self::MARKUP, null, true);

        $this->assertStringContainsString('<style', $permissive, 'Purification kept nothing, the rest of this test would be vacuous.');

        $strict = Tools::purifyHTML(self::MARKUP, null, false);

        $this->assertStringNotContainsString('<style', $strict, 'purifyHTML() kept <style> for a caller that asked for strict purification, because an earlier permissive call had built the cached purifier.');
    }
}
