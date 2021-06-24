<?php

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * Class FileMapper
 *
 * @package OCA\Libresign\DB
 *
 * @codeCoverageIgnore
 * @method File insert(File $entity)
 * @method File update(File $entity)
 * @method File insertOrUpdate(File $entity)
 * @method File delete(File $entity)
 */
class AccountFileMapper extends QBMapper {
	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_account_file');
	}
}
