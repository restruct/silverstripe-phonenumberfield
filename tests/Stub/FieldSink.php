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

    public function __get($fieldName)
    {
        return $this->written[$fieldName] ?? null;
    }

    public function setCastedField($fieldName, $val)
    {
        $this->written[$fieldName] = $val;

        return $this;
    }
}
