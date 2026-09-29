<?php

use App\Core\MultipartFormDataParser;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

final class MultipartFormDataParserTest extends TestCase
{
    public function testItParsesFieldsAndUploadedFiles(): void
    {
        $boundary = 'Unit-Test-Boundary';
        $body = "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"name\"\r\n\r\n"
            . "Ada Lovelace\r\n"
            . "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"image\"; filename=\"portrait.jpg\"\r\n"
            . "Content-Type: image/jpeg\r\n\r\n"
            . "fake-image-bytes\r\n"
            . "--{$boundary}--\r\n";

        $result = MultipartFormDataParser::parse($body, $boundary);

        self::assertSame('Ada Lovelace', $result['fields']['name']);
        self::assertInstanceOf(UploadedFileInterface::class, $result['files']['image']);
        self::assertSame('portrait.jpg', $result['files']['image']->getClientFilename());
        self::assertSame('fake-image-bytes', (string) $result['files']['image']->getStream());

        $tempPath = $result['files']['image']->getStream()->getMetadata('uri');
        if (is_string($tempPath) && is_file($tempPath)) {
            unlink($tempPath);
        }
    }

    public function testItRejectsMalformedMultipartBodies(): void
    {
        $this->expectException(\App\Core\ApiException::class);
        MultipartFormDataParser::parse('not multipart', 'expected-boundary');
    }

    public function testItPopulatesNativePutRequestFieldsAndFiles(): void
    {
        $boundary = 'put-boundary';
        $body = "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"title\"\r\n\r\n"
            . "Senior Instructor\r\n"
            . "--{$boundary}--\r\n";
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/api/v1/instructors/1')
            ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $boundary)
            ->withBody((new StreamFactory())->createStream($body));

        $parsedRequest = MultipartFormDataParser::applyToRequest($request);

        self::assertSame('PUT', $parsedRequest->getMethod());
        self::assertSame(['title' => 'Senior Instructor'], $parsedRequest->getParsedBody());
        self::assertSame([], $parsedRequest->getUploadedFiles());
    }

    public function testItParsesPhpStyleArrayFields(): void
    {
        $boundary = 'array-boundary';
        $body = "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"tags[]\"\r\n\r\n"
            . "math\r\n"
            . "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"tags[]\"\r\n\r\n"
            . "science\r\n"
            . "--{$boundary}--\r\n";

        $result = MultipartFormDataParser::parse($body, $boundary);

        self::assertSame(['tags' => ['math', 'science']], $result['fields']);
    }
}
