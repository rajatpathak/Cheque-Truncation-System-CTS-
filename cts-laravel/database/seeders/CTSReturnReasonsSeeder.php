<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CTSReturnReasonsSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            // Drawer-related
            ['code' => '01', 'description' => 'Funds Insufficient',                          'category' => 'DRAWER'],
            ['code' => '02', 'description' => 'Exceeds Arrangement',                         'category' => 'DRAWER'],
            ['code' => '03', 'description' => 'Effects Not Cleared',                         'category' => 'DRAWER'],
            ['code' => '04', 'description' => 'Full Cover Not Received',                     'category' => 'DRAWER'],
            ['code' => '05', 'description' => 'Payment Stopped',                             'category' => 'DRAWER'],
            ['code' => '06', 'description' => 'Account Closed',                              'category' => 'DRAWER'],
            ['code' => '07', 'description' => 'Account Dormant',                             'category' => 'DRAWER'],
            ['code' => '08', 'description' => 'Drawer Signature Absent',                     'category' => 'DRAWER'],
            ['code' => '09', 'description' => 'Drawer Signature Differs',                    'category' => 'DRAWER'],
            ['code' => '10', 'description' => 'Drawer Signature Incomplete',                 'category' => 'DRAWER'],

            // Payee-related
            ['code' => '11', 'description' => 'Payee Name Differs',                          'category' => 'PAYEE'],
            ['code' => '12', 'description' => 'Payee Name Required',                         'category' => 'PAYEE'],
            ['code' => '13', 'description' => 'Payee Account Type Mismatch',                 'category' => 'PAYEE'],

            // Instrument defects
            ['code' => '14', 'description' => 'Instrument Mutilated/Damaged',                'category' => 'INSTRUMENT'],
            ['code' => '15', 'description' => 'Instrument Irregularly Drawn',                'category' => 'INSTRUMENT'],
            ['code' => '16', 'description' => 'Instrument Undated',                          'category' => 'INSTRUMENT'],
            ['code' => '17', 'description' => 'Instrument Post Dated',                       'category' => 'INSTRUMENT'],
            ['code' => '18', 'description' => 'Instrument Stale (older than 3 months)',      'category' => 'INSTRUMENT'],
            ['code' => '19', 'description' => 'Amount in Words and Figures Differ',          'category' => 'INSTRUMENT'],
            ['code' => '20', 'description' => 'Material Alteration on Instrument',           'category' => 'INSTRUMENT'],

            // Technical / IQA
            ['code' => '21', 'description' => 'IQA Failure - Image Below Standard',          'category' => 'TECHNICAL'],
            ['code' => '22', 'description' => 'MICR Band Unreadable',                        'category' => 'TECHNICAL'],
            ['code' => '23', 'description' => 'MICR Sort Code Mismatch',                     'category' => 'TECHNICAL'],
            ['code' => '24', 'description' => 'Duplicate Instrument',                        'category' => 'TECHNICAL'],

            // Fraud
            ['code' => '25', 'description' => 'Suspected Fraud / Forged Instrument',        'category' => 'FRAUD'],
            ['code' => '26', 'description' => 'Non-CTS (P2F Penalty Applicable)',            'category' => 'FRAUD'],
            ['code' => '27', 'description' => 'UV Check Failed - Security Feature Missing', 'category' => 'FRAUD'],

            // Legal
            ['code' => '28', 'description' => 'Garnishee Order Received',                    'category' => 'LEGAL'],
            ['code' => '29', 'description' => 'Insolvency Order Received',                   'category' => 'LEGAL'],
            ['code' => '30', 'description' => 'Death of Drawer Intimated',                   'category' => 'LEGAL'],
        ];

        foreach ($reasons as $r) {
            DB::table('cts_return_reason_codes')->updateOrInsert(
                ['code' => $r['code']],
                array_merge($r, ['active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }

        $this->command->info('Return reason codes seeded: ' . count($reasons) . ' records.');
    }
}
