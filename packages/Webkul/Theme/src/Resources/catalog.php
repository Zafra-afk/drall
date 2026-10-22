<?php

/*
||--------------------------------------------------------------------------
|| Shop Theme Catalog
||--------------------------------------------------------------------------
||
|| Themes offered for the storefront, mirroring https://bagisto.com/en/bagisto-themes/.
||
|| An entry whose `code` matches a theme in `config/themes.php` is treated as installed.
|| `screenshot` is a remote url, or a path relative to the admin asset directory.
||
*/

return [
    [
        'code' => 'default',
        'name' => 'Default',
        'author' => 'Webkul',
        'version' => '2.4',
        'url' => null,
        'demo_url' => 'https://commerce.bagisto.com/',
        'screenshot' => 'images/themes/default.jpg',
        'rating' => null,
        'tags' => ['free', 'starter'],
        'description' => 'The theme Bagisto ships with. A clean, responsive storefront that works out of the box for any catalog.',
    ],
];
