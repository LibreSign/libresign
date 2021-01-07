<?php

namespace OCA\Signer\Service;

use OCA\Signer\AppInfo\Application;
use OCA\Signer\Handler\CfsslHandler;
use OCA\Signer\Storage\ClientStorage;
use OCP\IAppConfig;

class SignatureService
{
    /** @var CfsslHandler */
    private $cfsslHandler;

    /** @var ClientStorage */
    private $clientStorage;

    /** @var IAppConfig */
    private $config;

    public function __construct(
        CfsslHandler $cfsslHandler,
        ClientStorage $clientStorage,
        IAppConfig $config
    ) {
        $this->cfsslHandler = $cfsslHandler;
        $this->clientStorage = $clientStorage;
        $this->config = $config;
    }

    public function generate(
        string $commonName,
        array $hosts,
        string $country,
        string $organization,
        string $organizationUnit,
        string $certificatePath,
        string $password
    ) {
        $content = $this->cfsslHandler->generateCertificate(
            $commonName,
            $hosts,
            $country,
            $organization,
            $organizationUnit,
            $password
        );

        $folder = $this->clientStorage->createFolder($certificatePath);
        $certificateFile = $this->clientStorage->saveFile($commonName.'.pfx', $content, $folder);

        return $certificateFile->getInternalPath();
    }

    public function check()
    {
        return [
            'hasRootCert' => null !== $this->config->getValue(Application::APP_ID, 'authkey'),
        ];
    }
}
