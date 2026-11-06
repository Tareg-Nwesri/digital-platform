<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class FeatureUnavailable extends AccessDeniedHttpException
{
    public static function for(string $feature): self
    {
        return new self(__('Your team does not have access to this feature (:feature).', ['feature' => $feature]));
    }
}
