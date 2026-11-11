<?php

namespace Restruct\PhoneNumberField;

/**
 * One place for the user-facing error text, shared by the field and the validator so both
 * say the same thing for the same failure.
 */
class PhoneNumberMessages
{
    /**
     * @param PhoneNumberCheck $check A failed check.
     * @param array|null $allowedTypes The types in force, to pick the mobile-specific wording.
     */
    public static function forCheck(PhoneNumberCheck $check, ?array $allowedTypes = null): string
    {
        switch ($check->reason) {
            case PhoneNumberCheck::REASON_REGION:
                return _t(
                    PhoneNumberField::class . '.REGION_NOT_ALLOWED',
                    'Phone numbers from this country are not accepted'
                );
            case PhoneNumberCheck::REASON_TYPE:
                if (PhoneNumberRules::isMobileOnly($allowedTypes)) {
                    return _t(
                        PhoneNumberField::class . '.MOBILE_REQUIRED',
                        'Please enter a mobile phone number'
                    );
                }
                return _t(
                    PhoneNumberField::class . '.TYPE_NOT_ALLOWED',
                    'This type of phone number is not accepted'
                );
            case PhoneNumberCheck::REASON_EMPTY:
                # Required-ness is RequiredFields' job; an empty value only gets here when a
                # caller insists on checking it anyway.
            case PhoneNumberCheck::REASON_UNPARSEABLE:
            case PhoneNumberCheck::REASON_INVALID:
            default:
                return _t(
                    PhoneNumberField::class . '.INVALID',
                    'Please enter a valid phone number'
                );
        }
    }
}
