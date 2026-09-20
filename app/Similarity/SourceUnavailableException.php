<?php

namespace App\Similarity;

use RuntimeException;

/**
 * A similarity source can't continue (bad API key, rate limit exhausted, service down). Not
 * a failure of the check as a whole — the runner records the message as a warning on the
 * report and carries on with the remaining sources.
 */
class SourceUnavailableException extends RuntimeException {}
