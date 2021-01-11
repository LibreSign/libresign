<?php

namespace OCA\Dsv\AppInfo;

use OCP\AppFramework\App;

class Application extends App
{
    const APP_NAME = 'dsv';

    /**
     * Application constructor.
     *
     * @throws \OCP\AppFramework\QueryException
     */
    public function __construct(array $params = [])
    {
        parent::__construct(self::APP_NAME, $params);

        $container = $this->getContainer();
        $server = $container->getServer();
        $eventDispatcher = $server->getEventDispatcher();

        $eventDispatcher->addListener('OCA\Files::loadAdditionalScripts', function () {
            \OCP\Util::addStyle(self::APP_NAME, 'tabview');
            \OCP\Util::addScript(self::APP_NAME, 'tabview');
            \OCP\Util::addScript(self::APP_NAME, 'plugin');
        });
    }
}
