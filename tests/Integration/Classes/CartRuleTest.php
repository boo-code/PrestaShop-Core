<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Integration\Classes;

use CartRule;
use Customer;
use Db;
use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Adapter\Configuration;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Tests\Resources\DatabaseDump;
use Tools;

class CartRuleTest extends TestCase
{
    /**
     * @var Customer
     */
    protected $dummyCustomer;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var int
     */
    protected $defaultLanguageId;

    public static function setUpBeforeClass(): void
    {
        DatabaseDump::restoreTables(
            [
                'cart_rule',
                'cart_rule_lang',
                'cart_rule_combination',
            ]
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dummyCustomer = $this->createDummyCustomer();
        $this->configuration = new Configuration();
        $this->defaultLanguageId = $this->configuration->get('PS_LANG_DEFAULT', null, ShopConstraint::allShops());
    }

    public function testGetCartRulesForCustomer(): void
    {
        $this->createDummyCartRule(true, (int) $this->dummyCustomer->id);

        $customerCartRules = CartRule::getCustomerCartRules(
            $this->defaultLanguageId,
            $this->dummyCustomer->id
        );

        $this->assertEquals(1, count($customerCartRules));
    }

    public function testGetAllCartRulesForCustomerEvenDisabled(): void
    {
        $this->createDummyCartRule(false, (int) $this->dummyCustomer->id);

        $customerCartRules = CartRule::getCustomerCartRules(
            $this->defaultLanguageId,
            $this->dummyCustomer->id,
            true
        );

        // We assert 0 because 'active' flag does not work in "CartRule::getCustomerCartRules"
        // because of CartRule::isFeatureActive and one additional check
        // which doesn't not work if we have only 1 customer rule or all of them are disabled
        // see https://github.com/PrestaShop/PrestaShop/issues/21556 for more details
        $this->assertEquals(0, count($customerCartRules));
    }

    public function testGetAllCartRulesForCustomerWithDedicatedMethod(): void
    {
        $this->createDummyCartRule(true, (int) $this->dummyCustomer->id);

        $customerCartRules = CartRule::getAllCustomerCartRules(
            (int) $this->dummyCustomer->id
        );

        $this->assertEquals(1, count($customerCartRules));
    }

    public function testGetAllCartRulesForCustomerWithDedicatedMethodEvenDisabled(): void
    {
        $this->createDummyCartRule(false, (int) $this->dummyCustomer->id);

        $customerCartRules = CartRule::getAllCustomerCartRules(
            (int) $this->dummyCustomer->id
        );

        $this->assertEquals(1, count($customerCartRules));
    }

    public function testGetAllCartRulesForCustomerWithDedicatedMethodBothEnabledAndDisabled(): void
    {
        $this->createDummyCartRule(false, (int) $this->dummyCustomer->id);
        $this->createDummyCartRule(true, (int) $this->dummyCustomer->id);
        $this->createDummyCartRule(true, (int) $this->dummyCustomer->id);

        $customerCartRules = CartRule::getAllCustomerCartRules(
            (int) $this->dummyCustomer->id
        );

        $this->assertEquals(3, count($customerCartRules));
    }

    public function testGetAllCartRulesForCustomerWithDedicatedMethodBothEnabledAndDisabledWithOtherCustomerCartRulesAvailable(): void
    {
        $this->createDummyCartRule(false, (int) $this->dummyCustomer->id);
        $this->createDummyCartRule(true, (int) $this->dummyCustomer->id);

        $differentCustomer = $this->createDummyCustomer();
        $yetAnotherCustomer = $this->createDummyCustomer();

        // Just to make sure that our CartRule::getAlCustomerCartRules works well
        $this->createDummyCartRule(false, (int) $differentCustomer->id);
        $this->createDummyCartRule(true, (int) $yetAnotherCustomer->id);

        $customerCartRules = CartRule::getAllCustomerCartRules(
            (int) $this->dummyCustomer->id
        );

        $yetAnotherCustomerCartRules = CartRule::getAllCustomerCartRules(
            (int) $yetAnotherCustomer->id
        );

        $this->assertEquals(2, count($customerCartRules));
        $this->assertEquals(1, count($yetAnotherCustomerCartRules));
    }

    public function testGetAllCartRulesWithGlobalCartRulesAvailable(): void
    {
        $this->createDummyCartRule(true, (int) $this->dummyCustomer->id, false);
        $this->createDummyCartRule(true, (int) $this->dummyCustomer->id);

        $customerCartRules = CartRule::getAllCustomerCartRules(
            (int) $this->dummyCustomer->id
        );

        $this->assertEquals(2, count($customerCartRules));
    }

    /*
     * Tests if the customer sees only his specific vouchers in my account zone
     * in front office.
     */
    public function testGetAllCustomersCartRulesInMyAccountZone(): void
    {
        // Reset table
        self::setUpBeforeClass();

        // Code, highlight, for everyone
        $CodeHighlightEveryone = $this->createDummyCartRule(true, 0, true, true);
        // Code, highlight, specific customer
        $CodeHighlightSpecific = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id, true, true);
        // Code, no highlight, for everyone
        $CodeNohighlightEveryone = $this->createDummyCartRule(true, 0, true);
        // Code, no highlight, specific customer
        $CodeNohighlightSpecific = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id, true);
        // No code, highlight, for everyone
        $NocodeHighlightEveryone = $this->createDummyCartRule(true, 0, false, true);
        // No code, highlight, specific customer
        $NocodeHighlightSpecific = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id, false, true);
        // No code, no highlight, for everyone
        $NocodeNohighlightEveryone = $this->createDummyCartRule(true, 0, false);
        // No code, no highlight, specific customer
        $NocodeNohighlightSpecific = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id, false);
        // Code, highlight, specific customer, disabled
        $CodeHighlightSpecificDisabled = $this->createDummyCartRule(false, (int) $this->dummyCustomer->id, true, true);
        // Code, highlight, specific customer, disabled
        $CodeHighlightSpecificDisabled = $this->createDummyCartRule(false, (int) $this->dummyCustomer->id, true, true);

        // Get customer's vouchers in frontoffice
        $customerCartRules = CartRule::getCustomerCartRules(
            $this->defaultLanguageId,
            (int) $this->dummyCustomer->id,
            true,
            false
        );

        $this->assertEquals(
            [
                $CodeNohighlightSpecific->id,
                $NocodeNohighlightSpecific->id,
                $CodeHighlightSpecific->id,
                $NocodeHighlightSpecific->id,
            ],
            array_column($customerCartRules, 'id_cart_rule')
        );
    }

    /*
     * Tests customer's voucher in backoffice. We should see even the disabled vouchers here.
     */
    public function testGetAllCustomersCartRulesInBackoffice(): void
    {
        // Reset table
        self::setUpBeforeClass();

        // Code, highlight, for everyone
        $CodeHighlightEveryone = $this->createDummyCartRule(true, 0, true, true);
        // Code, highlight, specific customer
        $CodeHighlightSpecific = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id, true, true);
        // Code, no highlight, for everyone
        $CodeNohighlightEveryone = $this->createDummyCartRule(true, 0, true);
        // Code, no highlight, specific customer
        $CodeNohighlightSpecific = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id, true);
        // No code, highlight, for everyone
        $NocodeHighlightEveryone = $this->createDummyCartRule(true, 0, false, true);
        // No code, highlight, specific customer
        $NocodeHighlightSpecific = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id, false, true);
        // No code, no highlight, for everyone
        $NocodeNohighlightEveryone = $this->createDummyCartRule(true, 0, false);
        // No code, no highlight, specific customer
        $NocodeNohighlightSpecific = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id, false);
        // Code, highlight, specific customer, disabled
        $CodeHighlightSpecificDisabled = $this->createDummyCartRule(false, (int) $this->dummyCustomer->id, true, true);

        $customerCartRules = CartRule::getAllCustomerCartRules(
            (int) $this->dummyCustomer->id
        );

        $this->assertEquals(
            [
                $NocodeNohighlightSpecific->id,
                $NocodeHighlightSpecific->id,
                $CodeNohighlightSpecific->id,
                $CodeHighlightSpecific->id,
                $CodeHighlightSpecificDisabled->id,
            ],
            array_column($customerCartRules, 'id_cart_rule')
        );
    }

    /*
     * Tests if both logged in and logged out customer gets offered
     * proper highlighted vouchers in cart.
     */
    public function testGetHighlightedVouchersInCart(): void
    {
        // Reset table
        self::setUpBeforeClass();

        // Code, highlight, for everyone
        $CodeHighlightEveryone = $this->createDummyCartRule(true, 0, true, true);
        // Code, highlight, specific customer
        $CodeHighlightSpecific = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id, true, true);
        // Code, no highlight, for everyone
        $CodeNohighlightEveryone = $this->createDummyCartRule(true, 0, true);
        // Code, no highlight, specific customer
        $CodeNohighlightSpecific = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id, true);
        // No code, highlight, for everyone
        $NocodeHighlightEveryone = $this->createDummyCartRule(true, 0, false, true);
        // No code, highlight, specific customer
        $NocodeHighlightSpecific = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id, false, true);
        // No code, no highlight, for everyone
        $NocodeNohighlightEveryone = $this->createDummyCartRule(true, 0, false);
        // No code, no highlight, specific customer
        $NocodeNohighlightSpecific = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id, false);
        // Code, highlight, specific customer, disabled
        $CodeHighlightSpecificDisabled = $this->createDummyCartRule(false, (int) $this->dummyCustomer->id, true, true);
        // Code, highlight, specific customer, disabled
        $CodeHighlightSpecificDisabled = $this->createDummyCartRule(false, (int) $this->dummyCustomer->id, true, true);

        // Get logged in customer's vouchers, we simulate getCustomerHighlightedDiscounts with no cart
        $customerCartRules = CartRule::getCustomerCartRules(
            $this->defaultLanguageId,
            (int) $this->dummyCustomer->id,
            true,
            true,
            true,
            null,
            false,
            true
        );
        $this->assertEquals(
            [
                $CodeHighlightEveryone->id,
                $NocodeHighlightEveryone->id,
                $CodeHighlightSpecific->id,
                $NocodeHighlightSpecific->id,
            ],
            array_column($customerCartRules, 'id_cart_rule')
        );

        // Get guest customer's vouchers, we simulate getCustomerHighlightedDiscounts with no cart
        $customerCartRules = CartRule::getCustomerCartRules(
            $this->defaultLanguageId,
            0,
            true,
            true,
            true,
            null,
            false,
            true
        );
        $this->assertEquals(
            [
                $CodeHighlightEveryone->id,
                $NocodeHighlightEveryone->id,
            ],
            array_column($customerCartRules, 'id_cart_rule')
        );
    }

    /**
     * Test sorting of the CartRules
     *
     * Target sort order:
     * - Active CartRules for customer
     * - Active CartRules for everyone
     * - Inactive CartRules for customer
     * - Inactive CartRules for everyone
     */
    public function testSortingOfTheAvailableCustomerCartRules(): void
    {
        // inactive customer's rule
        $inactiveCustomerRule = $this->createDummyCartRule(false, (int) $this->dummyCustomer->id, false);

        // inactive global rule
        $inactiveGlobalRule = $this->createDummyCartRule(false, 0, false);

        // active global rule
        $activeGlobalRule = $this->createDummyCartRule(true, 0, false);

        // active customer's rule
        $activeCustomerRule = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id);

        $customerCartRules = CartRule::getAllCustomerCartRules(
            (int) $this->dummyCustomer->id
        );

        $this->assertEquals(
            [
                $activeCustomerRule->id,
                $inactiveCustomerRule->id,
            ],
            array_column($customerCartRules, 'id_cart_rule')
        );
    }

    /**
     * @param bool $active
     * @param int $customerId
     * @param bool $code
     *
     * @return CartRule
     */
    /**
     * The combination table stores a pair without normalising its orientation, so a shop that has saved
     * cart rule restrictions in the back office ends up holding both (A, B) and (B, A). copyConditions()
     * maps both of those rows onto the same destination pair, and the INSERT then collides with itself.
     *
     * It matters well beyond the copy: the only caller is PaymentModule::validateOrder() creating the
     * remainder voucher for a partially used one, and that path is not wrapped in a transaction, so the
     * throw leaves an order with a captured payment and no state at all.
     */
    public function testConditionsAreCopiedOnceWhenACombinationIsStoredInBothOrientations(): void
    {
        $source = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id);
        $other = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id);
        $destination = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id);

        // What the back office restriction inserts leave behind over time.
        Db::getInstance()->insert('cart_rule_combination', [
            'id_cart_rule_1' => (int) $source->id,
            'id_cart_rule_2' => (int) $other->id,
        ]);
        Db::getInstance()->insert('cart_rule_combination', [
            'id_cart_rule_1' => (int) $other->id,
            'id_cart_rule_2' => (int) $source->id,
        ]);

        CartRule::copyConditions((int) $source->id, (int) $destination->id);

        $copied = Db::getInstance()->executeS(
            'SELECT id_cart_rule_2 FROM ' . _DB_PREFIX_ . 'cart_rule_combination'
            . ' WHERE id_cart_rule_1 = ' . (int) $destination->id
        );

        $this->assertCount(1, $copied, 'the pair was copied once per stored orientation');
        $this->assertSame((int) $other->id, (int) $copied[0]['id_cart_rule_2']);
    }

    public function testASingleOrientationIsStillCopied(): void
    {
        $source = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id);
        $other = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id);
        $destination = $this->createDummyCartRule(true, (int) $this->dummyCustomer->id);

        Db::getInstance()->insert('cart_rule_combination', [
            'id_cart_rule_1' => (int) $source->id,
            'id_cart_rule_2' => (int) $other->id,
        ]);

        CartRule::copyConditions((int) $source->id, (int) $destination->id);

        // Guards the de-duplication against throwing the row away instead of collapsing it.
        $copied = Db::getInstance()->executeS(
            'SELECT id_cart_rule_2 FROM ' . _DB_PREFIX_ . 'cart_rule_combination'
            . ' WHERE id_cart_rule_1 = ' . (int) $destination->id
        );
        $this->assertCount(1, $copied);
        $this->assertSame((int) $other->id, (int) $copied[0]['id_cart_rule_2']);
    }

    public function createDummyCartRule(
        bool $active,
        int $customerId,
        bool $code = true,
        bool $highlight = false
    ): CartRule {
        $randomNumber = rand(999, 9999);
        $cart_rule = new CartRule();
        $cart_rule->code = $code ? 'TEST_CART_RULE_' . $randomNumber : '';
        $cart_rule->name = [
            $this->defaultLanguageId => 'Test Cart Rule #' . $randomNumber,
        ];
        $cart_rule->id_customer = $customerId;
        $cart_rule->free_shipping = true;
        $cart_rule->quantity = 1;
        $cart_rule->quantity_per_user = 1;
        $cart_rule->minimum_amount_currency = $this->configuration->get('PS_CURRENCY_DEFAULT', null, ShopConstraint::allShops());
        $cart_rule->reduction_currency = $this->configuration->get('PS_CURRENCY_DEFAULT', null, ShopConstraint::allShops());
        $cart_rule->date_from = date('Y-m-d H:i:s', time());
        $cart_rule->date_to = date('Y-m-d H:i:s', time() + 24 * 36000);
        $cart_rule->active = $active;
        $cart_rule->highlight = $highlight;
        $cart_rule->add();

        return $cart_rule;
    }

    /**
     * @return Customer
     */
    public function createDummyCustomer(): Customer
    {
        $customer = new Customer();
        $customer->firstname = 'Jenna';
        $customer->lastname = 'Doe';
        $customer->email = 'pub+' . uniqid() . '@prestashop.com';
        $customer->passwd = Tools::hash('prestashop');
        $customer->save();

        return $customer;
    }
}
