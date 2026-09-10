<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShopBundle\EventListener\Admin;

use PrestaShopBundle\Entity\Repository\EmployeeRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Records a back office connection in the employee log.
 *
 * The legacy AdminLoginController wrote this entry itself; when the login moved to Symfony the
 * entry was not carried over, so the log stopped showing who signed in and from where.
 */
class EmployeeLoginLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'logConnection',
        ];
    }

    public function logConnection(LoginSuccessEvent $event): void
    {
        $employee = $this->employeeRepository->loadEmployeeByIdentifier($event->getAuthenticatedToken()->getUserIdentifier());
        if (null === $employee) {
            return;
        }

        $this->logger->info(
            $this->translator->trans(
                'Back office connection from %ip%',
                ['%ip%' => $event->getRequest()->getClientIp()],
                'Admin.Advparameters.Feature'
            ),
            [
                // Each connection is its own entry: the point of the log is that a repeated
                // sign-in shows up as a repeated line.
                'allow_duplicate' => true,
                'id_employee' => $employee->getId(),
            ]
        );
    }
}
