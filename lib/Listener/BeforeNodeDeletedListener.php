<?php

namespace OCA\Libresign\Listener;

use OCA\Libresign\Db\FileMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\File;
use OCP\IDBConnection;

class BeforeNodeDeletedListener implements IEventListener {
	/** @var FileMapper */
	private $fileMapper;
	/** @var IDBConnection */
	private $db;

	public function __construct(
		FileMapper $fileMapper,
		IDBConnection $db
	) {
		$this->fileMapper = $fileMapper;
		$this->db = $db;
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforeNodeDeletedEvent) {
			return;
		}
		if ($event->getNode() instanceof File) {
			$nodeId = $event->getNode()->getId();
			$type = $this->fileMapper->getFileType($nodeId);
			if ($type !== 'not_libresign_file') {
				$field = $type === 'file' ? 'node_id' : 'file_id';
				$qb = $this->db->getQueryBuilder();
				$qb->delete('libresign_' . $type)
					->where($qb->expr()->eq($field, $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
					->execute();
			}
		}
	}
}
