<?php

namespace Restruct\PhoneNumberField\Compat;

/*
 * The parent class of PhoneNumberValidator, whatever the Silverstripe major calls it.
 *
 * WHY THIS FILE IS CONDITIONAL
 *
 * Silverstripe 6 moved the form Validator from SilverStripe\Forms\Validator to
 * SilverStripe\Forms\Validation\Validator, without leaving an alias behind. The API
 * PhoneNumberValidator uses is the same on both (php($data), validationError(), getResult()),
 * so only the name of the parent differs, and an `extends` cannot be conditional.
 *
 * So PhoneNumberValidator extends this name, and this file makes it mean the right parent:
 *  - SS6: an empty intermediate class extending SilverStripe\Forms\Validation\Validator.
 *  - SS4.13/SS5: a class_alias() of SilverStripe\Forms\Validator itself.
 * PHP allows a conditional declaration and Composer's PSR-4 autoloader resolves this file by
 * name; the class manifest does not see BaseValidator (its visitor does not descend into `if`
 * blocks), which only means the manifest does not list PhoneNumberValidator among the framework
 * Validator's descendants. Nothing in the form flow asks the manifest for those; runtime checks
 * (instanceof, get_parent_class) see the real chain.
 *
 * Why a declaration for SS6 and an alias for SS4/SS5, rather than two declarations: PHPStan
 * reads a conditionally declared class from its first declaration only, and a second one
 * extending the SS5 name is a non-ignorable "extends unknown class" error on an SS6 host. A
 * class_alias() is invisible to it, so analysis on SS6 sees exactly the SS6 chain. The SS5 name
 * is a string for the same reason: a `::class` constant of a class that is absent on SS6 is
 * reported as not found.
 *
 * Deliberately not an alias of the framework's own old name: aliasing SilverStripe\Forms\Validator
 * on SS6 would make every other module's class_exists() version check report SS5. This alias
 * lives in this module's namespace only.
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
    # Silverstripe 4.13 and 5.
    class_alias('SilverStripe\\Forms\\Validator', BaseValidator::class);
}
