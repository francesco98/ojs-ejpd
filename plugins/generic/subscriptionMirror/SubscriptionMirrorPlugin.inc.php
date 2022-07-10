<?php
import('lib.pkp.classes.plugins.GenericPlugin');

class SubscriptionMirrorPlugin extends GenericPlugin
{
	public function register($category, $path, $mainContextId = NULL): bool
	{

		// Register the plugin even when it is not enabled
		$success = parent::register($category, $path);

		if ($success && $this->getEnabled()) {
			HookRegistry::register('subscriptiontypeform::execute', [$this, 'insertSubscriptionTypeData'], HOOK_SEQUENCE_CORE);
			HookRegistry::register('subscriptiontypedao::_deletebyid', [$this, 'deleteSubscriptionTypeData'], HOOK_SEQUENCE_CORE);
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

	function deleteSubscriptionTypeData($hookName, $params): bool
	{
		if (!self::endsWith($params[0], "type_id = ?")) {
			return false;
		}

		$typeId = $params[1][0];

		/* @var $subscriptionTypeDAO SubscriptionTypeDAO */
		$subscriptionTypeDAO = DAORegistry::getDAO('SubscriptionTypeDAO');

		/* @var $subscriptionType SubscriptionType */
		$currentSubscriptionType = $subscriptionTypeDAO->getById($typeId);

		if(!is_null($currentSubscriptionType)) {
			$journals = Application::getContextDAO()->getNames();

			foreach ($journals as $journalId => $journalName) {
				$subscriptionType = clone $currentSubscriptionType;
				$subscriptionType->setJournalId($journalId);

				$journalSubscriptionTypes = $subscriptionTypeDAO->getByJournalId($journalId)->toArray();
				$existingTypeId = self::isSubscriptionTypePresent($subscriptionType, $journalSubscriptionTypes);

				$subscriptionTypeDAO->deleteById($existingTypeId, $journalId, false);
			}
		}

		return false;
	}

	function insertSubscriptionTypeData($hookName, $params): bool
	{

		/** @var $subscriptionTypeForm SubscriptionTypeForm */
		$subscriptionTypeForm = $params[0];

		$locale = $subscriptionTypeForm->getRequiredLocale();

		$name = $subscriptionTypeForm->getData('name');
		$description = $subscriptionTypeForm->getData('description');

		$membership = $subscriptionTypeForm->getData('membership');
		$disablePublicDisplay = $subscriptionTypeForm->getData('disable_public_display');

		/* @var $subscriptionTypeDAO SubscriptionTypeDAO */
		$subscriptionTypeDAO = DAORegistry::getDAO('SubscriptionTypeDAO');

		$currentSubscriptionType = $subscriptionTypeDAO->newDataObject();
		$currentSubscriptionType->setJournalId($subscriptionTypeForm->journalId);
		$currentSubscriptionType->setCost($subscriptionTypeForm->getData('cost'));
		$currentSubscriptionType->setCurrencyCodeAlpha($subscriptionTypeForm->getData('currency'));
		$currentSubscriptionType->setDuration($subscriptionTypeForm->getData('duration'));
		$currentSubscriptionType->setFormat($subscriptionTypeForm->getData('format'));
		$currentSubscriptionType->setInstitutional($subscriptionTypeForm->getData('institutional'));
		$currentSubscriptionType->setMembership(is_null($membership) ? 0 : $membership);
		$currentSubscriptionType->setDisablePublicDisplay(is_null($disablePublicDisplay) ? 0 : $disablePublicDisplay);
		$currentSubscriptionType->setSequence(REALLY_BIG_NUMBER);

		$journals = Application::getContextDAO()->getNames();
		$locales = AppLocale::getSupportedLocales();

		foreach ($journals as $journalId => $journalName) {
			if ($journalId != $currentSubscriptionType->getJournalId()) {
				$subscriptionType = clone $currentSubscriptionType;
				$subscriptionType->setJournalId($journalId);

				foreach ($locales as $localeId => $localeValue) {
					$subscriptionType->setName($name[$locale], $localeId);
					$subscriptionType->setDescription($description[$locale], $localeId);
				}

				$journalSubscriptionTypes = $subscriptionTypeDAO->getByJournalId($journalId)->toArray();

				$existingTypeId = self::isSubscriptionTypePresent($subscriptionType, $journalSubscriptionTypes);

				if (is_null($existingTypeId)) {
					$subscriptionTypeDAO->insertObject($subscriptionType, false);
				}
				else {
					$subscriptionType->setId($existingTypeId);
					$subscriptionTypeDAO->updateObject($subscriptionType, false);
				}

				$subscriptionTypeDAO->resequenceSubscriptionTypes($journalId);
			}
		}

		return false;
	}

	// TODO: Create a UNIQUE index on the DB
	static function isSubscriptionTypeEqual(SubscriptionType $obj1, SubscriptionType $obj2): bool
	{
		return $obj1->getJournalId() == $obj2->getJournalId()
			&& $obj1->getCost() == $obj2->getCost()
			&& $obj1->getDuration() == $obj2->getDuration()
			&& $obj1->getInstitutional() == $obj2->getInstitutional()
			&& $obj1->getMembership() == $obj2->getMembership();
	}

	static function isSubscriptionTypePresent(SubscriptionType $target, array $array): ?int
	{
		foreach ($array as $element) {
			if (self::isSubscriptionTypeEqual($target, $element)) {
				return $element->getId();
			}
		}

		return null;
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
