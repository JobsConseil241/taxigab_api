<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tarification Taxi Gab (FCFA / XAF)
    |--------------------------------------------------------------------------
    |
    | Ces valeurs sont des placeholders pour le prototype. Le client définira
    | les vrais tarifs au MVP; le panel admin permettra leur modification.
    |
    */

    'pricing' => [
        'base_fare'     => (int) env('TAXIGAB_BASE_FARE', 500),
        'per_km'        => (int) env('TAXIGAB_PER_KM', 350),
        'per_min_wait'  => (int) env('TAXIGAB_PER_MIN_WAIT', 50),
        'min_fare'      => (int) env('TAXIGAB_MIN_FARE', 1000),
        'currency'      => env('TAXIGAB_CURRENCY', 'XAF'),
        'rounding_step' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Matching
    |--------------------------------------------------------------------------
    */

    'matching' => [
        'radius_km'             => (float) env('TAXIGAB_MATCH_RADIUS_KM', 10),
        'accept_window_seconds' => 30,
        'location_ping_seconds' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Google
    |--------------------------------------------------------------------------
    */

    'google' => [
        'maps_api_key'       => env('GOOGLE_MAPS_API_KEY'),
        'directions_api_key' => env('GOOGLE_DIRECTIONS_API_KEY'),
    ],

];
