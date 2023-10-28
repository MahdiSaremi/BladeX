<?php

namespace Mmb\BladeX;

use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Closure getEmptyResolve()
 * @method static string getLayoutName(string $name)
 * @method static string getPartialName(string $name)
 * @method static string getComponentName(string $name)
 */
class BladeX extends Facade
{

    protected static function getFacadeAccessor()
    {
        return Factory::class;
    }

}
