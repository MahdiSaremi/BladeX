<?php

namespace Mmb\BladeX;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\DynamicComponent;
use Illuminate\View\FileViewFinder;

class BladeXCompiler
{

    public array $aliases;

    public array $boundAttributes;

    public BladeCompiler $blade;

    public BladeXServiceProvider $provider;

    public function use(string $component, string $alias = '')
    {
        if($alias === '')
        {
            $alias = Str::afterLast($component, '.');
            // $this->aliases[ucfirst(Str::camel($alias))] = $component;
            $alias = ucfirst(Str::camel($alias));
        }

        $this->aliases[$alias] = $component;
    }

    public function reset(BladeXServiceProvider $provider)
    {
        $this->aliases = [];
        $this->boundAttributes = [];
        $this->blade = app(BladeCompiler::class);
        $this->provider = $provider;
    }



    public function compiler() : BladeCompiler
    {
        return app(BladeCompiler::class);
    }

    public function getBladePathInfo(string $file)
    {
        $finder = View::getFinder();
        if($finder instanceof FileViewFinder)
        {
            foreach($finder->getPaths() as $path)
            {
                if(Str::startsWith($file, $path))
                {
                    $sub = trim(substr(str_replace('\\', '/', $file), strlen($path)), '/');
                    $name = str_replace('/', '.', preg_replace('/\..*$/', '', $sub));
                    return [
                        null,
                        Str::contains($name, '.') ? Str::beforeLast($name, '.') : null,
                        Str::contains($name, '.') ? Str::afterLast($name, '.') : $name,
                        $name,
                    ];
                }
            }

            foreach($finder->getHints() as $namespace => $hint)
            {
                foreach($hint as $path)
                {
                    if(Str::startsWith($file, $path))
                    {
                        $sub = trim(substr(str_replace('\\', '/', $file), strlen($path)), '/');
                        $name = str_replace('/', '.', preg_replace('/\..*$/', '', $sub));
                        return [
                            $namespace,
                            Str::contains($name, '.') ? Str::beforeLast($name, '.') : null,
                            Str::contains($name, '.') ? Str::afterLast($name, '.') : $name,
                            $namespace . '::' . $name,
                        ];
                    }
                }
            }
        }

        $sub = preg_replace('/^.*[\/\\\\]resources[\/\\\\]views[\/\\\\]/', '', $path);
        $name = str_replace('/', '.', preg_replace('/\..*$/', '', $sub));
        return [
            null,
            Str::contains($name, '.') ? Str::beforeLast($name, '.') : null,
            Str::contains($name, '.') ? Str::afterLast($name, '.') : $name,
            $name,
        ];
    }

    public function getNameFromCurrentSpace(string $name)
    {
        [ $namespace, $space, $_name, $fullName ] = $this->getBladePathInfo($this->compiler()->getPath());

        return
            (is_null($namespace) ? '' : $namespace . '::') .
            (is_null($space) ? '' : $space . '.') .
            $name
            ;
    }

    public function loadConfigFile()
    {
        $viewsPath = app_path();

        $path = dirname($this->compiler()->getPath());
        $includes = [];
        while(strlen($path) >= strlen($viewsPath))
        {
            if(file_exists($path . '/.blade.php'))
            {
                $includes[] = $path . '/.blade.php';
            }

            $path = dirname($path);
        }

        foreach(array_reverse($includes) as $include)
        {
            $config = require $include;

            foreach($config['use'] ?? [] as $alias => $name)
            {
                if(!is_string($alias))
                {
                    $alias = '';
                }

                $this->use($name, $alias);
            }
        }
    }



    public function getRealComponentName(string $name)
    {
        # <@Input></@Input>
        if(@$name[0] == '@')
        {
            $formattedName = implode('.', array_map(fn($str) => Str::kebab($str), explode('.', substr($name, 1))));
            return $this->getNameFromCurrentSpace($formattedName);
        }

        # @use('@partial.input', 'Input')
        # <Input />
        if(array_key_exists($name, $this->aliases))
        {
            return $this->aliases[$name];
        }

        # <Edit />
        if($name && strtolower($name[0]) != $name[0])
        {
            $formattedName = implode('.', array_map(fn($str) => Str::kebab($str), explode('.', $name)));
            if(View::exists($fullName = $this->getNameFromCurrentSpace($formattedName)))
            {
                return $fullName;
            }
        }

        return false;
    }

    /**
     * Compile the tags within the given string.
     *
     * @param  string  $value
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function compileTags(string $value)
    {
        $value = $this->compileSelfClosingTags($value);
        $value = $this->compileOpeningTags($value);
        $value = $this->compileClosingTags($value);

        return $value;
    }

    /**
     * Compile the opening tags within the given string.
     *
     * @param  string  $value
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function compileOpeningTags(string $value)
    {
        $pattern = "/
            <
                \s*
                (@?[\w\-\:\.]+)
                (?<attributes>
                    (?:
                        \s+
                        (?:
                            (?:
                                @(?:class)(\( (?: (?>[^()]+) | (?-1) )* \))
                            )
                            |
                            (?:
                                @(?:style)(\( (?: (?>[^()]+) | (?-1) )* \))
                            )
                            |
                            (?:
                                \{\{\s*\\\$attributes(?:[^}]+?)?\s*\}\}
                            )
                            |
                            (?:
                                (\:\\\$)(\w+)
                            )
                            |
                            (?:
                                [\w\-:.@%]+
                                (
                                    =
                                    (?:
                                        \\\"[^\\\"]*\\\"
                                        |
                                        \'[^\']*\'
                                        |
                                        [^\'\\\"=<>]+
                                    )
                                )?
                            )
                        )
                    )*
                    \s*
                )
                (?<![\/=\-])
            >
        /x";

        return preg_replace_callback($pattern, function (array $matches) {
            $this->boundAttributes = [];

            $attributes = $this->getAttributesFromAttributeString($matches['attributes']);

            $line = $this->componentString($matches[1], $attributes);

            if($line === false)
            {
                return $matches[0];
            }

            return $line;
        }, $value);
    }

    /**
     * Compile the self-closing tags within the given string.
     *
     * @param  string  $value
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function compileSelfClosingTags(string $value)
    {
        $pattern = "/
            <
                \s*
                (@?[\w\-\:\.]+)
                \s*
                (?<attributes>
                    (?:
                        \s+
                        (?:
                            (?:
                                @(?:class)(\( (?: (?>[^()]+) | (?-1) )* \))
                            )
                            |
                            (?:
                                @(?:style)(\( (?: (?>[^()]+) | (?-1) )* \))
                            )
                            |
                            (?:
                                \{\{\s*\\\$attributes(?:[^}]+?)?\s*\}\}
                            )
                            |
                            (?:
                                (\:\\\$)(\w+)
                            )
                            |
                            (?:
                                [\w\-:.@%]+
                                (
                                    =
                                    (?:
                                        \\\"[^\\\"]*\\\"
                                        |
                                        \'[^\']*\'
                                        |
                                        [^\'\\\"=<>]+
                                    )
                                )?
                            )
                        )
                    )*
                    \s*
                )
            \/>
        /x";

        return preg_replace_callback($pattern, function (array $matches) {
            $this->boundAttributes = [];

            $attributes = $this->getAttributesFromAttributeString($matches['attributes']);

            $line = $this->componentString($matches[1], $attributes);

            if($line === false)
            {
                return $matches[0];
            }

            return $line."\n@endComponentClass##END-COMPONENT-CLASS##";
        }, $value);
    }

    /**
     * Compile the Blade component string for the given component and attributes.
     *
     * @param  string  $component
     * @param  array  $attributes
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function componentString(string $component, array $attributes)
    {
        $component = $this->getRealComponentName($component);
        if($component === false)
        {
            return false;
        }

        $data = collect($attributes);
        $attributes = collect($attributes);

        $data = $data->mapWithKeys(function ($value, $key) {
            return [Str::camel($key) => $value];
        });

        $parameters = [
            'view' => "'$component'",
            'data' => '['.$this->attributesToString($data->all(), $escapeBound = false).']',
        ];

        $class = BladeXAnonymousComponent::class;

        return "##BEGIN-COMPONENT-CLASS##@component('{$class}', '{$component}', [".$this->attributesToString($parameters, $escapeBound = false).'])
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass('.$class.'::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['.$this->attributesToString($attributes->all(), $escapeAttributes = $class !== DynamicComponent::class).']); ?>';
    }

    /**
     * Compile the closing tags within the given string.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileClosingTags(string $value)
    {
        return preg_replace_callback("/<\/\s*(@?[\w\-\:\.]+)\s*>/", function(array $matches)
        {
            $component = $matches[1];
            if(!array_key_exists($component, $this->aliases))
            {
                return $matches[0];
            }

            return ' @endComponentClass##END-COMPONENT-CLASS##';
        }, $value);
    }

    /**
     * Get an array of attributes from the given attribute string.
     *
     * @param  string  $attributeString
     * @return array
     */
    protected function getAttributesFromAttributeString(string $attributeString)
    {
        $attributeString = $this->parseShortAttributeSyntax($attributeString);
        $attributeString = $this->parseAttributeBag($attributeString);
        $attributeString = $this->parseComponentTagClassStatements($attributeString);
        $attributeString = $this->parseComponentTagStyleStatements($attributeString);
        $attributeString = $this->parseBindAttributes($attributeString);

        $pattern = '/
            (?<attribute>[\w\-:.@%]+)
            (
                =
                (?<value>
                    (
                        \"[^\"]+\"
                        |
                        \\\'[^\\\']+\\\'
                        |
                        [^\s>]+
                    )
                )
            )?
        /x';

        if (! preg_match_all($pattern, $attributeString, $matches, PREG_SET_ORDER)) {
            return [];
        }

        return collect($matches)->mapWithKeys(function ($match) {
            $attribute = $match['attribute'];
            $value = $match['value'] ?? null;

            if (is_null($value)) {
                $value = 'true';

                $attribute = Str::start($attribute, 'bind:');
            }

            $value = $this->stripQuotes($value);

            if (str_starts_with($attribute, 'bind:')) {
                $attribute = Str::after($attribute, 'bind:');

                $this->boundAttributes[$attribute] = true;
            } else {
                $value = "'".$this->compileAttributeEchos($value)."'";
            }

            if (str_starts_with($attribute, '::')) {
                $attribute = substr($attribute, 1);
            }

            return [$attribute => $value];
        })->toArray();
    }

    /**
     * Parses a short attribute syntax like :$foo into a fully-qualified syntax like :foo="$foo".
     *
     * @param  string  $value
     * @return string
     */
    protected function parseShortAttributeSyntax(string $value)
    {
        $pattern = "/\s\:\\\$(\w+)/x";

        return preg_replace_callback($pattern, function (array $matches) {
            return " :{$matches[1]}=\"\${$matches[1]}\"";
        }, $value);
    }

    /**
     * Parse the attribute bag in a given attribute string into its fully-qualified syntax.
     *
     * @param  string  $attributeString
     * @return string
     */
    protected function parseAttributeBag(string $attributeString)
    {
        $pattern = "/
            (?:^|\s+)                                        # start of the string or whitespace between attributes
            \{\{\s*(\\\$attributes(?:[^}]+?(?<!\s))?)\s*\}\} # exact match of attributes variable being echoed
        /x";

        return preg_replace($pattern, ' :attributes="$1"', $attributeString);
    }

    /**
     * Parse @class statements in a given attribute string into their fully-qualified syntax.
     *
     * @param  string  $attributeString
     * @return string
     */
    protected function parseComponentTagClassStatements(string $attributeString)
    {
        return preg_replace_callback(
            '/@(class)(\( ( (?>[^()]+) | (?2) )* \))/x', function ($match) {
            if ($match[1] === 'class') {
                $match[2] = str_replace('"', "'", $match[2]);

                return ":class=\"\Illuminate\Support\Arr::toCssClasses{$match[2]}\"";
            }

            return $match[0];
        }, $attributeString
        );
    }

    /**
     * Parse @style statements in a given attribute string into their fully-qualified syntax.
     *
     * @param  string  $attributeString
     * @return string
     */
    protected function parseComponentTagStyleStatements(string $attributeString)
    {
        return preg_replace_callback(
            '/@(style)(\( ( (?>[^()]+) | (?2) )* \))/x', function ($match) {
            if ($match[1] === 'style') {
                $match[2] = str_replace('"', "'", $match[2]);

                return ":style=\"\Illuminate\Support\Arr::toCssStyles{$match[2]}\"";
            }

            return $match[0];
        }, $attributeString
        );
    }

    /**
     * Parse the "bind" attributes in a given attribute string into their fully-qualified syntax.
     *
     * @param  string  $attributeString
     * @return string
     */
    protected function parseBindAttributes(string $attributeString)
    {
        $pattern = "/
            (?:^|\s+)     # start of the string or whitespace between attributes
            :(?!:)        # attribute needs to start with a single colon
            ([\w\-:.@]+)  # match the actual attribute name
            =             # only match attributes that have a value
        /xm";

        return preg_replace($pattern, ' bind:$1=', $attributeString);
    }

    /**
     * Compile any Blade echo statements that are present in the attribute string.
     *
     * These echo statements need to be converted to string concatenation statements.
     *
     * @param  string  $attributeString
     * @return string
     */
    protected function compileAttributeEchos(string $attributeString)
    {
        $value = $this->blade->compileEchos($attributeString);

        $value = $this->escapeSingleQuotesOutsideOfPhpBlocks($value);

        $value = str_replace('<?php echo ', '\'.', $value);
        $value = str_replace('; ?>', '.\'', $value);

        return $value;
    }

    /**
     * Escape the single quotes in the given string that are outside of PHP blocks.
     *
     * @param  string  $value
     * @return string
     */
    protected function escapeSingleQuotesOutsideOfPhpBlocks(string $value)
    {
        return collect(token_get_all($value))->map(function ($token) {
            if (! is_array($token)) {
                return $token;
            }

            return $token[0] === T_INLINE_HTML
                ? str_replace("'", "\\'", $token[1])
                : $token[1];
        })->implode('');
    }

    /**
     * Convert an array of attributes to a string.
     *
     * @param  array  $attributes
     * @param  bool  $escapeBound
     * @return string
     */
    protected function attributesToString(array $attributes, $escapeBound = true)
    {
        return collect($attributes)
            ->map(function (string $value, string $attribute) use ($escapeBound) {
                return $escapeBound && isset($this->boundAttributes[$attribute]) && $value !== 'true' && ! is_numeric($value)
                    ? "'{$attribute}' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute({$value})"
                    : "'{$attribute}' => {$value}";
            })
            ->implode(',');
    }

    /**
     * Strip any quotes from the given string.
     *
     * @param  string  $value
     * @return string
     */
    public function stripQuotes(string $value)
    {
        return Str::startsWith($value, ['"', '\''])
            ? substr($value, 1, -1)
            : $value;
    }

}
