<?php

return [
    'reports_disk' => env('IMPORT_REPORTS_DISK', 'local'),
    'reports_path' => env('IMPORT_REPORTS_PATH', 'import/reports'),

    'models' => [
        'company' => [
            'table' => 'companies',
            'class_candidates' => [
                'Fleetbase\\FleetbaseCore\\Models\\Company',
            ],
            'required' => ['name'],
            'optional' => ['uuid', 'public_id', 'phone', 'email', 'country', 'currency', 'status'],
            'unique_by' => ['uuid', 'public_id', 'name'],
        ],

        'vehicle' => [
            'table' => 'vehicles',
            'class_candidates' => [
                'Fleetbase\\FleetbaseCore\\Models\\Vehicle',
                'Fleetbase\\FleetOps\\Models\\Vehicle',
            ],
            'required' => ['name'],
            'optional' => ['uuid', 'plate_number', 'vin', 'make', 'model', 'year', 'status', 'company_uuid', 'company_name'],
            'unique_by' => ['uuid', 'plate_number', 'vin', 'name'],
            'relations' => [
                'company' => [
                    'source_fields' => ['company_uuid', 'company_name'],
                    'target_column' => 'company_uuid',
                ],
            ],
        ],

        'user' => [
            'table' => 'users',
            'class_candidates' => [
                'Fleetbase\\FleetbaseCore\\Models\\User',
                'App\\Models\\User',
            ],
            'required' => ['name', 'email'],
            'optional' => ['uuid', 'phone', 'password', 'status', 'company_uuid', 'company_name'],
            'unique_by' => ['uuid', 'email'],
            'relations' => [
                'company' => [
                    'source_fields' => ['company_uuid', 'company_name'],
                    'target_column' => 'company_uuid',
                ],
            ],
        ],
    ],
];
