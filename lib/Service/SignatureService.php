<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CfsslHandler;
use OCA\Libresign\Storage\ClientStorage;
use OCP\IConfig;

class SignatureService
{
    /** @var CfsslHandler */
    private $cfsslHandler;

    /** @var ClientStorage */
    private $clientStorage;

    /** @var IConfig */
    private $config;

    public function __construct(
        CfsslHandler $cfsslHandler,
        ClientStorage $clientStorage,
        IConfig $config
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
            $password,
            $this->config->getAppValue(Application::APP_ID, 'cfsslUri')
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
