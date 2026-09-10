<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Tests\Integration\PrestaShopBundle\Form\Admin\Password;

use PrestaShopBundle\Form\Admin\Login\LoginType;
use PrestaShopBundle\Form\Admin\Login\ResetPasswordType;
use PrestaShopBundle\Form\Admin\Type\ChangePasswordType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * A password field with no autocomplete hint invites a browser to offer a generated password where
 * an existing one is wanted, and offers nothing where a new one is. The front office already makes
 * this distinction in CustomerFormatter; these pin the same distinction in the back office.
 */
class PasswordAutocompleteTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        global $kernel;
        $kernel = self::$kernel;

        $this->formFactory = self::getContainer()->get('form.factory');
    }

    private function attrOf(string $formType, string ...$path): array
    {
        $form = $this->formFactory->create($formType, null, ['csrf_protection' => false]);

        foreach ($path as $child) {
            $form = $form->get($child);
        }

        return $form->getConfig()->getOption('attr');
    }

    public function testTheCurrentPasswordOfTheChangePasswordFormIsNotOfferedForGeneration(): void
    {
        $attr = $this->attrOf(ChangePasswordType::class, 'old_password');

        $this->assertSame('current-password', $attr['autocomplete'] ?? null);
    }

    /**
     * @dataProvider newPasswordFieldProvider
     */
    public function testANewPasswordFieldAsksTheBrowserForOne(string $formType, array $path): void
    {
        $attr = $this->attrOf($formType, ...$path);

        $this->assertSame('new-password', $attr['autocomplete'] ?? null);
    }

    public static function newPasswordFieldProvider(): iterable
    {
        yield 'change password, new' => [ChangePasswordType::class, ['new_password', 'first']];
        yield 'change password, confirmation' => [ChangePasswordType::class, ['new_password', 'second']];
        yield 'reset password, new' => [ResetPasswordType::class, ['new_password', 'first']];
        yield 'reset password, confirmation' => [ResetPasswordType::class, ['new_password', 'second']];
    }

    public function testTheLoginPasswordIsNotOfferedForGeneration(): void
    {
        $attr = $this->attrOf(LoginType::class, 'passwd');

        $this->assertSame('current-password', $attr['autocomplete'] ?? null);
    }

    /**
     * The hint has to be added alongside the attributes a password field already carries, not in
     * place of them. This asserts only the pre-existing ones, so it passes before the change as well
     * and fails only if they were dropped - which is what makes it a control rather than a restatement
     * of the assertions above.
     */
    public function testTheAttributesAPasswordFieldAlreadyCarriedSurvive(): void
    {
        $attr = $this->attrOf(ChangePasswordType::class, 'new_password', 'first');

        $this->assertArrayHasKey('data-minlength', $attr, 'The existing attributes were replaced instead of extended.');
        $this->assertArrayHasKey('data-maxlength', $attr);
        $this->assertArrayHasKey('data-minscore', $attr);
    }
}
