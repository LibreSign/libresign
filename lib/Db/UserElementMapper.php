<?php

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * Class FileElementsMapper
 *
 * @package OCA\Libresign\DB
 *
 * @codeCoverageIgnore
 * @method UserElement insert(UserElement $entity)
 * @method UserElement update(UserElement $entity)
 * @method UserElement insertOrUpdate(UserElement $entity)
 * @method UserElement delete(UserElement $entity)
 */
class UserElementMapper extends QBMapper {
	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_user_element');
	}
}
