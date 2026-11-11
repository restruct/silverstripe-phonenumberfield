<?php

namespace Restruct\PhoneNumberField\Tests;

use libphonenumber\PhoneNumberType;
use Restruct\PhoneNumberField\PhoneNumberCheck;
use Restruct\PhoneNumberField\PhoneNumberRules;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

/**
 * The rules themselves, without a form. Numbers are libphonenumber example-style numbers;
 * none of them is a real subscriber.
 */
class PhoneNumberRulesTest extends SapphireTest
{
    protected function setUp(): void
    {
        parent::setUp();
        # Pin the config so a project YAML in the host cannot change the outcome.
        Config::modify()->set(PhoneNumberRules::class, 'default_region', 'NL');
        Config::modify()->set(PhoneNumberRules::class, 'allowed_regions', []);
        Config::modify()->set(PhoneNumberRules::class, 'allowed_types', ['MOBILE', 'FIXED_LINE_OR_MOBILE']);
    }

    public function testDutchMobileInNationalFormatPassesAndNormalises(): void
    {
        $check = PhoneNumberRules::check('06 1234 5678');
        $this->assertTrue($check->valid);
        $this->assertNull($check->reason);
        $this->assertSame('+31612345678', $check->e164);
        $this->assertSame('NL', $check->region);
        $this->assertSame(PhoneNumberType::MOBILE, $check->type);
        $this->assertSame('MOBILE', $check->typeName);
    }

    public function testInternationalFormatsAllNormaliseToOneValue(): void
    {
        foreach (['+31612345678', '0031612345678', '+31 (0)6 12345678', '0612345678'] as $input) {
            $this->assertSame('+31612345678', PhoneNumberRules::check($input)->e164, "input: $input");
        }
    }

    public function testEmptyAndNonStringAreEmpty(): void
    {
        foreach (['', '   ', null, 12, []] as $input) {
            $check = PhoneNumberRules::check($input);
            $this->assertTrue($check->failed(PhoneNumberCheck::REASON_EMPTY));
            $this->assertNull($check->e164);
        }
    }

    public function testGarbageIsUnparseableAndTooShortIsInvalid(): void
    {
        $this->assertTrue(PhoneNumberRules::check('call me')->failed(PhoneNumberCheck::REASON_UNPARSEABLE));
        $this->assertTrue(PhoneNumberRules::check('0612')->failed(PhoneNumberCheck::REASON_INVALID));
    }

    public function testLandlineFailsOnTypeByDefaultButPassesWhenAnyTypeAllowed(): void
    {
        $landline = PhoneNumberRules::check('0201234567');
        $this->assertTrue($landline->failed(PhoneNumberCheck::REASON_TYPE));
        $this->assertSame('FIXED_LINE', $landline->typeName);
        # The normalised number is still reported on a type failure, for logging.
        $this->assertSame('+31201234567', $landline->e164);

        $this->assertTrue(PhoneNumberRules::check('0201234567', null, null, [])->valid);
    }

    public function testPremiumRateIsRefusedByType(): void
    {
        $premium = PhoneNumberRules::check('0900 123 4567');
        $this->assertTrue($premium->failed(PhoneNumberCheck::REASON_TYPE));
        $this->assertSame('PREMIUM_RATE', $premium->typeName);
    }

    public function testRegionAllowListUsesTheNumbersOwnRegionNotTheCountryCode(): void
    {
        # +44 1534 is Jersey (JE), which shares the UK's +44 country code.
        $jersey = PhoneNumberRules::check('+44 1534 123456', null, ['GB'], []);
        $this->assertTrue($jersey->failed(PhoneNumberCheck::REASON_REGION));
        $this->assertSame('JE', $jersey->region);

        $this->assertTrue(PhoneNumberRules::check('+44 1534 123456', null, ['GB', 'JE'], [])->valid);
        # Lower-case region codes are accepted.
        $this->assertTrue(PhoneNumberRules::check('+32 470 12 34 56', null, ['nl', 'be'])->valid);
    }

    public function testRegionCheckRunsBeforeTypeCheck(): void
    {
        # A Belgian landline with NL-only regions: region is the reason, not type.
        $check = PhoneNumberRules::check('+32 2 552 23 11', null, ['NL']);
        $this->assertTrue($check->failed(PhoneNumberCheck::REASON_REGION));
    }

    public function testDefaultRegionDecidesHowANationalNumberIsRead(): void
    {
        # 0470 12 34 56 is a Belgian mobile when read as BE, and nothing valid when read as NL.
        $this->assertTrue(PhoneNumberRules::check('0470 12 34 56', 'BE')->valid);
        $this->assertSame('+32470123456', PhoneNumberRules::check('0470 12 34 56', 'BE')->e164);
        $this->assertFalse(PhoneNumberRules::check('0470 12 34 56', 'NL')->valid);
    }

    public function testConfigChangesTheOutcome(): void
    {
        Config::modify()->set(PhoneNumberRules::class, 'allowed_regions', ['BE']);
        $this->assertTrue(PhoneNumberRules::check('0612345678')->failed(PhoneNumberCheck::REASON_REGION));

        Config::modify()->set(PhoneNumberRules::class, 'allowed_regions', []);
        Config::modify()->set(PhoneNumberRules::class, 'allowed_types', ['FIXED_LINE']);
        $this->assertTrue(PhoneNumberRules::check('0612345678')->failed(PhoneNumberCheck::REASON_TYPE));
        $this->assertTrue(PhoneNumberRules::check('0201234567')->valid);

        Config::modify()->set(PhoneNumberRules::class, 'default_region', 'BE');
        $this->assertSame('+32470123456', PhoneNumberRules::check('0470 12 34 56', null, null, [])->e164);
    }

    public function testFixedLineOrMobileRegionsPassTheMobileRule(): void
    {
        # US numbers are FIXED_LINE_OR_MOBILE; the default rule must not reject a whole country.
        $us = PhoneNumberRules::check('+1 212 555 1234');
        $this->assertTrue($us->valid, 'US number should pass the default mobile-ish rule');
        $this->assertSame('FIXED_LINE_OR_MOBILE', $us->typeName);
    }

    public function testNormaliseReturnsE164OrNull(): void
    {
        $this->assertSame('+31612345678', PhoneNumberRules::normalise('06-12345678'));
        $this->assertNull(PhoneNumberRules::normalise('0201234567'), 'landline fails the default type rule');
        $this->assertNull(PhoneNumberRules::normalise(''));
    }

    public function testTypeNamesAndValuesAreBothAcceptedAndUnknownNamesAreDropped(): void
    {
        $this->assertSame(
            [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE],
            PhoneNumberRules::normaliseTypes(['mobile', 'FIXED_LINE', 'NO_SUCH_TYPE', PhoneNumberType::MOBILE])
        );
        $this->assertSame([], PhoneNumberRules::normaliseTypes([]));
    }

    public function testIsMobileOnly(): void
    {
        $this->assertTrue(PhoneNumberRules::isMobileOnly(['MOBILE']));
        $this->assertTrue(PhoneNumberRules::isMobileOnly(['MOBILE', 'FIXED_LINE_OR_MOBILE']));
        $this->assertFalse(PhoneNumberRules::isMobileOnly(['MOBILE', 'FIXED_LINE']));
        $this->assertFalse(PhoneNumberRules::isMobileOnly([]), 'any type allowed is not mobile-only');
        # null = config, which setUp pinned to the mobile pair.
        $this->assertTrue(PhoneNumberRules::isMobileOnly(null));
    }

    public function testTypeNameLookup(): void
    {
        $this->assertSame('PREMIUM_RATE', PhoneNumberRules::typeName(PhoneNumberType::PREMIUM_RATE));
        $this->assertNull(PhoneNumberRules::typeName(null));
        $this->assertNull(PhoneNumberRules::typeName(-99));
    }
}
