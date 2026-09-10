<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Integration\PrestaShopBundle\EventListener\Admin;

use Db;
use PrestaShopBundle\Entity\Repository\EmployeeRepository;
use PrestaShopBundle\EventListener\Admin\EmployeeLoginLogSubscriber;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class EmployeeLoginLogSubscriberTest extends KernelTestCase
{
    private const EMPLOYEE_EMAIL = 'test@prestashop.com';

    private int $logHighWaterMark = 0;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->logHighWaterMark = $this->lastLogId();
    }

    /**
     * The rows these tests write are real rows in the test database, so they are removed again -
     * a left-behind log entry would show up in every later assertion counting them.
     */
    protected function tearDown(): void
    {
        Db::getInstance()->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'log WHERE id_log > ' . $this->logHighWaterMark
        );

        parent::tearDown();
    }

    public function testItIsSubscribedToTheLoginSuccessEvent(): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = self::getContainer()->get('event_dispatcher');

        $subscribers = array_map(
            static fn (callable $listener): string => is_array($listener) ? get_class($listener[0]) : get_class($listener),
            $dispatcher->getListeners(LoginSuccessEvent::class)
        );

        $this->assertContains(EmployeeLoginLogSubscriber::class, $subscribers);
    }

    public function testItRecordsTheConnectionAgainstTheEmployeeWhoSignedIn(): void
    {
        $employee = $this->employeeRepository()->loadEmployeeByIdentifier(self::EMPLOYEE_EMAIL);
        $this->assertNotNull($employee, 'the test employee is expected in the test database');

        $before = $this->lastLogId();
        $this->subscriber()->logConnection($this->loginSuccessEvent(self::EMPLOYEE_EMAIL, '203.0.113.7'));
        $row = $this->logRowAfter($before);

        $this->assertNotNull($row, 'the connection should have been recorded in the log');
        $this->assertSame('Back office connection from 203.0.113.7', $row['message']);
        $this->assertSame((int) $employee->getId(), (int) $row['id_employee']);
        $this->assertSame(1, (int) $row['severity']);
    }

    /**
     * A repeated sign-in is the thing the log exists to show, so the entry must not be deduplicated.
     */
    public function testASecondConnectionIsRecordedAsWell(): void
    {
        $before = $this->lastLogId();
        $this->subscriber()->logConnection($this->loginSuccessEvent(self::EMPLOYEE_EMAIL, '203.0.113.8'));
        $this->subscriber()->logConnection($this->loginSuccessEvent(self::EMPLOYEE_EMAIL, '203.0.113.8'));

        $rows = Db::getInstance()->executeS(
            'SELECT id_log FROM ' . _DB_PREFIX_ . 'log WHERE id_log > ' . (int) $before
        );
        $this->assertCount(2, $rows);
    }

    public function testAnIdentifierThatMatchesNoEmployeeRecordsNothing(): void
    {
        $before = $this->lastLogId();
        $this->subscriber()->logConnection($this->loginSuccessEvent('nobody@example.com', '203.0.113.9'));

        $this->assertNull($this->logRowAfter($before));
    }

    private function subscriber(): EmployeeLoginLogSubscriber
    {
        return self::getContainer()->get(EmployeeLoginLogSubscriber::class);
    }

    private function employeeRepository(): EmployeeRepository
    {
        return self::getContainer()->get(EmployeeRepository::class);
    }

    private function loginSuccessEvent(string $identifier, string $ip): LoginSuccessEvent
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUserIdentifier')->willReturn($identifier);

        return new LoginSuccessEvent(
            $this->createMock(AuthenticatorInterface::class),
            new SelfValidatingPassport(new UserBadge($identifier)),
            $token,
            Request::create('/admin/index.php', 'POST', [], [], [], ['REMOTE_ADDR' => $ip]),
            null,
            'main'
        );
    }

    private function lastLogId(): int
    {
        return (int) Db::getInstance()->getValue('SELECT MAX(id_log) FROM ' . _DB_PREFIX_ . 'log');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function logRowAfter(int $lastId): ?array
    {
        $row = Db::getInstance()->getRow(
            'SELECT message, id_employee, severity FROM ' . _DB_PREFIX_ . 'log WHERE id_log > ' . (int) $lastId . ' ORDER BY id_log ASC'
        );

        return empty($row) ? null : $row;
    }
}
