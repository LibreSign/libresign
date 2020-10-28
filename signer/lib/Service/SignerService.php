<?php

namespace OCA\Signer\Service;

use OCA\Signer\Handler\JSignerHandler;
use OCA\Signer\Storage\ClientStorage;

class SignerService
{
    /** @var JSignerHandler */
    private $signerHandler;

    /** @var ClientStorage */
    private $clientStorage;

    public function __construct(JSignerHandler $signerHandler, ClientStorage $clientStorage)
    {
        $this->signerHandler = $signerHandler;
        $this->clientStorage = $clientStorage;
    }

    public function sign(string $inputFilePath, string $outputFolderPath, string $certificatePath, string $password){
        $file = $this->clientStorage->getFile($inputFilePath);
        $certificate = $this->clientStorage->getFile($certificatePath);

        list($filename, $content) = $this->signerHandler->signExistingFile($file, $certificate, $password);
        $folder = $this->clientStorage->createFolder($outputFolderPath);
        $certificateFile = $this->clientStorage->saveFile($filename, $content, $folder);

        return $certificateFile; 
    }
}
