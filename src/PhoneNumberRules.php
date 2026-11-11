<?php

namespace Restruct\PhoneNumberField;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

/**
 * The rules a phone number has to meet, and the check that applies them.
 *
 * The defaults live in config so a project sets them once in YAML:
 *
 *   Restruct\PhoneNumberField\PhoneNumberRules:
 *     default_region: 'NL'
 *     allowed_regions: ['NL', 'BE']
 *     allowed_types: ['MOBILE', 'FIXED_LINE_OR_MOBILE']
 *
 * PhoneNumberField and PhoneNumberValidator pass their own overrides per instance; anything
 * they leave null falls back to these. The check itself is a static so it can run anywhere,
 * for instance in a queued job right before an SMS goes out.
 *
 * Number types are handled in whatever form the installed libphonenumber uses: int constants
 * on 8.x, cases of the backed enum PhoneNumberType on 9.x. Both are written the same way
 * (PhoneNumberType::MOBILE), so code comparing a type with === works on either; what has to
 * change between them (deduplicating, int values from YAML, the name of a type) is done here.
 */
class PhoneNumberRules
{
    use Configurable;
    use Injectable;

    /**
     * Region used to parse a number typed without a country code ("0612345678" -> NL).
     * ISO 3166-1 alpha-2.
     *
     * @config
     */
    private static string $default_region = 'NL';

    /**
     * Regions a number may belong to, ISO 3166-1 alpha-2. Empty means any region. The region
     * is the one libphonenumber derives from the number itself, so a Jersey number (+44 1534)
     * is JE, not GB.
     *
     * @config
     */
    private static array $allowed_regions = [];

    /**
     * Number types that pass, as names of libphonenumber\PhoneNumberType constants. Empty
     * means any type. FIXED_LINE_OR_MOBILE is included by default because several countries
     * (the US among them) do not separate the two ranges, and a mobile-only rule would reject
     * every number there.
     *
     * @config
     */
    private static array $allowed_types = ['MOBILE', 'FIXED_LINE_OR_MOBILE'];

    /**
     * Check one raw input against the rules.
     *
     * @param mixed $raw What the user typed; anything non-string is treated as empty.
     * @param string|null $defaultRegion Overrides config default_region when given.
     * @param array|null $allowedRegions Overrides config allowed_regions when given (empty array = any).
     * @param array|null $allowedTypes Overrides config allowed_types when given (empty array = any);
     *        entries are PhoneNumberType names, their int values, or (libphonenumber 9.x) enum cases.
     */
    public static function check(
        $raw,
        ?string $defaultRegion = null,
        ?array $allowedRegions = null,
        ?array $allowedTypes = null
    ): PhoneNumberCheck {
        $input = is_string($raw) ? trim($raw) : '';
        if ($input === '') {
            return new PhoneNumberCheck(false, PhoneNumberCheck::REASON_EMPTY);
        }

        $defaultRegion = strtoupper($defaultRegion ?? static::config()->get('default_region') ?? 'NL');
        $allowedRegions = array_map('strtoupper', $allowedRegions ?? static::config()->get('allowed_regions') ?? []);
        $allowedTypes = static::normaliseTypes($allowedTypes ?? static::config()->get('allowed_types') ?? []);

        $util = PhoneNumberUtil::getInstance();
        try {
            $number = $util->parse($input, $defaultRegion);
        } catch (NumberParseException $e) {
            return new PhoneNumberCheck(false, PhoneNumberCheck::REASON_UNPARSEABLE);
        }

        if (!$util->isValidNumber($number)) {
            return new PhoneNumberCheck(false, PhoneNumberCheck::REASON_INVALID);
        }

        $e164 = $util->format($number, PhoneNumberFormat::E164);
        $region = $util->getRegionCodeForNumber($number);
        $type = $util->getNumberType($number);
        $typeName = static::typeName($type);

        if (count($allowedRegions) && !in_array($region, $allowedRegions, true)) {
            return new PhoneNumberCheck(false, PhoneNumberCheck::REASON_REGION, $e164, $region, $type, $typeName);
        }

        if (count($allowedTypes) && !in_array($type, $allowedTypes, true)) {
            return new PhoneNumberCheck(false, PhoneNumberCheck::REASON_TYPE, $e164, $region, $type, $typeName);
        }

        return new PhoneNumberCheck(true, null, $e164, $region, $type, $typeName);
    }

    /**
     * E.164 form of a number that passes the rules, or null when it does not. Convenience for
     * code that only wants the normalised value.
     */
    public static function normalise($raw, ?string $defaultRegion = null): ?string
    {
        $check = static::check($raw, $defaultRegion);

        return $check->valid ? $check->e164 : null;
    }

    /**
     * True when the given allowed types only admit mobile numbers, which decides whether the
     * user is told "enter a mobile number" or the generic "this type is not accepted".
     */
    public static function isMobileOnly(?array $allowedTypes = null): bool
    {
        $types = static::normaliseTypes($allowedTypes ?? static::config()->get('allowed_types') ?? []);
        if (!count($types)) {
            return false;
        }
        $mobileLike = [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE];

        # A loop with strict in_array() rather than array_diff(): array_diff() compares as
        # strings, and libphonenumber 9.x enum cases cannot be cast to string.
        // return !count(array_diff($types, $mobileLike));
        foreach ($types as $type) {
            if (!in_array($type, $mobileLike, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Accept constant names ('MOBILE') as well as their values (1) and return types in the form
     * the installed libphonenumber uses: int values on 8.x, PhoneNumberType enum cases on 9.x
     * (where an enum case is also accepted as input).
     * Unknown names are dropped rather than fatal, so a typo in YAML loosens the rule instead
     * of taking the form down; the test suite pins the valid names.
     */
    public static function normaliseTypes(array $types): array
    {
        $out = [];
        foreach ($types as $type) {
            if ($type instanceof \UnitEnum) {
                # libphonenumber 9.x: already a PhoneNumberType case.
                $out[] = $type;
                continue;
            }
            if (is_int($type)) {
                $type = static::typeFromValue($type);
                if ($type !== null) {
                    $out[] = $type;
                }
                continue;
            }
            # constant() returns the int on 8.x and the enum case on 9.x: enum cases are class
            # constants as far as defined() and constant() are concerned.
            $const = PhoneNumberType::class . '::' . strtoupper(trim((string) $type));
            if (defined($const)) {
                $out[] = constant($const);
            }
        }

        # Strict deduplication rather than array_unique(), which compares as strings and
        # cannot cast a libphonenumber 9.x enum case.
        // return array_values(array_unique($out));
        $unique = [];
        foreach ($out as $type) {
            if (!in_array($type, $unique, true)) {
                $unique[] = $type;
            }
        }

        return $unique;
    }

    /**
     * The installed libphonenumber's form of a type given as its int value: the int itself on
     * 8.x, the PhoneNumberType case on 9.x. Null for a value that is not a type (9.x only;
     * 8.x has no way to tell, and the int then simply never matches).
     *
     * @return int|PhoneNumberType|null
     */
    public static function typeFromValue(int $value)
    {
        if (enum_exists(PhoneNumberType::class)) {
            # Called by name: on 8.x PhoneNumberType is a plain class without tryFrom(), and this
            # line is never reached there.
            return call_user_func([PhoneNumberType::class, 'tryFrom'], $value);
        }

        return $value;
    }

    /**
     * Name of a PhoneNumberType constant for its value (or, on 9.x, its enum case), or null.
     *
     * @param int|PhoneNumberType|null $type
     */
    public static function typeName($type): ?string
    {
        if (is_int($type)) {
            $type = static::typeFromValue($type);
        }
        if ($type === null) {
            return null;
        }
        if ($type instanceof \UnitEnum) {
            # libphonenumber 9.x.
            return $type->name;
        }
        foreach ((new \ReflectionClass(PhoneNumberType::class))->getConstants() as $name => $value) {
            if ($value === $type) {
                return $name;
            }
        }

        return null;
    }
}
