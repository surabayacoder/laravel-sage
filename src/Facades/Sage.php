<?php

namespace Surabayacoder\Sage\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string ask(string $question)
 */
class Sage extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'sage';
    }
}
