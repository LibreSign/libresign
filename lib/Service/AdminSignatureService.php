<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CfsslServerHandler;
use OCP\IConfig;

class AdminSignatureService
{
    /** @var CfsslServerHandler */
    private $cfsslHandler;

    /** @var IConfig */
    private $config;

    public function __construct(CfsslServerHandler $cfsslHandler, IConfig $config)
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

        $this->cfsslHandler->createConfigServer(
            $commonName,
            $country,
            $organization,
            $organizationUnit,
            $key
        );

        $this->config->setValue(Application::APP_ID, 'authkey', $key);
        $this->config->setValue(Application::APP_ID, 'commonName', $commonName);
        $this->config->setValue(Application::APP_ID, 'country', $country);
        $this->config->setValue(Application::APP_ID, 'organization', $organization);
        $this->config->setValue(Application::APP_ID, 'organizationUnit', $organizationUnit);
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
