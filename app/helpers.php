<?php

use Stevebauman\Purify\Facades\Purify;

if (! function_exists('purify')) {
    function purify(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }
        return Purify::clean($html);
    }
}
