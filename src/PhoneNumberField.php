<?php

namespace Restruct\PhoneNumberField;

use SilverStripe\Forms\TextField;

/**
 * A text field that only accepts a phone number meeting the PhoneNumberRules, and saves it in
 * E.164 form (+31612345678) whatever the user typed.
 *
 *   PhoneNumberField::create('Mobile', 'Mobile number')
 *       ->setAllowedRegions(['NL', 'BE'])   # optional, config is the default
 *       ->setAllowedTypes(['MOBILE']);      # optional, config is the default
 *
 * Empty input passes here: whether the field is required stays with RequiredFields, as for any
 * other field. The user keeps seeing what they typed; only the saved value is normalised.
 */
class PhoneNumberField extends TextField
{
    # null means "use PhoneNumberRules config"; see the setters.
    protected ?string $defaultRegion = null;
    protected ?array $allowedRegions = null;
    protected ?array $allowedTypes = null;

    # Save the E.164 form rather than the raw input. Off, the field validates only.
    protected bool $normalise = true;

    public function __construct($name, $title = null, $value = '', $maxLength = null, $form = null)
    {
        parent::__construct($name, $title, $value, $maxLength, $form);
        # type="tel" brings up the numeric keypad on phones and lets browsers autofill a number.
        $this->setAttribute('type', 'tel');
        $this->setAttribute('autocomplete', 'tel');
    }

    public function Type()
    {
        return 'text phonenumber';
    }

    /**
     * Region to assume for numbers typed without a country code. ISO 3166-1 alpha-2.
     */
    public function setDefaultRegion(?string $region): static
    {
        $this->defaultRegion = $region;

        return $this;
    }

    public function getDefaultRegion(): ?string
    {
        return $this->defaultRegion;
    }

    /**
     * Regions the number may belong to. Empty array = any region; null = config default.
     */
    public function setAllowedRegions(?array $regions): static
    {
        $this->allowedRegions = $regions;

        return $this;
    }

    public function getAllowedRegions(): ?array
    {
        return $this->allowedRegions;
    }

    /**
     * Number types that pass, as libphonenumber\PhoneNumberType constant names or values.
     * Empty array = any type; null = config default.
     */
    public function setAllowedTypes(?array $types): static
    {
        $this->allowedTypes = $types;

        return $this;
    }

    public function getAllowedTypes(): ?array
    {
        return $this->allowedTypes;
    }

    /**
     * Whether dataValue() returns the E.164 form (default) or the raw input.
     */
    public function setNormalise(bool $normalise): static
    {
        $this->normalise = $normalise;

        return $this;
    }

    public function getNormalise(): bool
    {
        return $this->normalise;
    }

    /**
     * Run the rules against the current value.
     */
    public function check(): PhoneNumberCheck
    {
        return PhoneNumberRules::check(
            $this->value,
            $this->defaultRegion,
            $this->allowedRegions,
            $this->allowedTypes
        );
    }

    /**
     * What saveInto() writes: the normalised number when it passes, otherwise the raw value
     * (which validation has already refused, so it is never written through a validated form).
     */
    public function dataValue()
    {
        if ($this->normalise) {
            $check = $this->check();
            if ($check->valid) {
                return $check->e164;
            }
        }

        return parent::dataValue();
    }

    /**
     * @param \SilverStripe\Forms\Validator $validator
     * @return bool
     */
    public function validate($validator)
    {
        # TextField's own check (maxLength) first; it reports its own error.
        $valid = parent::validate($validator);

        # Empty stays with RequiredFields, same as every other field type.
        if (is_string($this->value) && trim($this->value) === '' || $this->value === null) {
            return $valid;
        }

        $check = $this->check();
        if (!$check->valid) {
            $validator->validationError(
                $this->name,
                PhoneNumberMessages::forCheck($check, $this->allowedTypes),
                'validation'
            );

            return false;
        }

        return $valid;
    }
}
