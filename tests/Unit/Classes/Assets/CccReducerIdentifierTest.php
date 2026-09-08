<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Classes\Assets;

use CccReducer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * The combined bundle is served under a name built from this identifier, so the identifier is what
 * decides whether a browser holding the previous bundle fetches a new one. Built from the paths
 * alone it never changes while the file list is stable, which is why an edited stylesheet used to
 * keep its URL until PS_CCCCSS_VERSION was bumped by hand.
 */
class CccReducerIdentifierTest extends TestCase
{
    /** @var string[] */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        $this->files = [];
    }

    public function testEditingAFileChangesTheIdentifier(): void
    {
        $files = [$this->tempFile('a{color:red}'), $this->tempFile('b{color:blue}')];
        $before = $this->identifierFor($files);

        // A later mtime is what a deployment or a theme edit produces.
        touch($files[0], time() + 10);
        clearstatcache(true, $files[0]);

        $this->assertNotSame($before, $this->identifierFor($files));
    }

    public function testAnUntouchedListKeepsItsIdentifier(): void
    {
        $files = [$this->tempFile('a{color:red}'), $this->tempFile('b{color:blue}')];

        $this->assertSame($this->identifierFor($files), $this->identifierFor($files));
    }

    public function testTheIdentifierStillDependsOnTheFileList(): void
    {
        $one = $this->tempFile('a{color:red}');
        $two = $this->tempFile('b{color:blue}');

        $this->assertNotSame($this->identifierFor([$one]), $this->identifierFor([$one, $two]));
    }

    public function testAMissingFileDoesNotBreakTheIdentifier(): void
    {
        $identifier = $this->identifierFor(['/does/not/exist.css']);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{6}$/', $identifier);
    }

    private function identifierFor(array $files): string
    {
        $method = new ReflectionMethod(CccReducer::class, 'getFileNameIdentifierFromList');
        $method->setAccessible(true);

        return $method->invokeArgs($this->reducer(), [$files]);
    }

    private function reducer(): CccReducer
    {
        // The identifier is a pure function of the file list; the collaborators are not reached.
        return (new ReflectionClass(CccReducer::class))->newInstanceWithoutConstructor();
    }

    private function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ccc-') . '.css';
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }
}
