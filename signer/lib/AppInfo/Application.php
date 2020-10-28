<?php

namespace OCA\Signer\AppInfo;

use OCA\Files\Event\LoadSidebar;
use OCA\Signer\Listener\LoadSidebarListener;
use OCA\Signer\Storage\ClientStorage;
use OCP\AppFramework\App;
use OCP\EventDispatcher\IEventDispatcher;

class Application extends App
{
    public const APP_ID = 'signer';

    public function __construct()
    {
        parent::__construct(self::APP_ID);
        $container = $this->getContainer();
        $dispatcher = $container->query(IEventDispatcher::class);
        $dispatcher->addServiceListener(LoadSidebar::class, LoadSidebarListener::class);

        $container->registerService(ClientStorage::class, function ($c) {
            return new ClientStorage(
                $c->query('ServerContainer')->getUserFolder()
            );
        });
    }
}
