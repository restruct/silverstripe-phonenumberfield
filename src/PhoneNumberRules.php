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
     *        entries are PhoneNumberType constant names or their int values.
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

        return !count(array_diff($types, $mobileLike));
    }

    /**
     * Accept constant names ('MOBILE') as well as their values (1) and return int values.
     * Unknown names are dropped rather than fatal, so a typo in YAML loosens the rule instead
     * of taking the form down; the test suite pins the valid names.
     */
    public static function normaliseTypes(array $types): array
    {
        $out = [];
        foreach ($types as $type) {
            if (is_int($type)) {
                $out[] = $type;
                continue;
            }
            $const = PhoneNumberType::class . '::' . strtoupper(trim((string) $type));
            if (defined($const)) {
                $out[] = constant($const);
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Name of a PhoneNumberType constant for its value, or null.
     */
    public static function typeName(?int $type): ?string
    {
        if ($type === null) {
            return null;
        }
        foreach ((new \ReflectionClass(PhoneNumberType::class))->getConstants() as $name => $value) {
            if ($value === $type) {
                return $name;
            }
        }

        return null;
    }
}
