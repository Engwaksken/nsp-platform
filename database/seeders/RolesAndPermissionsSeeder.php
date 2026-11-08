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
        'manage-experts' => 'Verify experts and assign mentors to startups',
        'manage-talent' => 'See all vacancies and take down unsuitable ones',
        'moderate-marketplace' => 'Handle marketplace reports and disputes, and take listings down',
        'manage-communications' => 'Send announcements and see the email delivery log',
        'view-insights' => 'See ecosystem KPIs and download aggregate reports',
        'view-system-health' => 'See system health (scheduler, queue, email, backups, disk)',
        // Owner-alignment modules
        'manage-organizations' => 'Create organizations (universities, ESOs, investors, partners, corporates) and manage their members',
        'manage-builder-curricula' => 'Edit draft Venture Builder curricula (modules, steps, fields, checklists)',
        'publish-builder-curricula' => 'Publish a Venture Builder curriculum version for founders',
        'review-builder-steps' => 'Review submitted Venture Builder steps, request changes and validate them',
        'manage-challenges' => 'Create and edit challenges in the Challenge Exchange',
        'review-challenges' => 'Review, approve, reject and publish challenges',
        'manage-knowledge' => 'Upload and edit Knowledge Hub resources (drafts)',
        'publish-knowledge' => 'Review and publish Knowledge Hub resources',
        'manage-intelligence' => 'Edit recommendation rules and document templates; see guidance feedback',
        'manage-payments' => 'See the payment ledger, refunds and disputes for expert bookings',
    ];

    public const ROLES = [
        'super_admin' => [], // passes every check through Gate::before
        'platform_admin' => ['*'],
        'ecosystem_manager' => ['manage-pages', 'manage-posts', 'publish-content', 'manage-media', 'manage-menus',
            'manage-site-content', 'manage-redirects', 'manage-contact-messages', 'manage-startups', 'review-duplicates', 'edit-vgi-frameworks', 'review-assessments', 'manage-opportunities', 'verify-investors', 'manage-experts', 'manage-talent', 'moderate-marketplace', 'manage-communications', 'view-insights',
            'manage-organizations', 'manage-builder-curricula', 'review-builder-steps', 'manage-challenges', 'review-challenges', 'manage-knowledge', 'publish-knowledge', 'manage-intelligence'],
        'content_editor' => ['manage-pages', 'manage-posts', 'manage-media', 'manage-menus', 'manage-site-content', 'manage-challenges', 'manage-knowledge'],
        'support' => ['manage-contact-messages'],
        'auditor' => ['view-audit-log', 'view-insights', 'view-system-health'],
        // Validates Venture Builder steps for startups they are assigned to (mentors, programme staff).
        'builder_reviewer' => ['review-builder-steps'],
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
