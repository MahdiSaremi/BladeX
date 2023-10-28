<?php

namespace Mmb\BladeX;

use Illuminate\Contracts\Support\Htmlable;

class HtmlPure implements Htmlable
{

    public function __construct(public string $html)
    {
    }

    public function toHtml()
    {
        return $this->html;
    }

}
