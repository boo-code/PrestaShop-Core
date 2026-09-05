<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Image;

use ImageManager;
use ImageManagerCore;
use PrestaShop\PrestaShop\Core\Image\Uploader\Exception\ImageFileNotFoundException;
use PrestaShop\PrestaShop\Core\Image\Uploader\Exception\ImageUploadException;
use PrestaShop\PrestaShop\Core\Image\Uploader\Exception\MemoryLimitException;
use PrestaShop\PrestaShop\Core\Image\Uploader\Exception\UploadedImageConstraintException;

/**
 * Responsible for validating image before upload
 */
class ImageValidator
{
    /**
     * @var int
     */
    protected $maxUploadSize;

    /**
     * @param int $maxUploadSizeInBytes
     */
    public function __construct(int $maxUploadSizeInBytes)
    {
        $this->maxUploadSize = $maxUploadSizeInBytes;
    }

    /**
     * @param string $filePath
     *
     * @throws ImageFileNotFoundException
     * @throws ImageUploadException
     * @throws UploadedImageConstraintException
     */
    public function assertFileUploadLimits(string $filePath): void
    {
        $this->assertFileExists($filePath);

        $size = filesize($filePath);

        if ($this->maxUploadSize > 0 && $size > $this->maxUploadSize) {
            throw new UploadedImageConstraintException(sprintf('Max file size allowed is "%s" bytes. Uploaded image size is "%s".', $this->maxUploadSize, $size), UploadedImageConstraintException::EXCEEDED_SIZE);
        }

        if (!ImageManager::checkImageMemoryLimit($filePath)) {
            throw new MemoryLimitException('Cannot upload image due to memory restrictions');
        }
    }

    /**
     * @param string $filePath
     * @param array $allowedMimeTypes
     *
     * @throws ImageUploadException
     * @throws UploadedImageConstraintException
     */
    public function assertIsValidImageType(string $filePath, ?array $allowedMimeTypes = null): void
    {
        if (!$allowedMimeTypes) {
            $allowedMimeTypes = ImageManagerCore::MIME_TYPE_SUPPORTED;
        }

        $this->assertFileExists($filePath);

        $mime = mime_content_type($filePath);
        if (!ImageManager::isRealImage($filePath, $mime, $allowedMimeTypes)) {
            throw new UploadedImageConstraintException(sprintf('Image type "%s" is not allowed, allowed types are: %s', $mime, implode(',', $allowedMimeTypes)), UploadedImageConstraintException::UNRECOGNIZED_FORMAT);
        }
    }

    /**
     * WHY: every assertion here reads the file from disk, and an upload whose temporary file has
     * gone missing reaches them with a path that no longer resolves. Without this guard the size
     * check runs filesize() on it, which warns and returns false, and the caller ends up reporting
     * whatever the false value breaks next instead of the actual problem.
     *
     * @throws ImageFileNotFoundException
     */
    protected function assertFileExists(string $filePath): void
    {
        if (!is_file($filePath)) {
            throw new ImageFileNotFoundException(sprintf('Image file "%s" not found', $filePath));
        }
    }
}
