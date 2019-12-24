<?php

declare(strict_types=1);

namespace Psl\Str\Byte;

/**
 * Returns the 'haystack' string with all occurrences of `$needle` replaced by
 * `$replacement`.
 */
function replace(string $haystack, string $needle, string $replacement): string
{
    return \str_replace($needle, $replacement, $haystack);
}
