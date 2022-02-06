<?php

namespace OCA\Libresign\AppInfo;

use OCA\Files\Event\LoadSidebar;
use OCA\Libresign\Event\SignedEvent;
use OCA\Libresign\Listener\BeforeNodeDeletedListener;
use OCA\Libresign\Listener\LoadSidebarListener;
use OCA\Libresign\Listener\SignedListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;

/**
 * @codeCoverageIgnore
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'libresign';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function boot(IBootContext $context): void {
	}

	public function register(IRegistrationContext $context): void {
		include_once __DIR__ . '/../../vendor/autoload.php';
		$context->registerEventListener(LoadSidebar::class, LoadSidebarListener::class);
		$context->registerEventListener(BeforeNodeDeletedEvent::class, BeforeNodeDeletedListener::class);
		$context->registerEventListener(SignedEvent::class, SignedListener::class);
	}
}
