<?php

namespace App\Support;

class EmailValidation
{
    public static function rule(): string
    {
        // Keep production strict, but avoid making local QA and automated tests
        // depend on external DNS lookups.
        return app()->environment('testing') ? 'email:rfc' : 'email:rfc,dns';
    }
}
