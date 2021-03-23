<?php

declare(strict_types=1);

namespace OCA\Libresign\Command;

use OC\Core\Command\Base;
use OCA\Libresign\AppInfo\Application;
use OCP\IURLGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DocsSwaggerGen extends Base {
	/** @var IURLGenerator */
	private $urlGenerator;
	public function __construct(IURLGenerator $urlGenerator) {
		parent::__construct();
		$this->urlGenerator = $urlGenerator;
	}

	protected function configure() {
		$this
			->setName('libresign:swagger-gen')
			->setDescription('Generate Swagger documentation');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$configFilePath = __DIR__.'/../../docs/.vuepress/config.js';
		$configJs = file_get_contents($configFilePath);
		$url = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID.'.page.index');
		$configJs = preg_replace('/{{APP_URL}}/', $url, $configJs);
		file_put_contents($configFilePath, $configJs);

		$openapi = \OpenApi\scan(__DIR__.'/../Controller');
		$output->writeln($openapi->toYaml());
		return Command::SUCCESS;
	}
}
