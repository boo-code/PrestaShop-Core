# ./vendor/bin/behat -c tests/Integration/Behaviour/behat.yml -s order --tags order-catalog-discount-and-voucher
@restore-all-tables-before-feature
@clear-cache-before-feature
@order-catalog-discount-and-voucher
Feature: Order with both a catalog discount and a voucher
  As a shop owner
  When an order carries a catalog price reduction and a cart rule at the same time
  I want its invoice to keep showing the tax rates of its products

  Background:
    Given email sending is disabled
    And the current currency is "USD"
    And country "US" is enabled
    And the module "dummy_payment" is installed
    And I am logged in as "test@prestashop.com" employee
    And there is customer "testCustomer" with email "pub@prestashop.com"
    And customer "testCustomer" has address in "US" country
    And a carrier "default_carrier" with name "My carrier" exists

  Scenario: The tax details survive a catalog discount combined with a voucher
    Given there is a product in the catalog named "TaxedProduct19803" with a price of 100.00 and 100 items in stock
    And product "TaxedProduct19803" has a specific price named "catalog_discount" with a discount of 50.0 percent
    And I create an empty cart "cart19803" for customer "testCustomer"
    And I select "US" address as delivery and invoice address for customer "testCustomer" in cart "cart19803"
    And I add 2 products "TaxedProduct19803" to the cart "cart19803"
    And I use a voucher "PROMO19803" for a discount of 5.0 on the cart "cart19803"
    When I add order "bo_order19803" from cart "cart19803" with "dummy_payment" payment method and "Payment accepted" order status
    # 100.00 halved by the catalog rule is 50.00 a unit; the 5.00 voucher spread over the two units
    # takes 2.50 off each, so the taxed base is 47.50 and the 6% tax follows it.
    Then order "bo_order19803" should have following tax details:
      | unit_tax_base | total_tax_base | tax_rate |
      | 47.50         | 95.00          | 6.00     |

  @restricted-voucher
  Scenario: Two tax bases with a voucher restricted to one of the products
    Given there is a product in the catalog named "ProductA19803" with a price of 100.00 and 100 items in stock
    And there is a product in the catalog named "ProductB19803" with a price of 50.00 and 100 items in stock
    And product "ProductA19803" has a specific price named "catalog_discount_a" with a discount of 50.0 percent
    And there is a cart rule "voucherA" with following properties:
      | name[en-US]       | voucherA |
      | discount_amount   | 10       |
      | total_quantity    | 100      |
      | quantity_per_user | 100      |
    And cart rule "voucherA" is restricted to product "ProductA19803"
    And I create an empty cart "cart19803b" for customer "testCustomer"
    And I select "US" address as delivery and invoice address for customer "testCustomer" in cart "cart19803b"
    And I add 1 products "ProductA19803" to the cart "cart19803b"
    And I add 2 products "ProductB19803" to the cart "cart19803b"
    And cart rule "voucherA" is applied to my cart
    When I add order "bo_order19803b" from cart "cart19803b" with "dummy_payment" payment method and "Payment accepted" order status
    # The voucher only concerns ProductA, so it comes off that line alone: 50.00 - 10.00 = 40.00,
    # and ProductB keeps its full 50.00 base.
    Then order "bo_order19803b" should have following tax details:
      | unit_tax_base | tax_rate |
      | 40            | 6.00     |
      | 50            | 6.00     |
