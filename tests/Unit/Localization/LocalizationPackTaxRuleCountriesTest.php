<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Localization;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * The tax rules a localization pack ships are matched to countries by ISO code at install time
 * (LocalizationPack::_installTaxes()), and an ISO that matches nothing is skipped without a word.
 */
class LocalizationPackTaxRuleCountriesTest extends TestCase
{
    private const UNITED_KINGDOM = 'GB';

    /**
     * @var string[] uppercase ISO codes of every country the installer creates
     */
    private static array $knownCountries;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $countries = new SimpleXMLElement((string) file_get_contents(self::rootDir() . '/install-dev/data/xml/country.xml'), 0, false);
        self::$knownCountries = [];
        foreach ($countries->entities->country as $country) {
            self::$knownCountries[] = strtoupper((string) $country['id']);
        }
    }

    /**
     * A tax rule naming a country that does not exist is dead weight: it never becomes a rule, and
     * nothing reports it. `uk` sat in two packs this way, next to a working `gb` entry.
     */
    public function testEveryTaxRuleNamesACountryTheInstallerCreates(): void
    {
        $this->assertGreaterThan(200, count(self::$knownCountries));

        $unknown = [];
        $rules = 0;
        foreach ($this->taxRules() as $pack => $isoCodes) {
            foreach ($isoCodes as $isoCode) {
                ++$rules;
                if (!in_array(strtoupper($isoCode), self::$knownCountries, true)) {
                    $unknown[] = $pack . ' -> ' . $isoCode;
                }
            }
        }

        $this->assertGreaterThan(500, $rules, 'the scan found too few tax rules to be trusted');
        $this->assertSame([], $unknown);
    }

    /**
     * The United Kingdom left the EU VAT area on 1 January 2021, so an EU pack charging its domestic
     * VAT there is wrong. Every pack but the United Kingdom's own lists EU countries only.
     */
    public function testNoPackButTheUnitedKingdomsOwnTaxesTheUnitedKingdom(): void
    {
        $offenders = [];
        foreach ($this->taxRules() as $pack => $isoCodes) {
            if ('gb' === $pack) {
                continue;
            }
            if (in_array(self::UNITED_KINGDOM, array_map('strtoupper', $isoCodes), true)) {
                $offenders[] = $pack;
            }
        }

        $this->assertSame([], $offenders);
    }

    public function testTheUnitedKingdomsOwnPackStillTaxesIt(): void
    {
        $isoCodes = array_map('strtoupper', $this->taxRules()['gb'] ?? []);

        $this->assertContains(self::UNITED_KINGDOM, $isoCodes);
    }

    /**
     * @return array<string, string[]> pack name => the iso codes its tax rules name
     */
    private function taxRules(): array
    {
        $packs = [];
        foreach ((array) glob(self::rootDir() . '/localization/*.xml') as $path) {
            $name = basename((string) $path, '.xml');
            if ('default' === $name) {
                continue;
            }

            $isoCodes = [];
            $xml = new SimpleXMLElement((string) file_get_contents((string) $path), 0, false);
            foreach ($xml->xpath('//taxRule[@iso_code_country]') ?: [] as $rule) {
                $isoCodes[] = (string) $rule['iso_code_country'];
            }
            $packs[$name] = $isoCodes;
        }

        $this->assertGreaterThan(50, count($packs), 'the localization packs were not found');

        return $packs;
    }

    private static function rootDir(): string
    {
        return dirname(__DIR__, 3);
    }
}
