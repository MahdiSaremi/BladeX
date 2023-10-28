<?php

namespace Mmb\BladeX;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\View\Factory as LaravelViewFactory;
use Illuminate\View\View;
use Illuminate\View\ViewName;

class Factory
{

    public function getEmptyResolve()
    {
        return fn($content) => new HtmlPure($content['slot']);
    }

    public function getLayoutName(string $name)
    {
        return preg_replace_callback('/(\.|:|^)([^.:]*?)$/', fn($x) => ($x[1] ? '.' : '') . 'layouts' . ($x[1] ? '' : '.') . $x[0], $name);
    }

    public function getPartialName(string $name)
    {
        return preg_replace_callback('/(\.|:|^)([^.:]*?)$/', fn($x) => ($x[1] ? '.' : '') . 'partials' . ($x[1] ? '' : '.') . $x[0], $name);
    }

    public function getComponentName(string $name)
    {
        return preg_replace_callback('/(\.|:|^)([^.:]*?)$/', fn($x) => ($x[1] ? '.' : '') . 'components' . ($x[1] ? '' : '.') . $x[0], $name);
    }

}
