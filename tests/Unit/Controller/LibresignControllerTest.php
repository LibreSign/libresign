<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use OC\Files\Node\File;
use OCA\Libresign\Controller\LibresignController;
use OCA\Libresign\Service\LibresignService;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @internal
 * @coversNothing
 */
final class LibresignControllerTest extends TestCase
{
    use ProphecyTrait;
    public function testSignFile()
    {
        $userId = 'john';
        $request = $this->prophesize(IRequest::class);
        $service = $this->prophesize(LibresignService::class);
        $file = $this->prophesize(File::class);
        $file->getInternalPath()->willReturn("/path/to/someFileSigned");
        
        $inputFilePath = '/path/to/someInputFilePath';
        $outputFolderPath = '/path/to/someOutputFolderPath';
        $certificatePath = '/path/to/someCertificatePath';
        $password = 'somePassword';

        $service->sign($inputFilePath, $outputFolderPath, $certificatePath, $password)
            ->shouldBeCalled()
            ->willReturn($file->reveal())
        ;
        
        $controller = new LibresignController(
                $request->reveal(),
                $service->reveal(),
                $userId
            );

        $result = $controller->sign($inputFilePath, $outputFolderPath, $certificatePath, $password);

        static::assertSame(['fileSigned' => '/path/to/someFileSigned'], $result->getData());
    }

    public function failParameterMissingProvider()
    {
        $inputFilePath = '/path/to/someInputFilePath';
        $outputFolderPath = '/path/to/someOutputFolderPath';
        $certificatePath = '/path/to/someCertificatePath';
        $password = 'somePassword';

        return [
            [null, $outputFolderPath, $certificatePath, $password, 'inputFilePath'],
            [$inputFilePath, null,  $certificatePath, $password, 'outputFolderPath'],
            [$inputFilePath, $outputFolderPath,  null, $password, 'certificatePath'],
            [$inputFilePath, $outputFolderPath, $certificatePath, null, 'password'],
        ];
    }

    /** @dataProvider failParameterMissingProvider */
    public function testSignFileFailParameterMissing(
        $inputFilePath,
        $outputFolderPath,
        $certificatePath,
        $password,
        $paramenterMissing
    ){
        $userId = 'john';
        $request = $this->prophesize(IRequest::class);
        $service = $this->prophesize(LibresignService::class);

        $service->sign(\Prophecy\Argument::cetera())
            ->shouldNotBeCalled();

        $controller = new LibresignController(
            $request->reveal(),
            $service->reveal(),
            $userId
        );

        $result = $controller->sign($inputFilePath, $outputFolderPath, $certificatePath, $password);

        static::assertSame(['message' => "parameter '{$paramenterMissing}' is required!"], $result->getData());
        static::assertSame(400, $result->getStatus());
    }
}
