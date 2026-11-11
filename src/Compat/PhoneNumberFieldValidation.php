<?php

namespace Restruct\PhoneNumberField\Compat;

use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\Validation\Validator;

/*
 * Version-specific validate() for PhoneNumberField.
 *
 * WHY THIS FILE DECLARES THE SAME TRAIT TWICE
 *
 * FormField::validate() changed shape between Silverstripe 5 and 6 in a way one method cannot
 * satisfy:
 *  - SS4/SS5: `validate($validator)`, reports errors on the Validator it is handed and returns
 *    a bool.
 *  - SS6: `validate(): ValidationResult`, takes no argument and returns its own result
 *    (SilverStripe\Core\Validation\ValidationResult, a class that does not exist on SS5).
 * A child that declares either signature is a fatal "must be compatible" error on the other major.
 *
 * So PhoneNumberField pulls validate() from this trait, and the trait is declared once per major.
 * PHP allows a conditional declaration; the class manifest never sees it (its visitor does not
 * descend into `if` blocks), and does not need to: Composer's PSR-4 autoloader resolves it by file
 * name. The check itself is the same on every major and lives in
 * PhoneNumberField::phoneNumberError(); only the way an error is reported differs here.
 *
 * SilverStripe\Forms\Validation\Validator exists only on Silverstripe 6 (SS5 has it as
 * SilverStripe\Forms\Validator), so its presence is the version switch. The `use` imports above
 * are inert on SS4/SS5: an import never autoloads.
 */
# One trait per major, declared twice on purpose (see above): PSR-1's one-per-file rule
# cannot tell a conditional declaration from a second one.
# phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
if (class_exists(Validator::class)) {
    /**
     * Silverstripe 6: the field returns its own ValidationResult.
     */
    trait PhoneNumberFieldValidation
    {
        public function validate(): ValidationResult
        {
            # TextField's own checks (maxLength, via its field validators) first; they add their
            # own messages to the result.
            $result = parent::validate();

            $message = $this->phoneNumberError();
            if ($message !== null) {
                $result->addFieldError($this->getName(), $message, 'validation');
            }

            return $result;
        }
    }
} else {
    /**
     * Silverstripe 4.13 and 5: the field reports to the form's Validator and returns a bool.
     */
    trait PhoneNumberFieldValidation
    {
        /**
         * @param \SilverStripe\Forms\Validator $validator
         * @return bool
         */
        public function validate($validator)
        {
            # TextField's own check (maxLength) first; it reports its own error.
            $valid = parent::validate($validator);

            $message = $this->phoneNumberError();
            if ($message !== null) {
                $validator->validationError($this->name, $message, 'validation');

                return false;
            }

            return $valid;
        }
    }
}
