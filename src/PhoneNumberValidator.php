<?php

namespace Restruct\PhoneNumberField;

use SilverStripe\Forms\Validator;

/**
 * Applies the PhoneNumberRules to named fields of ANY form, without replacing the fields.
 * For an existing form built elsewhere (a module's form, userforms, a CMS edit form) where
 * swapping the field class is not an option:
 *
 *   $form->setValidator(CompositeValidator::create([
 *       RequiredFields::create('Mobile'),
 *       PhoneNumberValidator::create(['Mobile']),
 *   ]));
 *
 * or on a form that already has a CompositeValidator (setValidator() again, because
 * CompositeValidator::addValidator() does not hand the form to the added validator):
 *
 *   $form->setValidator($form->getValidator()->addValidator(PhoneNumberValidator::create(['Mobile'])));
 *
 * Empty values pass (required-ness is RequiredFields' job). This validator does not rewrite
 * the submitted value: normalisation to E.164 needs PhoneNumberField, or a call to
 * PhoneNumberRules::normalise() where the data is saved.
 */
class PhoneNumberValidator extends Validator
{
    /** @var string[] */
    protected array $fieldNames = [];

    protected ?string $defaultRegion = null;
    protected ?array $allowedRegions = null;
    protected ?array $allowedTypes = null;

    /**
     * @param string[] $fieldNames Names of the fields to check.
     * @param array $options Any of default_region, allowed_regions, allowed_types; a key left
     *        out falls back to PhoneNumberRules config.
     */
    public function __construct(array $fieldNames = [], array $options = [])
    {
        parent::__construct();
        $this->fieldNames = array_values($fieldNames);
        $this->defaultRegion = $options['default_region'] ?? null;
        $this->allowedRegions = $options['allowed_regions'] ?? null;
        $this->allowedTypes = $options['allowed_types'] ?? null;
    }

    public function addField(string $fieldName): static
    {
        if (!in_array($fieldName, $this->fieldNames, true)) {
            $this->fieldNames[] = $fieldName;
        }

        return $this;
    }

    public function removeField(string $fieldName): static
    {
        $this->fieldNames = array_values(array_diff($this->fieldNames, [$fieldName]));

        return $this;
    }

    public function getFieldNames(): array
    {
        return $this->fieldNames;
    }

    public function setDefaultRegion(?string $region): static
    {
        $this->defaultRegion = $region;

        return $this;
    }

    public function setAllowedRegions(?array $regions): static
    {
        $this->allowedRegions = $regions;

        return $this;
    }

    public function setAllowedTypes(?array $types): static
    {
        $this->allowedTypes = $types;

        return $this;
    }

    /**
     * @param array $data Submitted form data.
     * @return bool
     */
    public function php($data)
    {
        $valid = true;
        foreach ($this->fieldNames as $fieldName) {
            $value = $data[$fieldName] ?? null;
            # Empty stays with RequiredFields.
            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }
            $check = PhoneNumberRules::check(
                $value,
                $this->defaultRegion,
                $this->allowedRegions,
                $this->allowedTypes
            );
            if (!$check->valid) {
                $this->validationError(
                    $fieldName,
                    PhoneNumberMessages::forCheck($check, $this->allowedTypes),
                    'validation'
                );
                $valid = false;
            }
        }

        return $valid;
    }
}
