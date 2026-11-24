<?php

namespace App\Service\JobScanner\Exceptions;

/**
 * A job source could not be scanned at all (blocked, down, or returned
 * something we can't parse). The message is safe to show to end users.
 */
class SourceUnavailableException extends \RuntimeException
{
}
