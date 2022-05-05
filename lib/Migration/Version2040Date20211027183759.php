<?php

declare(strict_types=1);

namespace OCA\Libresign\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCA\Libresign\Service\PdfParserService;
use OCP\DB\ISchemaWrapper;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class Version2040Date20211027183759 extends SimpleMigrationStep {
	/** @var IRootFolder*/
	private $root;
	/** @var PdfParserService */
	private $PdfParserService;
	/** @var array */
	private $rows;
	public function __construct(IDBConnection $connection,
								IRootFolder $root,
								PdfParserService $PdfParserService) {
		$this->connection = $connection;
		$this->install = $install;
		$this->config = $config;
		$this->systemConfig = $systemConfig;
		$this->rootFolder = $rootfolder;
		$this->root = $root;
		$this->PdfParserService = $PdfParserService;
	}

	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$query = $this->connection->getQueryBuilder();
		$query->select('id', 'node_id', 'user_id')
			->from('libresign_file', 'f');
		$this->rows = $query->execute()->fetchAll();
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('libresign_file');

		if (!$table->hasColumn('metadata')) {
			$table->addColumn('metadata', Types::TEXT, [
				'notnull' => false
			]);
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$cli = $this->getLibesignCli();
		foreach ($this->rows as $row) {
			$userFolder = $this->root->getUserFolder($row['user_id']);
			/** @var File[] */
			$file = $userFolder->getById($row['node_id']);
			if (count($file) >= 1) {
				$data = $this->PdfParserService->getMetadata($file[0]->getPath());
				$json = json_encode($data);
				$query = $this->connection->getQueryBuilder();
				$query
					->update('libresign_file')
					->set('metadata', $query->createNamedParameter($json))
					->where($query->expr()->eq('id', $query->createNamedParameter($row['id'])));
		
				$query->executeStatement();
			}
		}
	}

	private function getMetadataFromCli(string $cli, string $filePath): array {
		$fullPath = $this->getDataDir() . $filePath;
		$json = shell_exec($cli . ' info ' . $fullPath);
		$array = json_decode($json, true);
		$output = [
			'p' => count($array['pages']),
			'extension' => 'pdf',
		];
		foreach ($array['pages'] as $page) {
			$output['d'][] = [
				'w' => $page['width'],
				'h' => $page['height'],
			];
		}
		return $output;
	}

	private function getDataDir(): string {
		return $this->systemConfig->getValue('datadirectory', \OC::$SERVERROOT . '/data/');
	}

	private function getLibesignCli(): string {
		$path = $this->config->getAppValue(Application::APP_ID, 'libresign_cli_path');
		if (!file_exists($path)) {
			$this->install->run(new StringInput('--cli'), new NullOutput());
			$path = $this->config->getAppValue(Application::APP_ID, 'libresign_cli_path');
		}
		return $path;
	}
}
