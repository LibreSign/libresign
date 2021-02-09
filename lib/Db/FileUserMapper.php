<?php

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * Class FileUserMapper
 *
 * @package OCA\Libresign\DB
 *
 * @method FileUser insert(FileUser $entity)
 * @method FileUser update(FileUser $entity)
 * @method FileUser insertOrUpdate(FileUser $entity)
 * @method FileUser delete(FileUser $entity)
 */
class FileUserMapper extends QBMapper {

	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_file_user');
	}
}
