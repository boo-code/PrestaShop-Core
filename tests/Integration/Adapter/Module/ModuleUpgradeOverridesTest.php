<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Integration\Adapter\Module;

use Configuration;
use Module as LegacyModule;
use PHPUnit\Framework\MockObject\MockObject;
use PrestaShop\PrestaShop\Adapter\Module\Module;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Upgrading used to be the only lifecycle transition that left a module's overrides untouched, so a
 * module shipping a changed override kept running the version it had been installed with until
 * someone disabled and re-enabled it by hand.
 */
class ModuleUpgradeOverridesTest extends KernelTestCase
{
    private string $originalDisableOverrides;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->originalDisableOverrides = (string) Configuration::get('PS_DISABLE_MODULE_OVERRIDES');
    }

    protected function tearDown(): void
    {
        Configuration::updateValue('PS_DISABLE_MODULE_OVERRIDES', $this->originalDisableOverrides);

        parent::tearDown();
    }

    public function testUpgradePublishesTheOverridesShippedByTheNewVersion(): void
    {
        $legacy = $this->legacyModuleWithOverrides();
        $legacy->expects($this->once())->method('uninstallOverrides')->willReturn(true);
        $legacy->expects($this->once())->method('installOverrides')->willReturn(true);

        $module = $this->moduleFor($legacy);

        $this->assertTrue($module->onUpgrade('2.0.0'));
        $this->assertSame('2.0.0', $module->getDatabaseAttributes()->get('version'));
    }

    public function testUpgradeTakesBackWhatItAddedWhenTheNewOverrideConflicts(): void
    {
        $legacy = $this->legacyModuleWithOverrides();
        $legacy->method('installOverrides')->willThrowException(
            new \Exception('The method foo in the class Bar is already overridden by the module other.')
        );
        // Once to drop the previous version's declarations, once to take back the partial install.
        $legacy->expects($this->exactly(2))->method('uninstallOverrides')->willReturn(true);

        $module = $this->moduleFor($legacy);

        $this->assertFalse($module->onUpgrade('2.0.0'));
    }

    public function testUpgradeLeavesOverridesAloneWhenTheModuleShipsNone(): void
    {
        $legacy = $this->createLegacyMock();
        $legacy->method('getOverrides')->willReturn(null);
        $legacy->expects($this->never())->method('uninstallOverrides');
        $legacy->expects($this->never())->method('installOverrides');

        $this->assertTrue($this->moduleFor($legacy)->onUpgrade('2.0.0'));
    }

    public function testUpgradeLeavesOverridesAloneWhenTheShopDisabledThem(): void
    {
        Configuration::updateValue('PS_DISABLE_MODULE_OVERRIDES', '1');

        $legacy = $this->legacyModuleWithOverrides();
        $legacy->expects($this->never())->method('uninstallOverrides');
        $legacy->expects($this->never())->method('installOverrides');

        $this->assertTrue($this->moduleFor($legacy)->onUpgrade('2.0.0'));
    }

    /**
     * @return LegacyModule&MockObject
     */
    private function createLegacyMock(): LegacyModule
    {
        return $this->getMockBuilder(LegacyModule::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOverrides', 'uninstallOverrides', 'installOverrides'])
            ->getMock();
    }

    /**
     * @return LegacyModule&MockObject
     */
    private function legacyModuleWithOverrides(): LegacyModule
    {
        $legacy = $this->createLegacyMock();
        $legacy->method('getOverrides')->willReturn(['Contact']);

        return $legacy;
    }

    private function moduleFor(LegacyModule $legacy): Module
    {
        $module = new Module(
            ['name' => 'qaupgradeoverrides', 'version_available' => '2.0.0'],
            ['is_present' => true, 'is_valid' => true],
            ['installed' => true, 'version' => '1.0.0']
        );
        $module->instance = $legacy;

        return $module;
    }
}
