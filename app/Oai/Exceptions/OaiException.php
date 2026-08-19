<?php

namespace Alpha\Journal\Oai\Exceptions;

use RuntimeException;

/**
 * OAI-PMH errors are never HTTP error codes — the response is always HTTP 200 with an
 * <error code="..."> element in place of the verb's normal content (OAI-PMH 2.0 §3.6).
 */
class OaiException extends RuntimeException
{
    /**
     * Named errorCode, not code — the latter collides with the built-in (non-readonly, int)
     * Exception::$code property.
     */
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public static function badVerb(string $message = 'Illegal OAI verb.'): self
    {
        return new self('badVerb', $message);
    }

    public static function badArgument(string $message): self
    {
        return new self('badArgument', $message);
    }

    public static function badResumptionToken(string $message = 'The resumptionToken is invalid or has expired.'): self
    {
        return new self('badResumptionToken', $message);
    }

    public static function cannotDisseminateFormat(string $message = 'The metadata format is not supported.'): self
    {
        return new self('cannotDisseminateFormat', $message);
    }

    public static function idDoesNotExist(string $message = 'No matching identifier.'): self
    {
        return new self('idDoesNotExist', $message);
    }

    public static function noRecordsMatch(string $message = 'No records match the given criteria.'): self
    {
        return new self('noRecordsMatch', $message);
    }

    public static function noSetHierarchy(string $message = 'This repository does not support sets.'): self
    {
        return new self('noSetHierarchy', $message);
    }
}
