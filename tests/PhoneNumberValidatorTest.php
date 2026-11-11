<?php

namespace Restruct\PhoneNumberField\Tests;

use Restruct\PhoneNumberField\PhoneNumberRules;
use Restruct\PhoneNumberField\Tests\Stub\FormCompat;
use Restruct\PhoneNumberField\PhoneNumberValidator;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
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
        $this->assertTrue(FormCompat::run($form)->isValid());
    }

    public function testFailureNamesTheFieldAndUsesTheSharedMessage(): void
    {
        $form = $this->makeForm(
            ['Mobile' => '0201234567', 'Phone' => '0612345678'],
            PhoneNumberValidator::create(['Mobile', 'Phone'])
        );
        $result = FormCompat::run($form);
        $this->assertFalse($result->isValid());
        $messages = $result->getMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('Mobile', $messages[0]['fieldName']);
        $this->assertSame('Please enter a mobile phone number', $messages[0]['message']);
    }

    public function testEmptyValuesPass(): void
    {
        $form = $this->makeForm(['Mobile' => ''], PhoneNumberValidator::create(['Mobile']));
        $this->assertTrue(FormCompat::run($form)->isValid());
    }

    public function testOptionsOverrideConfig(): void
    {
        $validator = PhoneNumberValidator::create(['Mobile'], [
            'allowed_regions' => ['BE'],
            'default_region' => 'BE',
            'allowed_types' => ['MOBILE'],
        ]);
        $this->assertFalse(FormCompat::run($this->makeForm(['Mobile' => '0612345678'], $validator))->isValid());
        $this->assertTrue(FormCompat::run($this->makeForm(['Mobile' => '0470 12 34 56'], $validator))->isValid());

        $validator->setAllowedRegions(['NL'])->setDefaultRegion('NL');
        $this->assertTrue(FormCompat::run($this->makeForm(['Mobile' => '0612345678'], $validator))->isValid());
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
        $composite = FormCompat::composite([
            FormCompat::required('Mobile'),
            PhoneNumberValidator::create(['Mobile']),
        ]);

        $result = FormCompat::run($this->makeForm(['Mobile' => ''], $composite));
        $this->assertFalse($result->isValid(), 'required catches empty');

        $result = FormCompat::run($this->makeForm(['Mobile' => '0201234567'], $composite));
        $this->assertFalse($result->isValid(), 'phone rule catches the landline');
        $this->assertSame('Please enter a mobile phone number', $result->getMessages()[0]['message']);

        $this->assertTrue(FormCompat::run($this->makeForm(['Mobile' => '0612345678'], $composite))->isValid());
    }

    public function testCanBeAddedToAnExistingCompositeValidator(): void
    {
        $form = $this->makeForm(['Mobile' => '0201234567'], FormCompat::composite([FormCompat::required('Mobile')]));
        $this->assertTrue(FormCompat::run($form)->isValid(), 'before: nothing checks the number');

        # setValidator() again so the form is handed to the added validator: CompositeValidator's
        # addValidator() does not pass the form on by itself (framework behaviour, SS4 and SS5).
        $form->setValidator($form->getValidator()->addValidator(PhoneNumberValidator::create(['Mobile'])));
        $this->assertFalse(FormCompat::run($form)->isValid(), 'after: the landline is refused');
    }

    /**
     * Canary for the framework behaviour the README works around: addValidator() on a
     * CompositeValidator that is already on a form does not hand the form to the added validator,
     * so validating without the extra setValidator() fails (SS4/SS5: getData() on null; SS6:
     * the typed $form property is read before initialisation). Measured on 4.13, 5.4 and 6.3.
     * When this goes red, the framework has fixed it and the README workaround can be relaxed.
     */
    public function testAddValidatorAloneDoesNotHandTheFormOn(): void
    {
        $form = $this->makeForm(['Mobile' => '0201234567'], FormCompat::composite([FormCompat::required('Mobile')]));
        $form->getValidator()->addValidator(PhoneNumberValidator::create(['Mobile']));

        $this->expectException(\Error::class);
        FormCompat::run($form);
    }
}
