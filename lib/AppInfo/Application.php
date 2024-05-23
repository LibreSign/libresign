<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\AppInfo;

use OCA\Files\Event\LoadSidebar;
use OCA\Libresign\Activity\Listener as ActivityListener;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Files\TemplateLoader as FilesTemplateLoader;
use OCA\Libresign\Listener\BeforeNodeDeletedListener;
use OCA\Libresign\Listener\LoadSidebarListener;
use OCA\Libresign\Listener\MailNotifyListener;
use OCA\Libresign\Listener\NotificationListener;
use OCA\Libresign\Listener\SignedListener;
use OCA\Libresign\Middleware\GlobalInjectionMiddleware;
use OCA\Libresign\Middleware\InjectionMiddleware;
use OCA\Libresign\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
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
		$server = $context->getServerContainer();

		/** @var IEventDispatcher $dispatcher */
		$dispatcher = $server->get(IEventDispatcher::class);

		FilesTemplateLoader::register($dispatcher);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerMiddleWare(GlobalInjectionMiddleware::class, true);
		$context->registerMiddleWare(InjectionMiddleware::class);

		$context->registerNotifierService(Notifier::class);

		$context->registerEventListener(LoadSidebar::class, LoadSidebarListener::class);
		$context->registerEventListener(BeforeNodeDeletedEvent::class, BeforeNodeDeletedListener::class);
		$context->registerEventListener(SignedEvent::class, SignedListener::class);

		// Activity listeners
		$context->registerEventListener(SendSignNotificationEvent::class, ActivityListener::class);

		// Notification listeners
		$context->registerEventListener(SendSignNotificationEvent::class, NotificationListener::class);

		// MailNotify listener
		$context->registerEventListener(SendSignNotificationEvent::class, MailNotifyListener::class);
	}
}
