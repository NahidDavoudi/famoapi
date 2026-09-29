<?php

namespace Tests\Core;

use App\Core\Storage;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\UploadedFile;

class StorageTest extends TestCase
{
    private string $uploads;
    private string $tempFile;

    protected function setUp(): void
    {
        $this->uploads = sys_get_temp_dir() . '/famo-storage-test-' . bin2hex(random_bytes(6));
        mkdir($this->uploads, 0700, true);
        $_ENV['UPLOADS_PATH'] = $this->uploads;
        $this->tempFile = tempnam(sys_get_temp_dir(), 'famo-upload-');
        file_put_contents($this->tempFile, "%PDF-1.4\nvalid test payload\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
        $this->removeTree($this->uploads);
        unset($_ENV['UPLOADS_PATH']);
        parent::tearDown();
    }

    public function testUploadAcceptsPsrUploadedFileAndReturnsRelativePath(): void
    {
        $file = new UploadedFile($this->tempFile, 'report.pdf', 'application/pdf', filesize($this->tempFile));
        $path = Storage::upload($file, 'reports', 42);

        self::assertSame('reports/42/' . basename($path), $path);
        self::assertFileExists($this->uploads . '/' . $path);
        self::assertSame("%PDF-1.4\nvalid test payload\n", file_get_contents($this->uploads . '/' . $path));
    }

    public function testUploadRejectsFileWhoseContentDoesNotMatchItsAllowedExtension(): void
    {
        file_put_contents($this->tempFile, 'not a PDF');
        $file = new UploadedFile($this->tempFile, 'report.pdf', 'application/pdf', filesize($this->tempFile));

        $this->expectException(\App\Core\ApiException::class);
        Storage::upload($file, 'reports', 42);
    }

    public function testResolveAndDeleteRejectPathsOutsideUploadsRoot(): void
    {
        $outside = dirname($this->uploads) . '/outside-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($outside, 'private');
        try {
            $this->expectException(\App\Core\ApiException::class);
            Storage::resolve('../' . basename($outside));
        } finally {
            Storage::delete('../' . basename($outside));
            self::assertFileExists($outside);
            unlink($outside);
        }
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path . DIRECTORY_SEPARATOR . $entry;
            is_dir($child) ? $this->removeTree($child) : unlink($child);
        }
        rmdir($path);
    }
}
