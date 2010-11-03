<?php
/**
 * Octal
 */
class Horde_Form_Type_Octal extends Horde_Form_Type {

    function isValid($var, $vars, $value, &$message)
    {
        if ($var->required && empty($value) && ((string)(int)$value !== $value)) {
            $message = Horde_Model_Translation::t("This field is required.");
            return false;
        }

        if (empty($value) || preg_match('/^[0-7]+$/', $value)) {
            return true;
        }

        $message = Horde_Model_Translation::t("This field may only contain octal values.");
        return false;
    }

}
