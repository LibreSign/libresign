<?php

namespace OCA\Libresign\AppInfo;

use OCA\Files\Event\LoadSidebar;
use OCA\Libresign\Listener\LoadSidebarListener;
use OCA\Libresign\Storage\ClientStorage;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'libresign';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function boot(IBootContext $context): void {
		$this->registerHooks();
	}

	public function register(IRegistrationContext $context): void {
		include_once __DIR__ . '/../../vendor/autoload.php';
		$context->registerEventListener(
			LoadSidebar::class,
			LoadSidebarListener::class
		);

		$context->registerService(ClientStorage::class, function ($c) {
			return new ClientStorage(
				$c->query('ServerContainer')->getUserFolder()
			);
		});
	}
	
	private function registerHooks(): void {
		Util::connectHook('\OCP\Config', 'js', $this, 'extendJsConfig');
	}

	/**
	 * @param array $settings
	 */
	public function extendJsConfig(array $settings) {
		$appConfig = json_decode($settings['array']['oc_appconfig'], true);

		$appConfig['libresign'] = [
			'user' => [
				'name' => 'Jhon Doe'
			],
			'sign' => [
				'pdf' => 'http://asfadsf.asdfasdf',
				'filename' => 'Contract',
				'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.'
			]
		];

		$settings['array']['oc_appconfig'] = json_encode($appConfig);
	}
}
