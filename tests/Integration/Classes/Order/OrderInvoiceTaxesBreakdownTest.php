<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Integration\Classes\Order;

use Cache;
use Configuration;
use Context;
use Currency;
use Order;
use OrderInvoice;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Resources\DatabaseDump;

/**
 * Both breakdowns spread `total_shipping_tax_excl - $sum_of_tax_bases` over the rows. When
 * PS_ATCP_SHIPWRAP is off the base is assigned to each row but never accumulated, so the delta equals the
 * whole base and gets added on top of it.
 */
class OrderInvoiceTaxesBreakdownTest extends KernelTestCase
{
    private const INVOICE_ID = 9001;
    private const ORDER_ID = 1;
    private const SHIPPING_TAX_EXCL = 7.0;
    private const WRAPPING_TAX_EXCL = 5.0;

    public static function tearDownAfterClass(): void
    {
        DatabaseDump::restoreTables(['order_invoice', 'order_invoice_tax', 'configuration']);
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $container = self::getContainer();
        Context::getContext()->container = $container;

        // getComputingPrecision() reads the currency; no HTTP request set one up here
        $currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));
        $currency->precision ??= 2;
        Context::getContext()->currency = $currency;

        Configuration::updateValue('PS_ATCP_SHIPWRAP', 0);
        Configuration::updateValue('PS_INVOICE_TAXES_BREAKDOWN', 1);
        Configuration::resetStaticCache();

        $connection = $container->get('doctrine.dbal.default_connection');
        $prefix = $container->getParameter('database_prefix');
        $connection->executeStatement('DELETE FROM ' . $prefix . 'order_invoice WHERE id_order_invoice = ' . self::INVOICE_ID);
        $connection->executeStatement('DELETE FROM ' . $prefix . 'order_invoice_tax WHERE id_order_invoice = ' . self::INVOICE_ID);
        $connection->executeStatement(sprintf(
            'INSERT INTO %sorder_invoice (id_order_invoice, id_order, number, delivery_number, total_discount_tax_excl,
                total_discount_tax_incl, total_paid_tax_excl, total_paid_tax_incl, total_products, total_products_wt,
                total_shipping_tax_excl, total_shipping_tax_incl, shipping_tax_computation_method,
                total_wrapping_tax_excl, total_wrapping_tax_incl, date_add)
             VALUES (%d, %d, %d, 0, 0, 0, %F, %F, 0, 0, %F, %F, 0, %F, %F, NOW())',
            $prefix,
            self::INVOICE_ID,
            self::ORDER_ID,
            self::INVOICE_ID,
            self::SHIPPING_TAX_EXCL,
            self::SHIPPING_TAX_EXCL * 1.2,
            self::SHIPPING_TAX_EXCL,
            self::SHIPPING_TAX_EXCL * 1.2,
            self::WRAPPING_TAX_EXCL,
            self::WRAPPING_TAX_EXCL * 1.2
        ));
        $connection->executeStatement(sprintf(
            "INSERT INTO %sorder_invoice_tax (id_order_invoice, type, id_tax, amount) VALUES (%d, 'shipping', 1, %F), (%d, 'wrapping', 1, %F)",
            $prefix,
            self::INVOICE_ID,
            self::SHIPPING_TAX_EXCL * 0.2,
            self::INVOICE_ID,
            self::WRAPPING_TAX_EXCL * 0.2
        ));
        Cache::clean('*');
    }

    public function testTheShippingBreakdownReportsTheShippingBase(): void
    {
        $breakdown = (new OrderInvoice(self::INVOICE_ID))->getShippingTaxesBreakdown(new Order(self::ORDER_ID));

        self::assertNotEmpty($breakdown);
        self::assertEqualsWithDelta(
            self::SHIPPING_TAX_EXCL,
            array_sum(array_column($breakdown, 'total_tax_excl')),
            0.001,
            'the breakdown must add up to the shipping the invoice was issued for'
        );
    }

    public function testTheWrappingBreakdownReportsTheWrappingBase(): void
    {
        $breakdown = (new OrderInvoice(self::INVOICE_ID))->getWrappingTaxesBreakdown();

        self::assertNotEmpty($breakdown);
        self::assertEqualsWithDelta(
            self::WRAPPING_TAX_EXCL,
            array_sum(array_column($breakdown, 'total_tax_excl')),
            0.001,
            'the breakdown must add up to the wrapping the invoice was issued for'
        );
    }
}
