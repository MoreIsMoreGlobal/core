<?php
/**
 * @authod Piotr Mrowczynski <piotr@owncloud.com>
 *
 * @copyright Copyright (c) 2017, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC;

use OCP\IOrganisationManager;
use \OC\User\Account;
use \OC\User\AccountMapper;
use \OC\User\AccountTermMapper;
use OC\User\User;
use OC\Group\BackendGroup;
use \OC\Group\GroupMapper;
use \OC\Group\Group;
use OCP\IDBConnection;
use OCP\IConfig;
use OCP\AppFramework\Db\Entity;
use OC\MembershipManager;

/**
 * Class OrganisationManager
 *
 * @package OC
 */
class OrganisationManager implements IOrganisationManager {

	/** @var IConfig $config */
	private $config;

	/** @var \OC\User\Manager $userManager */
	private $userManager;

	/** @var \OC\Group\Manager $groupManager */
	private $groupManager;

	/** @var \OC\User\AccountMapper $userMapper */
	private $accountMapper;

	/** @var \OC\Group\GroupMapper $groupMapper */
	private $groupMapper;

	/** @var \OC\MembershipManager $groupMapper */
	private $membershipManager;

	/**
	 * @param \OC\User\Manager $userManager
	 * @param \OC\Group\Manager $groupManager
	 */
	public function __construct(IDBConnection $db, IConfig $config, \OC\User\Manager $userManager, \OC\Group\Manager $groupManager) {
		$this->config = $config;
		$this->accountMapper = new AccountMapper($config, $db, new AccountTermMapper($db));
		$this->groupMapper = new GroupMapper($db);
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->membershipManager = new MembershipManager($db, $this->accountMapper, $this->groupMapper);
	}

	/**
	 * @param BackendGroup $backendGroup
	 * @return BackendGroup the saved entity with the set id
	 */
	public function deleteGroup($backendGroup) {
		// Delete first all group members
		$this->membershipManager->deleteGroupMembers($backendGroup);

		// Delete group itself
		/** @var BackendGroup $deletedBackendGroup */
		$deletedBackendGroup = $this->groupMapper->delete($backendGroup);
		return $deletedBackendGroup;
	}

	/**
	 * Get all group users (User objects) which are within group
	 * identified by group id $gid
	 *
	 * @param string $gid
	 * @return User[]
	 */
	public function getUsersInGroupByGid($gid) {
		$accounts = $this->membershipManager->getGroupMembersByGid($gid);
		return $this->convertAccountsToUsers($accounts);
	}

	/**
	 * Get all group users (User objects) which are within group
	 * by group backend
	 *
	 * @param BackendGroup $backendGroup
	 * @return User[]
	 */
	public function getUsersInGroup($backendGroup) {
		$accounts = $this->membershipManager->getGroupMembers($backendGroup);
		return $this->convertAccountsToUsers($accounts);
	}

	/**
	 * Convert Account objects to User objects
	 *
	 * @param Account[] $accounts
	 * @return User[]
	 */
	protected function convertAccountsToUsers($accounts) {
		$users = [];
		foreach ($accounts as $account) {
			$user = $this->getUserObject($account);
			if (!is_null($user)) {
				$users[$account->getUserId()] = $user;
			}
		}
		return $users;
	}

	/**
	 * @param \OC\Group\BackendGroup $backendGroup
	 * @return \OC\Group\Group
	 */
	protected function getGroupObject($backendGroup) {
		return new Group($backendGroup, $this, $this->groupManager);
	}

	/**
	 * @param \OC\User\Account $account
	 * @return \OC\User\User
	 */
	protected function getUserObject($account) {
		return new User($account, $this, $this->userManager, $this->config, null, \OC::$server->getEventDispatcher() );
	}
}
