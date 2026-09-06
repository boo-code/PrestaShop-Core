<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Integration\Classes;

use Db;
use PHPUnit\Framework\TestCase;
use TaxRule;
use TaxRulesGroup;

/**
 * getIdTaxRuleGroupFromHistorizedId() maps a rule id from the group it was edited in onto the clone
 * that updateTaxRulesGroup() produces. A rule that is being created has no id to map, and the caller
 * in AdminTaxRulesGroupController passes it anyway, so the accessor has to answer that case rather
 * than read fields off the empty row.
 */
class TaxRulesGroupHistorizedIdTest extends TestCase
{
    private TaxRulesGroup $group;
    private TaxRule $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = new TaxRulesGroup();
        $this->group->name = 'Historized id test';
        $this->group->active = true;
        $this->group->add();

        $this->rule = new TaxRule();
        $this->rule->id_tax_rules_group = (int) $this->group->id;
        $this->rule->id_country = 8;
        $this->rule->id_state = 0;
        $this->rule->id_tax = 1;
        $this->rule->behavior = 0;
        $this->rule->zipcode_from = '0';
        $this->rule->zipcode_to = '0';
        $this->rule->add();
    }

    protected function tearDown(): void
    {
        Db::getInstance()->delete('tax_rule', 'id_tax_rules_group = ' . (int) $this->group->id);
        Db::getInstance()->delete('tax_rules_group_shop', 'id_tax_rules_group = ' . (int) $this->group->id);
        Db::getInstance()->delete('tax_rules_group', 'id_tax_rules_group = ' . (int) $this->group->id);

        parent::tearDown();
    }

    public function testItMapsARuleThatExists(): void
    {
        $this->assertSame(
            (string) $this->rule->id,
            (string) $this->group->getIdTaxRuleGroupFromHistorizedId((int) $this->rule->id)
        );
    }

    /**
     * The regression this guards: reading the six columns off an empty row raised a warning each,
     * which the debug error handler turns into an exception and stops the save.
     */
    public function testItAnswersForARuleThatDoesNotExistInsteadOfReadingAnEmptyRow(): void
    {
        $raised = [];
        set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
            $raised[] = $message;

            return true;
        }, E_ALL);

        try {
            $mapped = $this->group->getIdTaxRuleGroupFromHistorizedId(0);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $raised, 'mapping an id of 0 must not read fields off an empty row');
        $this->assertFalse($mapped);
    }

    public function testItAnswersForARuleIdThatWasDeleted(): void
    {
        $deletedId = (int) $this->rule->id;
        $this->rule->delete();

        $this->assertFalse($this->group->getIdTaxRuleGroupFromHistorizedId($deletedId));
    }
}
