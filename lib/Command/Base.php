<?php

declare(strict_types=1);

namespace OCA\Libresign\Command;

use OC\Core\Command\Base as CommandBase;
use OCA\Libresign\Service\InstallService;

class Base extends CommandBase {
	/** @var InstallService */
	public $installService;

	public function __construct(
		InstallService $installService
	) {
		parent::__construct();
		$this->installService = $installService;
	}

	protected function installJava(): void {
		$this->installService->installJava();
	}

	protected function uninstallJava(): void {
		$this->installService->uninstallJava();
	}

	protected function installJSignPdf(): void {
		$this->installService->installJSignPdf();
	}

	protected function uninstallJSignPdf(): void {
		$this->installService->uninstallJSignPdf();
	}

	protected function installCli(): void {
		$this->installService->installCli();
	}

	protected function uninstallCli(): void {
		$this->installService->uninstallCli();
	}

	protected function installCfssl(): void {
		$this->installService->installCfssl();
	}

	protected function uninstallCfssl(): void {
		$this->installService->uninstallCfssl();
	}
}
