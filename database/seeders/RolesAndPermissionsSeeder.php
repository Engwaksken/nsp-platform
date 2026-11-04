<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Platform roles from spec §3.3. Startup membership roles (owner, founder,
 * admin, team_member, viewer) belong to the registry and are not platform roles.
 * Safe to run more than once.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        'manage-pages' => 'Create and edit pages (drafts)',
        'manage-posts' => 'Create and edit news posts (drafts)',
        'publish-content' => 'Publish, unpublish and edit live pages and posts',
        'manage-media' => 'Upload and delete media',
        'manage-menus' => 'Edit site menus',
        'manage-site-content' => 'Edit FAQs, testimonials and partners',
        'manage-redirects' => 'Manage SEO redirects',
        'manage-contact-messages' => 'Read and handle contact form messages',
        'manage-settings' => 'Change branding and site settings',
        'manage-users' => 'Create users and assign roles',
        'manage-feature-flags' => 'Switch features on and off',
        'view-audit-log' => 'View the audit and login logs',
        'manage-startups' => 'View and manage registered startups',
        'review-duplicates' => 'Review possible duplicate startups and merge them',
        'edit-vgi-frameworks' => 'Edit draft growth-assessment rubrics',
        'approve-vgi-frameworks' => 'Approve a growth-assessment rubric version for founders',
        'review-assessments' => 'See submitted startup assessments and adjust scores with a reason',
        'manage-opportunities' => 'Publish and moderate opportunities and events',
        'manage-integrations' => 'See and retry deliveries to connected systems (IMS)',
        'verify-investors' => 'Verify investors before they can see startups',
    ];

    public const ROLES = [
        'super_admin' => [], // passes every check through Gate::before
        'platform_admin' => ['*'],
        'ecosystem_manager' => ['manage-pages', 'manage-posts', 'publish-content', 'manage-media', 'manage-menus',
            'manage-site-content', 'manage-redirects', 'manage-contact-messages', 'manage-startups', 'review-duplicates', 'edit-vgi-frameworks', 'review-assessments', 'manage-opportunities', 'verify-investors'],
        'content_editor' => ['manage-pages', 'manage-posts', 'manage-media', 'manage-menus', 'manage-site-content'],
        'support' => ['manage-contact-messages'],
        'auditor' => ['view-audit-log'],
        'investor' => [],
        'expert' => [],
        'consultant' => [],
        'recruiter' => [],
        'startup_user' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(self::PERMISSIONS) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        foreach (self::ROLES as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions === ['*'] ? array_keys(self::PERMISSIONS) : $permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
