<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CTSParametersSeeder extends Seeder
{
    public function run(): void
    {
        $params = [
            // Processing limits
            ['key' => 'MAX_CHEQUES_PER_DAY',        'value' => '100000', 'module' => 'PROCESSING', 'description' => 'Maximum cheques processed per day (grid-wide)'],
            ['key' => 'MAX_CHEQUES_PER_USER_DAY',   'value' => '500',    'module' => 'PROCESSING', 'description' => 'Maximum cheques a single user can process per day'],
            ['key' => 'HIGH_VALUE_THRESHOLD',        'value' => '500000', 'module' => 'PROCESSING', 'description' => 'Amount (INR) above which high-value alert is triggered'],
            ['key' => 'DUAL_VERIFY_THRESHOLD',       'value' => '100000', 'module' => 'PROCESSING', 'description' => 'Amount requiring dual verification (Maker + Checker)'],
            ['key' => 'FUTURE_DATE_DAYS',            'value' => '90',     'module' => 'PROCESSING', 'description' => 'Maximum future days allowed on cheque date'],

            // IQA
            ['key' => 'IQA_MIN_IMAGE_LENGTH_PX',    'value' => '1000',   'module' => 'IQA', 'description' => 'Minimum image width in pixels'],
            ['key' => 'IQA_MAX_IMAGE_LENGTH_PX',    'value' => '6000',   'module' => 'IQA', 'description' => 'Maximum image width in pixels'],
            ['key' => 'IQA_MIN_IMAGE_HEIGHT_PX',    'value' => '400',    'module' => 'IQA', 'description' => 'Minimum image height in pixels'],
            ['key' => 'IQA_DPI',                    'value' => '200',    'module' => 'IQA', 'description' => 'Required DPI for scanned images'],
            ['key' => 'IQA_COMPRESSION',            'value' => 'CCITT4', 'module' => 'IQA', 'description' => 'Required compression format (CCITT4 per NPCI spec)'],

            // Security
            ['key' => 'PASSWORD_MIN_LENGTH',         'value' => '12',    'module' => 'SECURITY', 'description' => 'Minimum password length'],
            ['key' => 'PASSWORD_EXPIRY_DAYS',        'value' => '30',    'module' => 'SECURITY', 'description' => 'Days before password expiry'],
            ['key' => 'MAX_LOGIN_ATTEMPTS',          'value' => '3',     'module' => 'SECURITY', 'description' => 'Max failed login attempts before lock'],
            ['key' => 'LOCKOUT_MINUTES',             'value' => '30',    'module' => 'SECURITY', 'description' => 'Lockout duration in minutes after max login attempts'],
            ['key' => 'SESSION_TIMEOUT_MINUTES',     'value' => '15',    'module' => 'SECURITY', 'description' => 'Session idle timeout (minutes)'],
            ['key' => 'VAPT_FREQUENCY_MONTHS',       'value' => '6',     'module' => 'SECURITY', 'description' => 'VAPT assessment frequency (months)'],
            ['key' => 'PT_FREQUENCY_MONTHS',         'value' => '12',    'module' => 'SECURITY', 'description' => 'Penetration test frequency (months)'],
            ['key' => 'AUDIT_RETENTION_YEARS',       'value' => '10',    'module' => 'SECURITY', 'description' => 'Audit log / image retention period (years)'],

            // SLA
            ['key' => 'SLA_UPTIME_PERCENT',          'value' => '99.95', 'module' => 'SLA', 'description' => 'System uptime SLA target (%)'],
            ['key' => 'SLA_RTO_MINUTES',             'value' => '30',    'module' => 'SLA', 'description' => 'Recovery Time Objective (minutes)'],
            ['key' => 'SLA_RPO_MINUTES',             'value' => '5',     'module' => 'SLA', 'description' => 'Recovery Point Objective (minutes)'],
            ['key' => 'SLA_P1_INCIDENT_MINUTES',     'value' => '15',    'module' => 'SLA', 'description' => 'P1 incident response time (minutes)'],
            ['key' => 'SLA_P2_INCIDENT_HOURS',       'value' => '2',     'module' => 'SLA', 'description' => 'P2 incident response time (hours)'],
            ['key' => 'SLA_P3_INCIDENT_HOURS',       'value' => '8',     'module' => 'SLA', 'description' => 'P3 incident response time (hours)'],

            // Image archival
            ['key' => 'IMAGE_ARCHIVE_YEARS',         'value' => '10',    'module' => 'ARCHIVE', 'description' => 'Image retention period per RBI mandate (years)'],
            ['key' => 'IMAGE_COMPRESSION_RATIO',     'value' => '10',    'module' => 'ARCHIVE', 'description' => 'Image compression ratio (approx 10:1 BW)'],
            ['key' => 'IMAGE_ANSI_STANDARD',         'value' => 'X9.37', 'module' => 'ARCHIVE', 'description' => 'ANSI standard for image archival'],

            // MICR
            ['key' => 'MICR_SORT_CODE_LENGTH',       'value' => '9',     'module' => 'MICR', 'description' => 'Bank sort code length (9 digits per CTS spec)'],
            ['key' => 'MICR_CHEQUE_NO_LENGTH',       'value' => '6',     'module' => 'MICR', 'description' => 'Cheque number length'],

            // PKI
            ['key' => 'PKI_KEY_SIZE',                'value' => '2048',         'module' => 'PKI', 'description' => 'RSA key size for digital signatures'],
            ['key' => 'PKI_SIGNING_ALGORITHM',       'value' => 'SHA256withRSA','module' => 'PKI', 'description' => 'Signing algorithm per RBI mandate'],
            ['key' => 'PKI_IDRBT_CPS_VERSION',       'value' => '3.0',          'module' => 'PKI', 'description' => 'IDRBT CPS version for certificate issuance'],

            // Grid
            ['key' => 'TOTAL_BRANCHES',              'value' => '3500',  'module' => 'GRID', 'description' => 'Total IOB branches in CTS National Grid'],
            ['key' => 'NPCI_GRID_CODES',             'value' => 'NGCC01,NGCC02,NGCC03', 'module' => 'GRID', 'description' => 'Active NPCI grid codes'],
        ];

        foreach ($params as $param) {
            DB::table('cts_parameters')->updateOrInsert(
                ['key' => $param['key']],
                array_merge($param, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        $this->command->info('CTS parameters seeded: ' . count($params) . ' records.');
    }
}
