<?php

namespace OCA\Libresign\AppInfo;

use OCA\Files\Event\LoadSidebar;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Helper\JSConfigHelper;
use OCA\Libresign\Listener\LoadSidebarListener;
use OCA\Libresign\Storage\ClientStorage;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'libresign';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function boot(IBootContext $context): void {
		$this->registerHooks($context);
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
	
	private function registerHooks($context): void {
		$request = $context->getServerContainer()->get(IRequest::class);
		$path = $request->getRawPathInfo();
		$regex = '/' . self::APP_ID . '\/sign\/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/';
		if (!preg_match($regex, $path)) {
			return;
		}
		$jsConfigHelper = new JSConfigHelper(
			$context->getServerContainer()->get(ISession::class),
			$request,
			$context->getServerContainer()->get(FileMapper::class),
			$context->getServerContainer()->get(FileUserMapper::class),
			$this->getContainer()->get(IL10N::class),
			$context->getServerContainer()->get(IRootFolder::class),
			$context->getServerContainer()->get(IURLGenerator::class)
		);
		Util::connectHook('\OCP\Config', 'js', $jsConfigHelper, 'extendJsConfig');
	}
}
