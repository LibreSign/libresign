<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files\Event\LoadSidebar;
use OCA\Libresign\Activity\Listener as ActivityListener;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Files\TemplateLoader as FilesTemplateLoader;
use OCA\Libresign\Listener\BeforeNodeDeletedListener;
use OCA\Libresign\Listener\LoadAdditionalListener;
use OCA\Libresign\Listener\LoadSidebarListener;
use OCA\Libresign\Listener\MailNotifyListener;
use OCA\Libresign\Listener\NotificationListener;
use OCA\Libresign\Listener\SignedListener;
use OCA\Libresign\Listener\UserDeletedListener;
use OCA\Libresign\Middleware\GlobalInjectionMiddleware;
use OCA\Libresign\Middleware\InjectionMiddleware;
use OCA\Libresign\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\User\Events\UserDeletedEvent;

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

		// Files newFile listener
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalListener::class);

		// Activity listeners
		$context->registerEventListener(SendSignNotificationEvent::class, ActivityListener::class);

		// Notification listeners
		$context->registerEventListener(SendSignNotificationEvent::class, NotificationListener::class);

		// MailNotify listener
		$context->registerEventListener(SendSignNotificationEvent::class, MailNotifyListener::class);

		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
	}
}
