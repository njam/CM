<?php

class CM_FormField_Birthdate extends CM_FormField_Date {

    /** @var integer */
    protected $_minAge;

    /** @var integer */
    protected $_maxAge;

    public function initialize() {
        $this->_minAge = $this->_params->getInt('minAge');
        $this->_maxAge = $this->_params->getInt('maxAge');

        $this->_params->set('yearFirst', date('Y') - $this->_minAge);
        $this->_params->set('yearLast', date('Y') - $this->_maxAge);
        parent::initialize();
    }

    public function validate(CM_Frontend_Environment $environment, $userInput, CM_Response_Abstract $response) {
        $userInput = parent::validate($environment, $userInput, $response);
        $age = $userInput->diff(new DateTime())->y;
        if ($age < $this->_minAge || $age > $this->_maxAge) {
            throw new CM_Exception_FormFieldValidation('Invalid birthdate');
        }
        return $userInput;
    }
}
