<?php

namespace Restruct\PhoneNumberField\Tests;

use Restruct\PhoneNumberField\PhoneNumberField;
use Restruct\PhoneNumberField\PhoneNumberRules;
use Restruct\PhoneNumberField\Tests\Stub\FieldSink;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\RequiredFields;

/**
 * The field inside a real Form: validation outcome, error message, and what saveInto() gets.
 */
class PhoneNumberFieldTest extends SapphireTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::modify()->set(PhoneNumberRules::class, 'default_region', 'NL');
        Config::modify()->set(PhoneNumberRules::class, 'allowed_regions', []);
        Config::modify()->set(PhoneNumberRules::class, 'allowed_types', ['MOBILE', 'FIXED_LINE_OR_MOBILE']);
    }

    protected function makeForm(PhoneNumberField $field, ?RequiredFields $required = null): Form
    {
        $form = new Form(
            null,
            'TestForm',
            FieldList::create($field),
            FieldList::create(FormAction::create('submit', 'Submit')),
            $required ?: RequiredFields::create()
        );
        $form->disableSecurityToken();

        return $form;
    }

    /**
     * Run the form's validator the way Form::validationResult() does and return the result.
     */
    protected function validate(Form $form)
    {
        return $form->validationResult();
    }

    public function testRendersAsTelInput(): void
    {
        $field = PhoneNumberField::create('Mobile', 'Mobile');
        $this->assertSame('tel', $field->getAttribute('type'));
        $this->assertStringContainsString('type="tel"', (string) $field->Field());
        $this->assertStringContainsString('phonenumber', $field->Type());
    }

    public function testValidMobilePassesAndDataValueIsE164WhileValueStaysAsTyped(): void
    {
        $field = PhoneNumberField::create('Mobile', 'Mobile')->setValue('06-12345678');
        $result = $this->validate($this->makeForm($field));

        $this->assertTrue($result->isValid());
        $this->assertSame('06-12345678', $field->Value(), 'the user keeps seeing what they typed');
        $this->assertSame('+31612345678', $field->dataValue(), 'the saved value is normalised');
    }

    public function testNormaliseCanBeSwitchedOff(): void
    {
        $field = PhoneNumberField::create('Mobile', 'Mobile')->setValue('06-12345678')->setNormalise(false);
        $this->assertSame('06-12345678', $field->dataValue());
    }

    public function testLandlineFailsWithTheMobileMessage(): void
    {
        $field = PhoneNumberField::create('Mobile', 'Mobile')->setValue('020 123 4567');
        $result = $this->validate($this->makeForm($field));

        $this->assertFalse($result->isValid());
        $messages = $result->getMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('Mobile', $messages[0]['fieldName']);
        $this->assertSame('Please enter a mobile phone number', $messages[0]['message']);
        # An invalid value is not normalised into something that looks accepted.
        $this->assertSame('020 123 4567', $field->dataValue());
    }

    public function testGarbageFailsWithTheGenericMessage(): void
    {
        $field = PhoneNumberField::create('Mobile', 'Mobile')->setValue('not a number');
        $result = $this->validate($this->makeForm($field));

        $this->assertFalse($result->isValid());
        $this->assertSame('Please enter a valid phone number', $result->getMessages()[0]['message']);
    }

    public function testRegionFailsWithTheCountryMessage(): void
    {
        $field = PhoneNumberField::create('Mobile', 'Mobile')
            ->setValue('+32 470 12 34 56')
            ->setAllowedRegions(['NL']);
        $result = $this->validate($this->makeForm($field));

        $this->assertFalse($result->isValid());
        $this->assertSame('Phone numbers from this country are not accepted', $result->getMessages()[0]['message']);
    }

    public function testGenericTypeMessageWhenTheRuleIsNotMobileOnly(): void
    {
        $field = PhoneNumberField::create('Phone', 'Phone')
            ->setValue('0900 123 4567')
            ->setAllowedTypes(['MOBILE', 'FIXED_LINE']);
        $result = $this->validate($this->makeForm($field));

        $this->assertFalse($result->isValid());
        $this->assertSame('This type of phone number is not accepted', $result->getMessages()[0]['message']);
    }

    public function testEmptyPassesUnlessRequired(): void
    {
        $field = PhoneNumberField::create('Mobile', 'Mobile')->setValue('');
        $this->assertTrue($this->validate($this->makeForm($field))->isValid());

        $field = PhoneNumberField::create('Mobile', 'Mobile')->setValue('');
        $result = $this->validate($this->makeForm($field, RequiredFields::create('Mobile')));
        $this->assertFalse($result->isValid(), 'required-ness stays with RequiredFields');
        $this->assertSame('Mobile', $result->getMessages()[0]['fieldName']);
    }

    public function testInstanceSettersOverrideConfig(): void
    {
        Config::modify()->set(PhoneNumberRules::class, 'allowed_regions', ['BE']);
        $field = PhoneNumberField::create('Mobile', 'Mobile')->setValue('0612345678');
        $this->assertFalse($this->validate($this->makeForm($field))->isValid(), 'config says BE only');

        $field->setAllowedRegions([]);
        $this->assertTrue($this->validate($this->makeForm($field))->isValid(), 'instance override: any region');

        $field = PhoneNumberField::create('Mobile', 'Mobile')->setValue('0470 12 34 56')->setDefaultRegion('BE');
        $this->assertTrue($this->validate($this->makeForm($field))->isValid());
        $this->assertSame('+32470123456', $field->dataValue());
    }

    public function testMaxLengthStillApplies(): void
    {
        $field = PhoneNumberField::create('Mobile', 'Mobile', '+31612345678', 5);
        $result = $this->validate($this->makeForm($field));
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('must not exceed', $result->getMessages()[0]['message']);
    }

    public function testSaveIntoWritesTheNormalisedValue(): void
    {
        $field = PhoneNumberField::create('Mobile', 'Mobile')->setValue('06 12 34 56 78');
        $this->makeForm($field);
        # FormField::saveInto() is what Form::saveInto() calls per field; it writes dataValue().
        $sink = new FieldSink();
        $field->saveInto($sink);
        $this->assertSame('+31612345678', $sink->Mobile);
    }
}
