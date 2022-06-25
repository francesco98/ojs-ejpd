<?php
import('lib.pkp.classes.plugins.GenericPlugin');

class SubscriptionMirrorPlugin extends GenericPlugin
{
	public function register($category, $path, $mainContextId = NULL): bool
	{

		// Register the plugin even when it is not enabled
		$success = parent::register($category, $path);

		if ($success && $this->getEnabled()) {
			HookRegistry::register('subscriptiontypedao::_insertobject', [$this, 'insertSubscriptionTypeData']);
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
		return __('plugins.generic.subscriptionMirror.displayName');
	}

	/**
	 * Provide a description for this plugin
	 *
	 * The description will appear in the plugins list where editors can
	 * enable and disable plugins.
	 */
	public function getDescription(): string
	{
		return __('plugins.generic.subscriptionMirror.description');
	}

	/**
	 * Enable the settings form in the site-wide plugins list
	 *
	 * @return string
	 */
	public function isSitePlugin()
	{
		return true;
	}

	function updateSubscriptionTypeData($hookName, $params): bool
	{
		return false;
	}

	function insertSubscriptionTypeData($hookName, $params): bool
	{
		/* @var $subscriptionTypeDAO SubscriptionTypeDAO */
		$subscriptionTypeDAO = DAORegistry::getDAO('SubscriptionTypeDAO');

		$currentSubscriptionType = $subscriptionTypeDAO->newDataObject();
		$currentSubscriptionType->setJournalId($params[1][0]);
		$currentSubscriptionType->setCost($params[1][1]);
		$currentSubscriptionType->setCurrencyCodeAlpha($params[1][2]);
		$currentSubscriptionType->setDuration($params[1][3]);
		$currentSubscriptionType->setFormat($params[1][4]);
		$currentSubscriptionType->setInstitutional($params[1][5]);
		$currentSubscriptionType->setMembership($params[1][6]);
		$currentSubscriptionType->setDisablePublicDisplay($params[1][7]);
		$currentSubscriptionType->setSequence($params[1][8]);

		$journals = Application::getContextDAO()->getNames();

		foreach ($journals as $journalId => $journalName) {
			if ($journalId != $currentSubscriptionType->getJournalId()) {
				$subscriptionType = clone $currentSubscriptionType;
				$subscriptionType->setJournalId($journalId);

				$journalSubscriptionTypes = $subscriptionTypeDAO->getByJournalId($journalId)->toArray();

				if (!self::isSubscriptionTypePresent($subscriptionType, $journalSubscriptionTypes)) {
					$subscriptionTypeDAO->insertObject($subscriptionType);
				}
			}
		}

		return true;
	}

	static function isSubscriptionTypeEqual(SubscriptionType $obj1, SubscriptionType $obj2): bool
	{
		return $obj1->getJournalId() == $obj2->getJournalId()
			&& $obj1->getCost() == $obj2->getCost()
			&& $obj1->getDuration() == $obj2->getDuration()
			&& $obj1->getInstitutional() == $obj2->getInstitutional()
			&& $obj1->getMembership() == $obj2->getMembership();
	}

	static function isSubscriptionTypePresent(SubscriptionType $target, array $array): bool
	{
		$found = false;

		foreach ($array as $element) {
			if (self::isSubscriptionTypeEqual($target, $element)) {
				$found = true;
			}
		}

		return $found;
	}


}
