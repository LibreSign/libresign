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
        string $organizationUnit,
        string $cfsslUri,
        string $configPath
    ) {
        $key = bin2hex(random_bytes(16));

        $this->cfsslHandler->createConfigServer(
            $commonName,
            $country,
            $organization,
            $organizationUnit,
            $key,
            $configPath
        );

        $this->config->setAppValue(Application::APP_ID, 'authkey', $key);
        $this->config->setAppValue(Application::APP_ID, 'commonName', $commonName);
        $this->config->setAppValue(Application::APP_ID, 'country', $country);
        $this->config->setAppValue(Application::APP_ID, 'organization', $organization);
        $this->config->setAppValue(Application::APP_ID, 'organizationUnit', $organizationUnit);
        $this->config->setAppValue(Application::APP_ID, 'cfsslUri', $cfsslUri);
        $this->config->setAppValue(Application::APP_ID, 'configPath', $configPath);
    }

    public function loadKeys(){
        return [
            'commonName' => $this->config->getAppValue(Application::APP_ID, 'commonName'),
            'country' => $this->config->getAppValue(Application::APP_ID, 'country'),
            'organization' => $this->config->getAppValue(Application::APP_ID, 'organization'),
            'organizationUnit' => $this->config->getAppValue(Application::APP_ID, 'organizationUnit'),
            'cfsslUri' => $this->config->getAppValue(Application::APP_ID, 'cfsslUri'),
            'configPath' => $this->config->getAppValue(Application::APP_ID, 'configPath'),
        ];
    }
}
