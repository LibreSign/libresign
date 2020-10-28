<?php

namespace OCA\Signer\Service;

use OCA\Signer\AppInfo\Application;
use OCA\Signer\Handler\CfsslServerHandler;
use OCP\IAppConfig;

class AdminSignatureService
{
    /** @var CfsslServerHandler */
    private $cfsslHandler;

    /** @var IAppConfig */
    private $config;

    public function __construct(CfsslServerHandler $cfsslHandler, IAppConfig $config)
    {
        $this->cfsslHandler = $cfsslHandler;
        $this->config = $config;
    }

    public function generate(
        string $commonName,
        string $country,
        string $organization,
        string $organizationUnit
    ) {
        $key = bin2hex(random_bytes(16));
        $this->config->setValue(Application::APP_ID, 'authkey', $key);
        $this->config->setValue(Application::APP_ID, 'commonName', $commonName);
        $this->config->setValue(Application::APP_ID, 'country', $country);
        $this->config->setValue(Application::APP_ID, 'organization', $organization);
        $this->config->setValue(Application::APP_ID, 'organizationUnit', $organizationUnit);

        $this->cfsslHandler->createConfigServer(
            $commonName,
            $country,
            $organization,
            $organizationUnit,
            $key
        );
    }

    public function loadKeys(){
        return [
            'commonName' => $this->config->getValue(Application::APP_ID, 'commonName'),
            'country' => $this->config->getValue(Application::APP_ID, 'country'),
            'organization' => $this->config->getValue(Application::APP_ID, 'organization'),
            'organizationUnit' => $this->config->getValue(Application::APP_ID, 'organizationUnit'),
        ];
    }
}
