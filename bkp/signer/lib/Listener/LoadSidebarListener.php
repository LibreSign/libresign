<?php

namespace OCA\Signer\Listener;

use OCA\Files\Event\LoadSidebar;
use OCA\Signer\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

class LoadSidebarListener implements IEventListener
{
    public function handle(Event $event): void
    {
        if (!($event instanceof LoadSidebar)) {
            return;
        }

        Util::addScript(Application::APP_ID, 'signer-tab');
    }
}
