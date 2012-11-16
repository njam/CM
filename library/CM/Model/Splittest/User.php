<?php
class CM_Model_Splittest_User extends CM_Model_Splittest {

	const TYPE = 27;

	/**
	 * @param CM_Model_User $user
	 * @param string        $variationName
	 * @param string|null   $forceVariationName
	 * @return bool
	 */
	public function isVariationFixture(CM_Model_User $user, $variationName, $forceVariationName = null) {
		return $this->_isVariationFixture($user->getId(), $variationName, $forceVariationName);
	}

	/**
	 * @param CM_Model_User $user
	 * @param string|null   $forceVariationName
	 * @return bool
	 */
	public function getVariationFixture(CM_Model_User $user, $forceVariationName = null) {
		return $this->_getVariationFixture($user->getId(), $forceVariationName);
	}

	/**
	 * @param CM_Model_User $user
	 */
	public function setConversion($user) {
		$this->_setConversion($user->getId());
	}

}