<?php

class CM_FormField_Email extends CM_FormField_Text {

    public function validate(CM_Frontend_Environment $environment, $userInput, CM_Response_Abstract $response) {
        $userInput = parent::validate($environment, $userInput, $response);

        if (false === filter_var($userInput, FILTER_VALIDATE_EMAIL)) {
            throw new CM_Exception_FormFieldValidation('Invalid email address');
        }

        return $userInput;
    }
}
