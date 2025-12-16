<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AWS SDK Configuration
    |--------------------------------------------------------------------------
    |
    | The configuration options set in this file will be passed directly to
    | the `Aws\Sdk` object, from which all client objects are created. This
    | file is published from the aws/aws-sdk-php-laravel package.
    |
    | See: https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_configuration.html
    |
    */

    'credentials' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],

    'region' => env('AWS_DEFAULT_REGION', 'eu-west-3'),
    'version' => 'latest',

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configure timeouts and connection settings for AWS API calls.
    |
    */

    'http' => [
        'timeout' => 30,
        'connect_timeout' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic retry behavior for failed requests.
    |
    */

    'retries' => [
        'mode' => 'standard',
        'max_attempts' => 3,
    ],

];
