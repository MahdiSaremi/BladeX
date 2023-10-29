# BladeX
This package adds some features to make it easier to work with components and html tags

```html
<AppLayout>
    <x-slot name="title">BladeX</x-slot>
    
    <MyButton type="primary">Click here</MyButton>
</AppLayout>
```


## Requirements
* Php 8.0 or higher
* Laravel 10.0 or higher


## Installation
Install the package with composer:
```shell
composer require mmb/bladex
```

Then clear the compiled view caches to recompile blade files:
```shell
php artisan view:clear
```


## Compiling
BladeX converts the components tag into proper php code at compile time! Some operations are not at runtime.

This means that if you change something that doesn't recompile the source file, but affects the code, you have to clear the compiled caches to recompile:
```shell
php artisan view:clear
```


## Component
The component call name should be `PascalCase`, but the file name should be `kebab-case`.

To use a blade file as a component, the two views must be in the same directory.

- pages/users/create-button.blade.php:
```html
<button class="btn btn-primary">{{ $slot }}</button>
```

- pages/users/index.blade.php:
```html
<h1>Create user:</h1>
<CreateButton>Create</CreateButton>
```

If you want to use this component from other directories (eg `pages/`), just separate the component name with a dot `.` write:

- pages/some.blade.php:
```html
<div class="container">
    <Users.CreateButton>Create User</Users.CreateButton>
</div>
```

> Note: If the component file is not found at compile time, the component will not compile.


## Use
If you want to use any components anywhere with any name, you can use the magic blade directive `@use`:

- admin/dashboard.blade.php:
```html
@use('pages.users.create-button')

<CreateButton>Create User</CreateButton>
```

For custom name:
```html
@use('pages.users.create-button', 'NewUser')

<NewUser>Create User</NewUser>
```


## Directive Helpers

### Layout
Layout equals to `@extends`, but only adds `layouts.` name to your view name.
For example `@layout('guest')` converts to `@extends('layouts.guest')` and `@layout('admin.main')` to `@extends('admin.layouts.main')`
```html
@layout('guest')

@section('content')
    <h1>Hello World</h1>
@endsection
```

> Also, you can use components for layouts.

### Partial
Partial equals to `@include`, but only adds `partials.` name to your view name.
For example `@layout('edit')` converts to `@extends('partials.guest')` and `@layout('admin.delete')` to `@extends('admin.partials.delete')`
```html
<AppLayout>
    @partial('users.my-info')
</AppLayout>
```



## Current Directory Name
You can use `@` in `@include`, `@use`, `@layout`, `@extends`, `@partial` to use current path reference.

- admin/dashboard.blade.php:
```html
@use('admin.btn-create')
@use('@btn-create')

@include('admin.partials.create-user')
@include('@partials.create-user')
@partial('@create-user')
```


## Compile Settings
Add the `.blade.php` file wherever you want to apply the sub files:
```php
<?php

# Note: After editing this source, run `php artisan view:clear` one time.

return [
    # Use components alias
    'use' => [
        # Example: 'components.btn', 'Logging' => 'admin.log'.
    ],
];
```

For example, create file `resources/views/.blade.php` with the following content:
```php
<?php

# Note: After editing this source, run `php artisan view:clear` once.

return [
    # Use components alias
    'use' => [
        # Example: 'components.btn', 'Logging' => 'admin.log'.
        'AppLayout' => 'layouts.app',
        'GuestLayout' => 'layouts.guest',
    ],
];
```
Now, you can use layouts with components in any blade files in `resources/views`
```html
<AppLayout>
    <p>Content</p>
</AppLayout>
```


## Support
- Telegram: [@Mahdi_Saremi](https://t.me/Mahdi_Saremi)
- Instagram: [@mahdi_saremi_org](https://instagram.org/mahdi_saremi_org)