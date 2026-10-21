<?php

namespace App\Tests\Integration\Config;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Since Symfony 4 the web root is public/ (public/index.php); web/ is no
 * longer served. Uploaded files must therefore be stored under public/, at
 * the path their public URI points to, and the image filters must read them
 * from there.
 *
 * @internal
 *
 * @coversNothing
 */
final class PublicUploadStorageTest extends KernelTestCase
{
    /** 1x1 transparent PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /** @var null|string */
    private $createdFile;

    protected function tearDown(): void
    {
        if (null !== $this->createdFile && is_file($this->createdFile)) {
            unlink($this->createdFile);
        }

        parent::tearDown();
    }

    public function testEveryUploadMappingStoresItsFilesWhereItsPublicUriIsServed(): void
    {
        self::bootKernel();
        $publicDir = self::$container->getParameter('kernel.project_dir') . '/public';

        $mappings = self::$container->getParameter('vich_uploader.mappings');
        $this->assertNotEmpty($mappings);

        foreach ($mappings as $name => $mapping) {
            $this->assertSame(
                $publicDir . $mapping['uri_prefix'],
                $mapping['upload_destination'],
                sprintf('The "%s" uploads must be stored under the web root, at their URI "%s".', $name, $mapping['uri_prefix'])
            );
        }
    }

    public function testTheImageFiltersReadAnUploadedLogoFromItsPublicUri(): void
    {
        self::bootKernel();
        $mapping = self::$container->getParameter('vich_uploader.mappings')['service_logo'];

        $name = 'public-upload-storage-test-' . bin2hex(random_bytes(4)) . '.png';
        if (!is_dir($mapping['upload_destination'])) {
            mkdir($mapping['upload_destination'], 0775, true);
        }
        $this->createdFile = $mapping['upload_destination'] . '/' . $name;
        file_put_contents($this->createdFile, base64_decode(self::PNG));

        // Same path as the templates pass to imagine_filter(): asset(vich_uploader_asset(...)).
        $binary = self::$container->get('liip_imagine.data.manager')->find('service_logo', $mapping['uri_prefix'] . '/' . $name);

        $this->assertSame(base64_decode(self::PNG), $binary->getContent());
    }
}
