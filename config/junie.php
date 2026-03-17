<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Junie Base Guidelines URL
    |--------------------------------------------------------------------------
    |
    | This is the remote URL where your base AI guidelines are hosted.
    | It will be merged with your local guidelines file.
    |
    */
    'base_url' => env('JUNIE_BASE_GUIDELINES_URL', 'https://api.nuclino.com/v1/items/your-item-id'),

    /*
    |--------------------------------------------------------------------------
    | Nuclino API Key
    |--------------------------------------------------------------------------
    |
    | Use your Nuclino API key for authentication. You can find it in your
    | Nuclino account settings.
    |
    */
    'api_key' => env('JUNIE_API_KEY'),
];
