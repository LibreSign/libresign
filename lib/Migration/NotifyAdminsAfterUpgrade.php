<?php

declare(strict_types=1);

namespace OCA\Libresign\Migration;

use OCA\Libresign\AppInfo\Application as AppInfoApplication;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Notification\IManager as NotificationManager;

class NotifyAdminsAfterUpgrade implements IRepairStep {
	public function __construct(
		private IUserManager $userManager,
		private NotificationManager $notificationManager,
	) {
	}

	public function getName(): string {
		return 'Notify admins after LibreSign upgrade';
	}

	public function run(IOutput $output): void {
		$admins = $this->userManager->search('', 1, 0, ['admin']);
		foreach ($admins as $admin) {
			$notification = $this->notificationManager->createNotification();
			$notification
				->setApp(AppInfoApplication::APP_ID)
				->setUser($admin->getUID())
				->setDateTime(new \DateTime())
				->setObject('upgrade', '1')
				->setSubject('libresign_upgrade', [
					'message' => 'LibreSign has been updated! Consider supporting the project: https://libresign.coop'
				]);
			$this->notificationManager->notify($notification);
		}
	}
}
