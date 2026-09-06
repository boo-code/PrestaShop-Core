<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Integration\Classes\Product;

use Configuration;
use Db;
use Product;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * getAttributesGroups() orders by group position, attribute position and group name. None of those
 * identifies a row - every attribute is shared by several combinations - so their relative order was
 * left to the engine, and the front office preselects whichever combination comes first.
 *
 * This asserts the ORDER BY rather than the returned rows on purpose. An unordered query is free to
 * return the right order, and on this database it does most of the time: the same code returned
 * 2,1,4,3,6,5,8,7 on one run and 1,2,3,4,5,6,7,8 on the next. A test that reads the result would
 * therefore pass with the bug present, which is worse than no test at all.
 */
class AttributesGroupsOrderTest extends KernelTestCase
{
    /**
     * @var array<string>
     */
    private $executedQueries = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        global $kernel;
        $kernel = self::$kernel;

        $this->executedQueries = [];
    }

    protected function tearDown(): void
    {
        $this->restoreRealDb();
        parent::tearDown();
    }

    /**
     * WHY: Db::setInstanceForTesting() replaces the connection GLOBALLY, so anything reached while the
     * mock is installed reads [] and can memoise that emptiness in a static that outlives the swap -
     * Configuration's cache being the one that matters, since an empty configuration yields id_lang 0
     * and an empty shop context for every later test in the run. Put the real connection back and
     * re-read the configuration from it, rather than only dropping the mock.
     */
    private function restoreRealDb(): void
    {
        Db::deleteTestingInstance();
        Configuration::loadConfiguration();
    }

    public function testTheOrderByIsTotal(): void
    {
        // Built before the mock goes in, so the constructor's own queries hit the real connection.
        $product = new Product(1, false, 1);
        $sql = $this->captureQueryOf(static function () use ($product): void {
            $product->getAttributesGroups(1);
        });

        $this->assertStringContainsString('ORDER BY', $sql, 'the query must be ordered at all');
        $this->assertMatchesRegularExpression(
            '/ORDER BY.*id_product_attribute/is',
            $sql,
            'the combination id must take part in the ordering, otherwise rows sharing an attribute are returned in engine order'
        );
    }

    public function testTheTieBreakerComesLastSoTheDocumentedOrderIsKept(): void
    {
        // Built before the mock goes in, so the constructor's own queries hit the real connection.
        $product = new Product(1, false, 1);
        $sql = $this->captureQueryOf(static function () use ($product): void {
            $product->getAttributesGroups(1);
        });

        preg_match('/ORDER BY(.*)$/is', $sql, $matches);
        $orderBy = $matches[1] ?? '';

        $this->assertNotSame('', trim($orderBy));
        $this->assertLessThan(
            strpos($orderBy, 'id_product_attribute'),
            strpos($orderBy, 'position'),
            'the tie-breaker must come after the positions, or it would override the intended ordering'
        );
    }

    private function captureQueryOf(callable $call): string
    {
        $mock = $this->createMock(Db::class);
        $mock->method('executeS')->willReturnCallback(function ($sql) {
            $this->executedQueries[] = (string) $sql;

            return [];
        });

        // Keep the window around the mocked connection as small as the measurement allows: only the
        // call under test runs against it, and the real connection is back before any assertion.
        Db::setInstanceForTesting($mock);
        try {
            $call();
        } finally {
            $this->restoreRealDb();
        }

        $this->assertNotEmpty($this->executedQueries, 'no query was issued, so this test proves nothing');

        foreach ($this->executedQueries as $sql) {
            if (false !== stripos($sql, 'id_attribute_group')) {
                return $sql;
            }
        }

        $this->fail('the attributes-groups query was never issued');
    }
}
