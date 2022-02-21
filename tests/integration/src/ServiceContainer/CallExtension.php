<?php

namespace LibreCode\Server\ServiceContainer;

use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class CallExtension implements Extension {
	/**
	 * Returns the extension config key.
	 *
	 * @return string
	 */
	public function getConfigKey()
	{
		return 'php_server';
	}

	/**
	 * Initializes other extensions.
	 *
	 * This method is called immediately after all extensions are activated but
	 * before any extension `configure()` method is called. This allows extensions
	 * to hook into the configuration of other extensions providing such an
	 * extension point.
	 *
	 * @param ExtensionManager $extensionManager
	 */
	public function initialize(ExtensionManager $extensionManager)
	{

	}

	/**
	 * Setups configuration for the extension.
	 *
	 * @param ArrayNodeDefinition $builder
	 */
	public function configure(ArrayNodeDefinition $builder)
	{
		$builder
			->children()
				->booleanNode('verbose')
					->info('Enables/disables verbose mode')
					->defaultFalse()
				->end()
				->scalarNode('rootDir')
					->info('Specifies http root dir')
					->defaultValue('/var/www/html')
				->end()
			->end()
		;


	}

	/**
	 * Loads extension services into temporary container.
	 *
	 * @param ContainerBuilder $container
	 * @param array $config
	 */
	public function load(ContainerBuilder $container, array $config)
	{
		$definition = (new Definition('LibreCode\Server\RunServerListener'))
			->addTag('event_dispatcher.subscriber')
			->setArguments([$this->getVerboseLevel($container), $config['rootDir']])
		;

		$container->setDefinition('php_server.listener', $definition);
	}

	private function getVerboseLevel(ContainerBuilder $container): ?int {
		$input = $container->get('cli.input');
		if ($input->hasParameterOption('--verbose')) {
			$verbose = $input->getParameterOption('--verbose');
			return (int)($verbose ?? 0);
		}
		if ($input->hasParameterOption('-v')) {
			$verbose = $input->getParameterOption('-v');
			return strlen($verbose);
		}
		return null;
	}

	/**
	 * You can modify the container here before it is dumped to PHP code.
	 *
	 * @param ContainerBuilder $container
	 *
	 * @api
	 */
	public function process(ContainerBuilder $container)
	{

	}
}
