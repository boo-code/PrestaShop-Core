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

namespace Tests\Unit\PrestaShopBundle\Form\Admin\Configure\ShopParameters\TrafficSeo\Meta;

use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Adapter\Routes\RouteValidator;
use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;
use PrestaShopBundle\Form\Admin\Configure\ShopParameters\TrafficSeo\Meta\MetaSettingsUrlSchemaFormDataProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

class MetaSettingsUrlSchemaFormDataProviderTest extends TestCase
{
    private function buildProvider(
        DataConfigurationInterface $dataConfiguration,
        RouteValidator $routeValidator
    ): MetaSettingsUrlSchemaFormDataProvider {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('error message');

        return new MetaSettingsUrlSchemaFormDataProvider($dataConfiguration, $translator, $routeValidator);
    }

    /**
     * In single-shop multistore context the submitted data carries top-level
     * `multistore_<route>` checkbox helper fields whose value is a bool. Saving
     * must not fatal with "foreach() argument must be of type array|object,
     * bool given" — those entries are skipped, not iterated as route arrays.
     */
    public function testSaveIgnoresMultistoreCheckboxBooleanFields(): void
    {
        $routeValidator = $this->createMock(RouteValidator::class);
        $routeValidator->method('isRoutePattern')->willReturn(true);
        $routeValidator->method('isRouteValid')->willReturn([]);

        $dataConfiguration = $this->createMock(DataConfigurationInterface::class);
        $dataConfiguration->method('updateConfiguration')->willReturn([]);

        $provider = $this->buildProvider($dataConfiguration, $routeValidator);

        $result = $provider->setData([
            'product_rule' => [1 => 'category/{id}-{rewrite}'],
            'multistore_product_rule' => false,
        ]);

        $this->assertSame([], $result);
    }

    /**
     * An empty route field arrives as null. Validation must not fatal with
     * "htmlspecialchars(): Argument #1 must be of type string, null given"
     * (nor on the strictly-typed RouteValidator::isRouteValid(string)).
     */
    public function testValidationDoesNotFatalOnNullRouteValue(): void
    {
        $routeValidator = $this->createMock(RouteValidator::class);
        // Force the invalid-pattern branch so htmlspecialchars() is reached.
        $routeValidator->method('isRoutePattern')->willReturn(false);
        $routeValidator->method('isRouteValid')->willReturn([]);

        $dataConfiguration = $this->createMock(DataConfigurationInterface::class);

        $provider = $this->buildProvider($dataConfiguration, $routeValidator);

        $result = $provider->setData([
            'product_rule' => [1 => null],
        ]);

        // It returns the validation errors (non-empty) instead of fataling.
        $this->assertNotEmpty($result);
    }
}
