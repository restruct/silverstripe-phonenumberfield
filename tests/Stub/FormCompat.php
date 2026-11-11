<?php

namespace Restruct\PhoneNumberField\Tests\Stub;

use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormField;

/**
 * The framework API the tests use, under whichever name the installed Silverstripe major gives
 * it, so one suite runs unchanged on SS4.13, SS5 and SS6. SS6 moved the validators to
 * SilverStripe\Forms\Validation and renamed RequiredFields to RequiredFieldsValidator, and
 * renamed Form::validationResult() and FormField::Value(); the behaviour the tests rely on is
 * the same.
 */
class FormCompat
{
    /**
     * RequiredFields (SS4/SS5) or RequiredFieldsValidator (SS6) for the given field names.
     */
    public static function required(string ...$fieldNames)
    {
        $class = class_exists('SilverStripe\\Forms\\Validation\\RequiredFieldsValidator')
            ? 'SilverStripe\\Forms\\Validation\\RequiredFieldsValidator'
            : 'SilverStripe\\Forms\\RequiredFields';

        return $class::create(...$fieldNames);
    }

    /**
     * CompositeValidator, from SilverStripe\Forms (SS4/SS5) or SilverStripe\Forms\Validation (SS6).
     */
    public static function composite(array $validators)
    {
        $class = class_exists('SilverStripe\\Forms\\Validation\\CompositeValidator')
            ? 'SilverStripe\\Forms\\Validation\\CompositeValidator'
            : 'SilverStripe\\Forms\\CompositeValidator';

        return $class::create($validators);
    }

    /**
     * Run the form's validation and return the ValidationResult: Form::validate() on SS5.4 and
     * SS6, Form::validationResult() on SS4 (where validate() does not exist; SS5.4 deprecated
     * validationResult() and SS6 removed it).
     */
    public static function run(Form $form)
    {
        return method_exists($form, 'validate') ? $form->validate() : $form->validationResult();
    }

    /**
     * The field's value as the user sees it: FormField::getValue() on SS6, FormField::Value() on
     * SS4/SS5 (where getValue() does not exist; SS5.4 deprecated Value() and SS6 removed it).
     */
    public static function value(FormField $field)
    {
        return method_exists($field, 'getValue') ? $field->getValue() : $field->Value();
    }
}
