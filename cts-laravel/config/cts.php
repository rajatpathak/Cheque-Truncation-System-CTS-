<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CTS National Grid Configuration
    |--------------------------------------------------------------------------
    | Indian Overseas Bank - Cheque Truncation System
    */

    'bank' => [
        'name'         => 'Indian Overseas Bank',
        'short'        => 'IOB',
        'ifsc_prefix'  => 'IOBA',
        'total_branches' => 3500,
    ],

    'grid' => [
        'dc'  => ['name' => 'Primary DC', 'location' => 'Chennai',   'host' => env('CTS_DC_HOST')],
        'dr'  => ['name' => 'DR Site',    'location' => 'Hyderabad', 'host' => env('CTS_DR_HOST')],
        'uat' => ['name' => 'UAT',        'location' => 'Chennai',   'host' => env('CTS_UAT_HOST')],
    ],

    'processing' => [
        'max_cheques_per_day'     => 100000,
        'max_cheques_per_user_day'=> 500,
        'high_value_threshold'    => env('CTS_HIGH_VALUE_THRESHOLD', 500000),
        'dual_verify_threshold'   => env('CTS_DUAL_VERIFY_THRESHOLD', 100000),
        'future_date_days'        => 90,
        'session_timeout_minutes' => 15,
    ],

    'clearing_types' => [
        'CTS'     => 'CTS Clearing',
        'NONCTS'  => 'Non-CTS (P2F)',
        'SPECIAL' => 'Special Clearing',
        'RETURN'  => 'Return Clearing',
        'GOVT'    => 'Government Cheque',
        'ECS'     => 'ECS/NACH',
    ],

    'iqa' => [
        'min_image_length'  => 1000,
        'max_image_length'  => 6000,
        'min_image_height'  => 400,
        'max_image_height'  => 2000,
        'grey_bits'         => 8,
        'dpi'               => 200,
        'compression'       => 'CCITT4',
        'formats'           => ['GREY', 'BW', 'UV', 'EMBEDDED'],
        'failure_reasons'   => [
            'PARTIAL_IMAGE', 'EXCESSIVE_SKEW', 'PIGGYBACK', 'STREAKS_BANDS',
            'BENT_CORNER', 'BELOW_MIN_SIZE', 'EXCEEDS_MAX_SIZE',
            'TOO_LIGHT', 'TOO_DARK', 'LENGTH_MISMATCH', 'HEIGHT_MISMATCH',
            'TORN_CORNER', 'UV_FAILURE', 'MICR_UNREADABLE',
        ],
    ],

    'micr' => [
        'sort_code_length'  => 9,
        'cheque_no_length'  => 6,
        'account_length'    => 6,
        'transaction_code'  => 2,
        'city_code'         => 3,
    ],

    'npci' => [
        'base_url'         => env('NPCI_BASE_URL'),
        'api_version'      => 'v2',
        'timeout'          => 30,
        'grid_codes'       => ['NGCC01', 'NGCC02', 'NGCC03'],
    ],

    'chi_dem' => [
        'host'     => env('CHI_DEM_HOST'),
        'port'     => env('CHI_DEM_PORT', 8443),
        'cert'     => env('CHI_DEM_CERT_PATH'),
        'key'      => env('CHI_DEM_KEY_PATH'),
        'timeout'  => 60,
    ],

    'cbs' => [
        'finacle_host'    => env('FINACLE_HOST'),
        'finacle_port'    => env('FINACLE_PORT', 8080),
        'finacle_timeout' => 15,
        'finacle_version' => env('FINACLE_VERSION', '10.x'),
    ],

    'pki' => [
        'hsm_host'          => env('HSM_HOST'),
        'hsm_port'          => env('HSM_PORT', 1792),
        'cert_store'        => env('PKI_CERT_STORE', '/certs/cts'),
        'signing_algorithm' => 'SHA256withRSA',
        'key_size'          => 2048,
        'ca_cert'           => env('PKI_CA_CERT'),
        'idrbt_cps_version' => '3.0',
    ],

    'image_storage' => [
        'archive_years'       => 10,
        'storage_driver'      => env('IMAGE_STORAGE_DRIVER', 'local'),
        'compression_ratio'   => 10,
        'worm_enabled'        => true,
        'ansi_standard'       => 'X9.37',
        'hash_algorithm'      => 'SHA256',
    ],

    'notifications' => [
        'sms_gateway'   => env('SMS_GATEWAY_URL'),
        'email_driver'  => env('MAIL_MAILER', 'smtp'),
        'high_value_sms'=> true,
        'return_email'  => true,
    ],

    'security' => [
        'password_min_length'    => 12,
        'password_expiry_days'   => 30,
        'max_login_attempts'     => 3,
        'lockout_minutes'        => 30,
        'owasp_enabled'          => true,
        'audit_retention_years'  => 10,
        'vapt_frequency_months'  => 6,
        'pt_frequency_months'    => 12,
    ],

    'sla' => [
        'uptime_percent'         => 99.95,
        'rto_minutes'            => 30,
        'rpo_minutes'            => 5,
        'incident_p1_minutes'    => 15,
        'incident_p2_hours'      => 2,
        'incident_p3_hours'      => 8,
    ],
];
