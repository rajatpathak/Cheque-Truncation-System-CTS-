<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CTSRolesSeeder extends Seeder
{
    /**
     * Seed all CTS roles and their permissions.
     * Roles are aligned with RBI IS guidelines for CTS.
     */
    public function run(): void
    {
        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Outward Clearing
            'outward.scan', 'outward.batch.create', 'outward.batch.close',
            'outward.batch.submit', 'outward.micr.read', 'outward.micr.correct',
            'outward.instrument.hold', 'outward.instrument.release',

            // Inward Clearing
            'inward.session.create', 'inward.session.submit',
            'inward.data_entry', 'inward.verify',
            'inward.ocr.extract',

            // Fraud Detection
            'fraud.scan', 'fraud.alert.view', 'fraud.alert.resolve',
            'fraud.blacklist.view', 'fraud.blacklist.manage',
            'fraud.positive_pay.check', 'fraud.positive_pay.register',

            // Return Processing
            'returns.view', 'returns.process',
            'returns.represent', 'returns.sign',
            'returns.memo.generate', 'returns.memo.email',

            // Digital Signatures
            'signature.sign_instrument', 'signature.sign_file',
            'signature.sign_batch', 'signature.verify',
            'signature.certificates.view',

            // Integration
            'integration.chi_dem.submit', 'integration.chi_dem.receive',
            'integration.npci.submit', 'integration.cbs.upload',

            // Reporting
            'reports.view', 'reports.export',
            'reports.audit_trail', 'reports.schedule',

            // Image Storage
            'images.view', 'images.archive', 'images.email', 'images.purge',

            // Administration
            'admin.users.manage', 'admin.roles.manage',
            'admin.masters.manage', 'admin.parameters.manage',
            'admin.eod.run', 'admin.patch.apply',

            // BCP/DR
            'bcp.status', 'bcp.failover', 'bcp.drill.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $roles = [
            'admin' => $permissions,  // All permissions

            'supervisor' => [
                'outward.scan', 'outward.batch.create', 'outward.batch.close',
                'outward.batch.submit', 'outward.micr.read', 'outward.micr.correct',
                'outward.instrument.hold', 'outward.instrument.release',
                'inward.session.create', 'inward.session.submit',
                'inward.data_entry', 'inward.verify',
                'fraud.scan', 'fraud.alert.view', 'fraud.alert.resolve',
                'fraud.blacklist.view', 'returns.view', 'returns.process',
                'returns.sign', 'returns.memo.generate',
                'signature.sign_instrument', 'signature.sign_batch', 'signature.verify',
                'reports.view', 'reports.export', 'images.view', 'bcp.status',
            ],

            'branch_operator' => [
                'outward.scan', 'outward.batch.create', 'outward.batch.close',
                'outward.micr.read', 'outward.instrument.hold', 'outward.instrument.release',
                'inward.data_entry', 'images.view',
            ],

            'hub_operator' => [
                'outward.scan', 'outward.batch.create', 'outward.batch.close',
                'outward.batch.submit', 'outward.micr.read', 'outward.micr.correct',
                'outward.instrument.hold', 'outward.instrument.release',
                'inward.session.create', 'inward.data_entry', 'inward.verify',
                'images.view', 'reports.view',
            ],

            'inward_operator' => [
                'inward.session.create', 'inward.session.submit',
                'inward.data_entry', 'inward.verify',
                'inward.ocr.extract', 'images.view',
            ],

            'data_entry' => [
                'inward.data_entry', 'outward.micr.read', 'images.view',
            ],

            'checker' => [
                'outward.batch.submit', 'inward.verify',
                'returns.sign', 'signature.sign_batch',
            ],

            'fraud_officer' => [
                'fraud.scan', 'fraud.alert.view', 'fraud.alert.resolve',
                'fraud.blacklist.view', 'fraud.blacklist.manage',
                'fraud.positive_pay.check', 'fraud.positive_pay.register',
                'images.view', 'reports.view',
            ],

            'return_officer' => [
                'returns.view', 'returns.process', 'returns.represent', 'returns.sign',
                'returns.memo.generate', 'returns.memo.email',
                'signature.sign_instrument', 'images.view',
            ],

            'signing_officer' => [
                'signature.sign_instrument', 'signature.sign_file',
                'signature.sign_batch', 'signature.verify',
                'signature.certificates.view',
            ],

            'integration_officer' => [
                'integration.chi_dem.submit', 'integration.chi_dem.receive',
                'integration.npci.submit', 'integration.cbs.upload',
            ],

            'bcp_officer' => [
                'bcp.status', 'bcp.failover', 'bcp.drill.manage',
                'reports.view',
            ],

            'auditor' => [
                'reports.view', 'reports.export', 'reports.audit_trail',
                'images.view', 'fraud.alert.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePerms);
            $this->command->info("Role '{$roleName}' created with " . count($rolePerms) . " permissions.");
        }
    }
}
