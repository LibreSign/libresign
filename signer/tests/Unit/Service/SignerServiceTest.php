<?php

namespace OCA\Signer\Tests\Unit\Service;

use OCA\Signer\Handler\JSignerHandler;
use OCA\Signer\Service\SignerService;
use OCA\Signer\Storage\ClientStorage;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class SignerServiceTest extends TestCase
{
    public function testSignFile()
    {
        $inputFilePath = '/path/to/someInputFilePath';
        $outputFolderPath = '/path/to/someOutputFolderPath';
        $certificatePath = '/path/to/someCertificatePath';
        $password = 'somePassword';
        $signerHandler = $this->prophesize(JSignerHandler::class);
        $clientStorage = $this->prophesize(ClientStorage::class);

        $filename = 'someFilename';
        $content = 'someContent';

        $clientStorage->getFile($inputFilePath)
            ->shouldBeCalled()
        ;

        $clientStorage->getFile($certificatePath)
            ->shouldBeCalled()
        ;

        $signerHandler->signExistingFile(\Prophecy\Argument::any(), \Prophecy\Argument::any(), $password)
            ->shouldBeCalled()
            ->willReturn([$filename, $content])
        ;

        $clientStorage->createFolder($outputFolderPath)
            ->shouldBeCalled()
        ;

        $clientStorage->saveFile($filename, $content, \Prophecy\Argument::any())
            ->shouldBeCalled()
        ;

        $service = new SignerService($signerHandler->reveal(), $clientStorage->reveal());
        $service->sign($inputFilePath, $outputFolderPath, $certificatePath, $password);
    }
}
