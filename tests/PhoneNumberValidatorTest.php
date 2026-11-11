<?php

namespace Restruct\PhoneNumberField\Tests;

use Restruct\PhoneNumberField\PhoneNumberRules;
use Restruct\PhoneNumberField\PhoneNumberValidator;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\CompositeValidator;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextField;

/**
 * The validator on plain TextFields: the "existing form, cannot swap the field class" case.
 */
class PhoneNumberValidatorTest extends SapphireTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::modify()->set(PhoneNumberRules::class, 'default_region', 'NL');
        Config::modify()->set(PhoneNumberRules::class, 'allowed_regions', []);
        Config::modify()->set(PhoneNumberRules::class, 'allowed_types', ['MOBILE', 'FIXED_LINE_OR_MOBILE']);
    }

    protected function makeForm(array $values, $validator): Form
    {
        $fields = FieldList::create();
        foreach ($values as $name => $value) {
            $fields->push(TextField::create($name)->setValue($value));
        }
        $form = new Form(null, 'TestForm', $fields, FieldList::create(FormAction::create('submit', 'Submit')), $validator);
        $form->disableSecurityToken();

        return $form;
    }

    public function testOnlyNamedFieldsAreChecked(): void
    {
        $form = $this->makeForm(
            ['Mobile' => '0612345678', 'Other' => 'not a number'],
            PhoneNumberValidator::create(['Mobile'])
        );
        $this->assertTrue($form->validationResult()->isValid());
    }

    public function testFailureNamesTheFieldAndUsesTheSharedMessage(): void
    {
        $form = $this->makeForm(
            ['Mobile' => '0201234567', 'Phone' => '0612345678'],
            PhoneNumberValidator::create(['Mobile', 'Phone'])
        );
        $result = $form->validationResult();
        $this->assertFalse($result->isValid());
        $messages = $result->getMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('Mobile', $messages[0]['fieldName']);
        $this->assertSame('Please enter a mobile phone number', $messages[0]['message']);
    }

    public function testEmptyValuesPass(): void
    {
        $form = $this->makeForm(['Mobile' => ''], PhoneNumberValidator::create(['Mobile']));
        $this->assertTrue($form->validationResult()->isValid());
    }

    public function testOptionsOverrideConfig(): void
    {
        $validator = PhoneNumberValidator::create(['Mobile'], [
            'allowed_regions' => ['BE'],
            'default_region' => 'BE',
            'allowed_types' => ['MOBILE'],
        ]);
        $this->assertFalse($this->makeForm(['Mobile' => '0612345678'], $validator)->validationResult()->isValid());
        $this->assertTrue($this->makeForm(['Mobile' => '0470 12 34 56'], $validator)->validationResult()->isValid());

        $validator->setAllowedRegions(['NL'])->setDefaultRegion('NL');
        $this->assertTrue($this->makeForm(['Mobile' => '0612345678'], $validator)->validationResult()->isValid());
    }

    public function testFieldsCanBeAddedAndRemoved(): void
    {
        $validator = PhoneNumberValidator::create();
        $this->assertSame([], $validator->getFieldNames());
        $validator->addField('Mobile')->addField('Mobile')->addField('Phone');
        $this->assertSame(['Mobile', 'Phone'], $validator->getFieldNames());
        $validator->removeField('Mobile');
        $this->assertSame(['Phone'], $validator->getFieldNames());
    }

    public function testCombinesWithRequiredFieldsInACompositeValidator(): void
    {
        $composite = CompositeValidator::create([
            RequiredFields::create('Mobile'),
            PhoneNumberValidator::create(['Mobile']),
        ]);

        $result = $this->makeForm(['Mobile' => ''], $composite)->validationResult();
        $this->assertFalse($result->isValid(), 'required catches empty');

        $result = $this->makeForm(['Mobile' => '0201234567'], $composite)->validationResult();
        $this->assertFalse($result->isValid(), 'phone rule catches the landline');
        $this->assertSame('Please enter a mobile phone number', $result->getMessages()[0]['message']);

        $this->assertTrue($this->makeForm(['Mobile' => '0612345678'], $composite)->validationResult()->isValid());
    }

    public function testCanBeAddedToAnExistingCompositeValidator(): void
    {
        $form = $this->makeForm(['Mobile' => '0201234567'], CompositeValidator::create([RequiredFields::create('Mobile')]));
        $this->assertTrue($form->validationResult()->isValid(), 'before: nothing checks the number');

        # setValidator() again so the form is handed to the added validator: CompositeValidator's
        # addValidator() does not pass the form on by itself (framework behaviour, SS4 and SS5).
        $form->setValidator($form->getValidator()->addValidator(PhoneNumberValidator::create(['Mobile'])));
        $this->assertFalse($form->validationResult()->isValid(), 'after: the landline is refused');
    }
}
