<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Integration\Classes;

use CMS;
use Configuration;
use Db;
use PHPUnit\Framework\TestCase;
use Shop;

class CMSTest extends TestCase
{
    private const CMS_ID = 1;
    private const OTHER_SHOP_ID = 2;
    private const OTHER_SHOP_LANG_ID = 2;
    private const CONTENT_IN_LANG_1 = 'other shop, language 1';
    private const CONTENT_IN_LANG_2 = 'other shop, language 2';

    protected function setUp(): void
    {
        parent::setUp();
        $this->removeFixtures();

        $db = Db::getInstance();

        // Configuration::get() only honours an explicitly requested shop while the multistore
        // feature is on, and that in turn needs more than one shop to exist.
        $db->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'shop` (`id_shop`, `id_shop_group`, `name`, `color`, `id_category`, `theme_name`, `active`, `deleted`)
             VALUES (' . self::OTHER_SHOP_ID . ", 1, 'other shop', '', 2, 'hummingbird', 1, 0)"
        );
        $db->execute(
            'INSERT INTO `' . _DB_PREFIX_ . "configuration` (`name`, `value`, `id_shop`, `date_add`, `date_upd`)
             VALUES ('PS_MULTISHOP_FEATURE_ACTIVE', '1', NULL, NOW(), NOW())"
        );

        // The other shop keeps a default language of its own, different from the one in context.
        $db->execute(
            'INSERT INTO `' . _DB_PREFIX_ . "configuration` (`name`, `value`, `id_shop`, `date_add`, `date_upd`)
             VALUES ('PS_LANG_DEFAULT', '" . self::OTHER_SHOP_LANG_ID . "', " . self::OTHER_SHOP_ID . ', NOW(), NOW())'
        );

        foreach ([1 => self::CONTENT_IN_LANG_1, self::OTHER_SHOP_LANG_ID => self::CONTENT_IN_LANG_2] as $idLang => $content) {
            $db->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'cms_lang` (`id_cms`, `id_lang`, `id_shop`, `meta_title`, `head_seo_title`, `meta_description`, `content`, `link_rewrite`)
                 VALUES (' . self::CMS_ID . ', ' . (int) $idLang . ', ' . self::OTHER_SHOP_ID . ", 'title', 'title', '', '" . pSQL($content) . "', 'link-" . (int) $idLang . "')"
            );
        }

        $this->refreshCaches();
    }

    protected function tearDown(): void
    {
        $this->removeFixtures();
        $this->refreshCaches();
        parent::tearDown();
    }

    private function removeFixtures(): void
    {
        $db = Db::getInstance();
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'cms_lang` WHERE `id_cms` = ' . self::CMS_ID . ' AND `id_shop` = ' . self::OTHER_SHOP_ID);
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . "configuration` WHERE `name` = 'PS_LANG_DEFAULT' AND `id_shop` = " . self::OTHER_SHOP_ID);
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . "configuration` WHERE `name` = 'PS_MULTISHOP_FEATURE_ACTIVE'");
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'shop` WHERE `id_shop` = ' . self::OTHER_SHOP_ID);
    }

    private function refreshCaches(): void
    {
        Shop::resetStaticCache();
        Configuration::loadConfiguration();
    }

    /**
     * Asking for another shop's content without naming a language has to fall back to the default
     * language of that shop, not to the one of the shop currently in context.
     */
    public function testItFallsBackToTheDefaultLanguageOfTheRequestedShop(): void
    {
        self::assertTrue(Shop::isFeatureActive(), 'the fixture must have multistore on, or the requested shop is ignored');
        self::assertSame(self::OTHER_SHOP_LANG_ID, (int) Configuration::get('PS_LANG_DEFAULT', null, null, self::OTHER_SHOP_ID));

        $row = CMS::getCMSContent(self::CMS_ID, null, self::OTHER_SHOP_ID);

        self::assertSame(self::CONTENT_IN_LANG_2, $row['content']);
    }

    /**
     * A language given explicitly still wins over the shop's default.
     */
    public function testItKeepsAnExplicitlyRequestedLanguage(): void
    {
        $row = CMS::getCMSContent(self::CMS_ID, 1, self::OTHER_SHOP_ID);

        self::assertSame(self::CONTENT_IN_LANG_1, $row['content']);
    }

    /**
     * The other shop's default language must not leak into a call that names no shop.
     */
    public function testItLeavesTheShopInContextAlone(): void
    {
        $expected = Db::getInstance()->getValue(
            'SELECT `content` FROM `' . _DB_PREFIX_ . 'cms_lang` WHERE `id_cms` = ' . self::CMS_ID
            . ' AND `id_lang` = ' . (int) Configuration::get('PS_LANG_DEFAULT')
            . ' AND `id_shop` = ' . (int) Configuration::get('PS_SHOP_DEFAULT')
        );

        $row = CMS::getCMSContent(self::CMS_ID);

        self::assertSame($expected, $row['content']);
    }
}
