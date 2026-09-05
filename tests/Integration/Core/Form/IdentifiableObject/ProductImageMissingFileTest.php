<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Integration\Core\Form\IdentifiableObject;

use Context;
use PrestaShop\PrestaShop\Core\Context\ShopContextBuilder;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\Builder\FormBuilderInterface;
use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\Handler\FormHandlerInterface;
use PrestaShop\PrestaShop\Core\Image\Uploader\Exception\ImageFileNotFoundException;
use PrestaShop\PrestaShop\Core\Image\Uploader\Exception\UploadedImageConstraintException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * The product image form declares its file field without a File constraint, so an upload whose
 * temporary file has already been consumed - by another module reading $_FILES in the same
 * request, for instance - passes validation and reaches the command handler with a path that no
 * longer resolves. This pins which exception the handler raises for it, because that exception is
 * what ImageController turns into the message the merchant reads.
 */
class ProductImageMissingFileTest extends KernelTestCase
{
    private ?string $uploadedFilePath = null;

    protected function tearDown(): void
    {
        if (null !== $this->uploadedFilePath && is_file($this->uploadedFilePath)) {
            unlink($this->uploadedFilePath);
        }
        $this->uploadedFilePath = null;

        parent::tearDown();
    }

    public function testItReportsAMissingUploadedFile(): void
    {
        [$form, $handler] = $this->submitImageForm(false);

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isValid(), 'the form has no File constraint, so it accepts the upload');

        $this->expectException(ImageFileNotFoundException::class);
        $handler->handle($form);
    }

    /**
     * Control: the same request with the file still on disk must get past the size assertion and
     * fail on the content instead. Without it, the assertion above would also pass if the handler
     * simply rejected every upload.
     */
    public function testItStillValidatesTheContentWhenTheFileIsPresent(): void
    {
        [$form, $handler] = $this->submitImageForm(true);

        self::assertTrue($form->isValid());

        $this->expectException(UploadedImageConstraintException::class);
        $this->expectExceptionCode(UploadedImageConstraintException::UNRECOGNIZED_FORMAT);
        $handler->handle($form);
    }

    /**
     * @return array{0: FormInterface, 1: FormHandlerInterface}
     */
    private function submitImageForm(bool $keepFile): array
    {
        self::bootKernel();
        $container = self::getContainer();
        Context::getContext()->container = $container;

        // no HTTP request ran, so the context listener never initialised the shop context
        $shopContextBuilder = $container->get(ShopContextBuilder::class);
        $shopContextBuilder->setShopId(1);
        $shopContextBuilder->setShopConstraint(ShopConstraint::shop(1));

        /** @var FormBuilderInterface $formBuilder */
        $formBuilder = $container->get('prestashop.core.form.identifiable_object.builder.product_image_form_builder');
        /** @var FormHandlerInterface $formHandler */
        $formHandler = $container->get('prestashop.core.form.identifiable_object.product_image_form_handler');

        $filePath = tempnam(sys_get_temp_dir(), 'product_image_upload');
        $this->uploadedFilePath = $filePath;
        file_put_contents($filePath, 'not an image');
        $uploadedFile = new UploadedFile($filePath, 'upload.jpg', 'image/jpeg', null, true);

        if (!$keepFile) {
            unlink($filePath);
            self::assertFileDoesNotExist($filePath);
        }

        $form = $formBuilder->getForm();
        $tokenId = $form->getConfig()->getOption('csrf_token_id') ?: $form->getName();
        $token = (string) $container->get('security.csrf.token_manager')->getToken($tokenId);

        $form->handleRequest(Request::create(
            '/',
            'POST',
            ['product_image' => ['product_id' => 1, 'shop_id' => 1, '_token' => $token]],
            [],
            ['product_image' => ['file' => $uploadedFile]]
        ));

        return [$form, $formHandler];
    }
}
