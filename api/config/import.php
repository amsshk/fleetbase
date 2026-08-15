<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fleetbase API Key
    |--------------------------------------------------------------------------
    | The API key used to authenticate requests to the Fleetbase API.
    */
    'api_key' => env('FLEETBASE_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Fleetbase API Host
    |--------------------------------------------------------------------------
    */
    'api_host' => env('FLEETBASE_HOST', 'https://api.fleetbase.io'),

    /*
    |--------------------------------------------------------------------------
    | Google Drive Folder ID
    |--------------------------------------------------------------------------
    | The public Google Drive folder ID from which XLSX files are downloaded.
    */
    'google_drive_folder' => env('GOOGLE_DRIVE_FOLDER_ID', ''),
];
