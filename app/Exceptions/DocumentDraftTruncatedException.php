<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when Claude's response hit the max_tokens limit before finishing
 * the draft. A silently truncated legal document is worse than an error —
 * this must always surface as a failed DocumentRequest, never a partial .docx.
 */
class DocumentDraftTruncatedException extends Exception
{
}
