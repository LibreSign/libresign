<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\Handler\JLibresignHandler;
use OCA\Libresign\Storage\ClientStorage;

class LibresignService
{
    /** @var JLibresignHandler */
    private $libresignHandler;

    /** @var ClientStorage */
    private $clientStorage;

    public function __construct(JLibresignHandler $libresignHandler, ClientStorage $clientStorage)
    {
        $this->libresignHandler = $libresignHandler;
        $this->clientStorage = $clientStorage;
    }

    public function sign(string $inputFilePath, string $outputFolderPath, string $certificatePath, string $password){
        $file = $this->clientStorage->getFile($inputFilePath);
        $certificate = $this->clientStorage->getFile($certificatePath);

        list($filename, $content) = $this->libresignHandler->signExistingFile($file, $certificate, $password);
        $folder = $this->clientStorage->createFolder($outputFolderPath);
        $certificateFile = $this->clientStorage->saveFile($filename, $content, $folder);

        return $certificateFile; 
    }
}
