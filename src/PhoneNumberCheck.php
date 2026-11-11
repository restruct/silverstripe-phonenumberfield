<?php

namespace Restruct\PhoneNumberField;

/**
 * The outcome of one phone number check: whether the number passed, why it failed, and the
 * normalised form plus what libphonenumber found out about it.
 *
 * Plain value object so it can be used without a form: a job that sends SMS can call
 * PhoneNumberRules::check() right before sending and read these fields.
 */
class PhoneNumberCheck
{
    # Reasons, in the order the checks run. The first failing check wins.
    public const REASON_EMPTY = 'empty';
    public const REASON_UNPARSEABLE = 'unparseable'; # libphonenumber could not even parse it
    public const REASON_INVALID = 'invalid';         # parsed, but not a valid number anywhere
    public const REASON_REGION = 'region';           # valid, but its country is not on the allow-list
    public const REASON_TYPE = 'type';               # valid, but not one of the allowed number types

    public function __construct(
        public readonly bool $valid,
        public readonly ?string $reason = null,
        # E.164 form (+31612345678), set whenever the number parsed to a valid number, even when
        # region or type then failed, so callers can still log or display it.
        public readonly ?string $e164 = null,
        # ISO 3166-1 alpha-2 region libphonenumber attributes the number to (NL, BE, GG ...).
        public readonly ?string $region = null,
        # libphonenumber\PhoneNumberType constant value.
        public readonly ?int $type = null,
        # The constant's name (MOBILE, FIXED_LINE ...), handy in messages and logs.
        public readonly ?string $typeName = null,
    ) {
    }

    public function failed(string $reason): bool
    {
        return !$this->valid && $this->reason === $reason;
    }
}
