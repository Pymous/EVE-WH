<?php

return [
    /*
    |--------------------------------------------------------------------------
    | EVE Online ESI API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for interacting with EVE Online's
    | ESI API. You will need to register an application at:
    | https://developers.eveonline.com/ to get your client ID and secret.
    |
    */

    // ESI API Client ID and Secret obtained from developers.eveonline.com
    'client_id' => env('EVE_ESI_CLIENT_ID'),
    'client_secret' => env('EVE_ESI_CLIENT_SECRET'),

    // OAuth Callback URL
    'callback_url' => env('EVE_ESI_CALLBACK_URL'),

    // Required scopes for this application
    'scopes' => env('EVE_ESI_SCOPES', 'esi-markets.structure_markets.v1 esi-universe.read_structures.v1'),

    // Common EVE region IDs
    'regions' => [
        'jita' => 10000002,     // The Forge (Jita)
        'amarr' => 10000043,    // Domain (Amarr)
        'dodixie' => 10000032,  // Sinq Laison (Dodixie)
        'rens' => 10000030,     // Heimatar (Rens)
        'hek' => 10000042,      // Metropolis (Hek)
    ],

    // Map our application station names to EVE station IDs
    'stations' => [
        'jita' => 60003760,      // Jita IV - Moon 4 - Caldari Navy Assembly Plant
        'amarr' => 60008494,     // Amarr VIII (Oris) - Emperor Family Academy
        'dodixie' => 60011866,   // Dodixie IX - Moon 20 - Federation Navy Assembly Plant
        'rens' => 60005686,      // Rens VI - Moon 8 - Brutor Tribe Treasury
        'hek' => 60003760,       // Hek VIII - Moon 12 - Boundless Creation Factory
    ],
];
