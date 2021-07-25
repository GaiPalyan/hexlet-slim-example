<?php

namespace Src\Misc;

use JsonException;

/**
 * @throws JsonException
 */
function decode(string $encodedString, $default = true)
{
    return json_decode($encodedString, $default, 512, JSON_THROW_ON_ERROR);
}
