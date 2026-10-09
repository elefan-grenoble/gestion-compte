<?php

namespace App\Tests\Integration\Validator;

use App\Entity\Event;
use App\Entity\Service;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The images uploaded for a service logo or an event (C-SEC-3): only images,
 * with an image extension, since the uploader keeps the client's file name
 * extension in the public uploads directory.
 *
 * @internal
 */
class UploadedImageValidationTest extends KernelTestCase
{
    /** @var string[] */
    private $files = [];

    protected function setUp(): void
    {
        static::bootKernel();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /**
     * @dataProvider uploads
     */
    public function testAServiceLogoMustBeAnImageWithAnImageExtension(string $content, string $clientName, bool $valid): void
    {
        $service = new Service();
        $service->setLogoFile($this->anUpload($content, $clientName));

        $this->assertSame($valid, 0 === count(static::$container->get('validator')->validateProperty($service, 'logoFile')));
    }

    /**
     * @dataProvider uploads
     */
    public function testAnEventImageMustBeAnImageWithAnImageExtension(string $content, string $clientName, bool $valid): void
    {
        $event = new Event();
        $event->setImgFile($this->anUpload($content, $clientName));

        $this->assertSame($valid, 0 === count(static::$container->get('validator')->validateProperty($event, 'imgFile')));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public function uploads(): array
    {
        return [
            'a PNG' => ['png', 'logo.png', true],
            'a PNG, upper case extension' => ['png', 'LOGO.PNG', true],
            'a PHP script' => ['php', 'shell.php', false],
            'a PHP script named as an image' => ['php', 'shell.png', false],
            'a PNG named as a PHP script' => ['png', 'shell.php', false],
            'a PNG with a double extension' => ['png', 'shell.png.php', false],
        ];
    }

    private function anUpload(string $content, string $clientName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        $this->files[] = $path;
        if ('png' === $content) {
            $image = imagecreatetruecolor(4, 4);
            imagepng($image, $path);
            imagedestroy($image);
        } else {
            file_put_contents($path, '<?php echo "pwned";');
        }

        return new UploadedFile($path, $clientName, null, null, true);
    }
}
