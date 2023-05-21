<?php

namespace OCA\Libresign\AppInfo;

use OCA\Files\Event\LoadSidebar;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Files\TemplateLoader as FilesTemplateLoader;
use OCA\Libresign\Listener\BeforeNodeDeletedListener;
use OCA\Libresign\Listener\CSPListener;
use OCA\Libresign\Listener\LoadSidebarListener;
use OCA\Libresign\Listener\NotificationListener;
use OCA\Libresign\Listener\SignedListener;
use OCA\Libresign\Middleware\InjectionMiddleware;
use OCA\Libresign\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

/**
 * @codeCoverageIgnore
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'libresign';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function boot(IBootContext $context): void {
		$server = $context->getServerContainer();

		/** @var IEventDispatcher $dispatcher */
		$dispatcher = $server->get(IEventDispatcher::class);

		FilesTemplateLoader::register($dispatcher);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerMiddleWare(InjectionMiddleware::class);

		$context->registerNotifierService(Notifier::class);

		$context->registerEventListener(LoadSidebar::class, LoadSidebarListener::class);
		$context->registerEventListener(BeforeNodeDeletedEvent::class, BeforeNodeDeletedListener::class);
		$context->registerEventListener(SignedEvent::class, SignedListener::class);
		$context->registerEventListener(SendSignNotificationEvent::class, NotificationListener::class);
		$context->registerEventListener(AddContentSecurityPolicyEvent::class, CSPListener::class);
	}
}
