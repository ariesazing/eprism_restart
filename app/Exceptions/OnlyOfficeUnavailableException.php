<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A self-hosted ONLYOFFICE Document Server call couldn't be completed — unreachable, timed
 * out, or returned an error — anywhere in OnlyOfficeService. Deliberately one exception type
 * for every such failure mode (see OnlyOfficeService's own class doc), so callers only ever
 * need to catch one thing.
 */
class OnlyOfficeUnavailableException extends RuntimeException {}
