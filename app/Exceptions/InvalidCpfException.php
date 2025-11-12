<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidCpfException extends RuntimeException
{
    public function __construct(string $message = 'O CPF informado não é válido.')
    {
        parent::__construct($message);
    }
}
