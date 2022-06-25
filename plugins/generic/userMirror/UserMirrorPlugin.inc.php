<?php
import('lib.pkp.classes.plugins.GenericPlugin');

class UserMirrorPlugin extends GenericPlugin
{
	public function register($category, $path, $mainContextId = NULL): bool
	{

		// Register the plugin even when it is not enabled
		$success = parent::register($category, $path);

		if ($success && $this->getEnabled()) {
			HookRegistry::register('userdao::_updateobject', [$this, 'updateUserData'], HOOK_SEQUENCE_CORE);
		}

		return $success;
	}

	/**
	 * Provide a name for this plugin
	 *
	 * The name will appear in the plugins list where editors can
	 * enable and disable plugins.
	 */
	public function getDisplayName(): string
	{
		return __('plugins.generic.userMirror.displayName');
	}

	/**
	 * Provide a description for this plugin
	 *
	 * The description will appear in the plugins list where editors can
	 * enable and disable plugins.
	 */
	public function getDescription(): string
	{
		return __('plugins.generic.userMirror.description');
	}

	function updateUserData($hookName, $params): bool
	{
		// Safety check to be sure last element of the array is the user id
		if (!self::endsWith($params[0], "user_id = ?")) {
			return false;
		}

		$userId = end($params[1]);

		$this->updateUserSettings($userId);
		$this->updateUserRoles($userId);

		return false;
	}

	function updateUserRoles($userId)
	{
		/** @var $roleDAO RoleDAO */
		$roleDAO = DAORegistry::getDAO('RoleDAO');
		$roles = $roleDAO->getByUserId($userId);

		$contexts = Application::getContextDAO()->getNames();

		/* @var $userGroupDao UserGroupDAO */
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

		foreach ($contexts as $contextId => $contextName) {
			$contextRoles = $roleDAO->getByUserId($userId, $contextId);
			foreach ($roles as $role) {
				if (!in_array($role->getId(), $contextRoles)) {
					/* @var $defaultGroup UserGroup */
					$defaultGroup = $userGroupDao->getDefaultByRoleId($contextId, $role->getId());
					$userGroupDao->assignUserToGroup($userId, $defaultGroup->getId());
				}
			}
		}
	}

	function updateUserSettings($userId)
	{
		$locales = AppLocale::getSupportedLocales();
		$primaryLocale = AppLocale::getPrimaryLocale();

		/* @var $userDao UserDAO */
		$userDao = DAORegistry::getDAO('UserDAO');
		$user = $userDao->getById($userId);

		// Information for primary locale are always available
		$givenName = $user->getGivenName($primaryLocale);
		$familyName = $user->getFamilyName($primaryLocale);
		$affiliation = $user->getAffiliation($primaryLocale);

		$updated = false;

		foreach ($locales as $localeId => $localeValue) {
			if (!self::isUserSettings($user, $localeId)) {
				$user->setGivenName($givenName, $localeId);
				$user->setFamilyName($familyName, $localeId);
				$user->setAffiliation($affiliation, $localeId);

				$updated = true;
			}
		}

		if ($updated) {
			$userDao->updateObject($user);
		}
	}

	static function isUserSettings(User $user, $locale): bool
	{
		return !is_null($user->getGivenName($locale))
			&& !is_null($user->getFamilyName($locale));

	}

	static function endsWith($haystack, $needle): bool
	{
		$length = strlen($needle);
		if (!$length) {
			return true;
		}
		return substr($haystack, -$length) === $needle;
	}
}
