<?php

namespace OCA\Signer\Handler;

use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;
use OC\Files\Node\File;
use OCA\Signer\Storage\ClientStorage;

class JSignerHandler
{
    public function signExistingFile(
        File $inputFile,
        File $certificate,
        string $password
    ): array {
        $param = (new JSignParam())
            ->setCertificate($certificate->getContent())
            ->setPdf($inputFile->getContent())
            ->setPassword($password)
            ->setTempPath('/tmp/')
        ;

        $jSignPdf = new JSignPDF($param);
        $contentFileSigned = $jSignPdf->sign();

        return [
            'signed_'.$inputFile->getName(),
            $contentFileSigned,
        ];
    }
}
