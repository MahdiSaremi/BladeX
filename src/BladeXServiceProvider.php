<?php

namespace Mmb\BladeX;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;

class BladeXServiceProvider extends ServiceProvider
{

    protected $facade = '\\' . BladeX::class;

    public function boot()
    {
        $this->app->singleton(BladeXCompiler::class);

        $this->callAfterResolving(BladeCompiler::class, fn($blade) => $this->bootBlade($blade));

        $this->loadViewsFrom(resource_path('test-views'), 'Test');
    }

    public function bootBlade($blade)
    {
        Blade::directive('layout', function($name)
        {
            throw new \InvalidArgumentException("Invalid layout tag. Should initialize in start of source");
        });
        Blade::directive('partial', function($name)
        {
            return "<?php echo \$__env->make({$this->facade}::getPartialName($name), \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>";
        });

        Blade::directive('endsection', function($expr)
        {
            return "<?php \$__env->stopSection($expr); ?>";
        });
        Blade::directive('ends', function()
        {
            return "<?php \$__env->stopSection(true); ?>";
        });

        Blade::directive('use', function($expr)
        {
            if(preg_match('/^[\'\"](.*)[\'\"]\s*,[\'\"](.*)[\'\"]\s*$/', $expr, $matches))
            {
                $this->proCompiler()->use($matches[1], $matches[2]);
            }
            elseif(preg_match('/^[\'\"](.*)[\'\"]$/', $expr, $matches))
            {
                $this->proCompiler()->use($matches[1]);
            }
            else
            {
                throw new \InvalidArgumentException("Invalid arguments for @use");
            }

            return "";
        });

        Blade::prepareStringsForCompilationUsing([ $this, 'bladeXRender' ]);

        Blade::anonymousComponentPath(resource_path('views/layouts/components'));
    }

    public function bladeXRender(string $value)
    {
        $this->proCompiler()->reset($this);
        $this->proCompiler()->loadConfigFile();

        // Current directory
        $value = preg_replace_callback('/@(include|extends|layout|partial)\((.*?)\)/', function($x)
        {
            $expr = $x[2];
            if(Str::contains($expr, ['"', "'"]))
            {
                $space = $this->proCompiler()->getNameFromCurrentSpace('');
                $expr = preg_replace_callback('/([\'\"])@/', fn($x) => $x[1] . $space, $expr);
                return "@{$x[1]}({$expr})";
            }
            return $x[0];
        }, $value);

        // Layout
        $value = preg_replace('/@layout\((.*?)\)/', "@extends({$this->facade}::getLayoutName(\$1))", $value);

        // Uses
        $value = preg_replace_callback('/@use\((.*?)\)/', function($x)
        {
            $expr = $x[1];
            if(preg_match('/^[\'\"](.*)[\'\"]\s*,\s*[\'\"](.*)[\'\"]\s*$/', $expr, $matches))
            {
                $name = $matches[1];
                if($name[0] == '@')
                {
                    $name = $this->proCompiler()->getNameFromCurrentSpace(substr($name, 1));
                }
                $this->proCompiler()->use($name, $matches[2]);
            }
            elseif(preg_match('/^[\'\"](.*)[\'\"]$/', $expr, $matches))
            {
                $name = $matches[1];
                if($name[0] == '@')
                {
                    $name = $this->proCompiler()->getNameFromCurrentSpace(substr($name, 1));
                }
                $this->proCompiler()->use($name);
            }
            else
            {
                throw new \InvalidArgumentException("Invalid arguments for @use");
            }

            return "";
        }, $value);

        // Component tags
        $value = $this->proCompiler()->compileTags($value);

        return $value;
    }

    public function proCompiler() : BladeXCompiler
    {
        return app(BladeXCompiler::class);
    }

}
