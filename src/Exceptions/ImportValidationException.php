<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Exceptions;

final class ImportValidationException extends RoleMatrixException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
