<?php

namespace Restruct\PhoneNumberField\Compat;

/*
 * The parent class of PhoneNumberValidator, whatever the Silverstripe major calls it.
 *
 * WHY THIS FILE DECLARES THE SAME CLASS TWICE
 *
 * Silverstripe 6 moved the form Validator from SilverStripe\Forms\Validator to
 * SilverStripe\Forms\Validation\Validator, without leaving an alias behind. The API
 * PhoneNumberValidator uses is the same on both (php($data), validationError(), getResult()),
 * so only the name of the parent differs, and an `extends` cannot be conditional.
 *
 * This empty intermediate class is declared once per major instead. PHP allows a conditional
 * declaration and Composer's PSR-4 autoloader resolves it by file name; the class manifest does
 * not see it (its visitor does not descend into `if` blocks), which only means the manifest
 * does not list PhoneNumberValidator among the framework Validator's descendants. Nothing in
 * the form flow asks the manifest for those; runtime checks (instanceof, get_parent_class) see
 * the real chain.
 *
 * Deliberately not a class_alias() of the framework name: aliasing SilverStripe\Forms\Validator
 * on SS6 would make every other module's class_exists() version check report SS5.
 *
 * Fully qualified names rather than `use` imports, because the two branches name different
 * classes and only one of them exists in any given install.
 */
if (class_exists(\SilverStripe\Forms\Validation\Validator::class)) {
    /**
     * Silverstripe 6.
     */
    abstract class BaseValidator extends \SilverStripe\Forms\Validation\Validator
    {
    }
} else {
    /**
     * Silverstripe 4.13 and 5.
     */
    abstract class BaseValidator extends \SilverStripe\Forms\Validator
    {
    }
}
