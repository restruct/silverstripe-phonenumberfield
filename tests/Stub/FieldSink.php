<?php

namespace Restruct\PhoneNumberField\Tests\Stub;

use SilverStripe\ORM\DataObjectInterface;

/**
 * The smallest thing Form::saveInto() accepts: records what each field wrote, nothing else.
 */
class FieldSink implements DataObjectInterface
{
    public array $written = [];

    public function __construct()
    {
    }

    public function write()
    {
        return 0;
    }

    public function delete()
    {
    }

    # `: mixed` because SS6's DataObjectInterface declares it; SS4/SS5 declare no return type,
    # which a child may narrow, so the one signature fits every major. The parameter stays
    # untyped for the same reason in reverse (SS4/SS5 leave it untyped, a child may not narrow it).
    public function __get($fieldName): mixed
    {
        return $this->written[$fieldName] ?? null;
    }

    public function setCastedField($fieldName, $val)
    {
        $this->written[$fieldName] = $val;

        return $this;
    }
}
