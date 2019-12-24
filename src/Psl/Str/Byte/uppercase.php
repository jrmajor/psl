<?php

declare(strict_types=1);

namespace Psl\Str\Byte;

/**
 * Returns the string with all alphabetic characters converted to uppercase.
 */
function uppercase(string $string): string
{
    return \strtoupper($string);
}
