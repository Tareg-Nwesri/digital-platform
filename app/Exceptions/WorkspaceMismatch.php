<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class WorkspaceMismatch extends NotFoundHttpException
{
    public static function make(): self
    {
        return new self(__('Not found.'));
    }
}
