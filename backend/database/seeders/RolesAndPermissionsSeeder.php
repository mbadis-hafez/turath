<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * @var array<int, array{code: string, ar: string, en: string, description_ar: string|null, description_en: string|null}>
     */
    private const ROLES = [
        ['code' => 'reader', 'ar' => 'قارئ', 'en' => 'Reader', 'description_ar' => 'زائر مسجّل بلا صلاحيات تحرير.', 'description_en' => 'Signed-in visitor with no editing abilities.'],
        ['code' => 'contributor', 'ar' => 'مساهم', 'en' => 'Contributor', 'description_ar' => 'يمكنه تقديم مواد ومقترحات تعديل للمراجعة.', 'description_en' => 'Can submit material and edit proposals for review.'],
        ['code' => 'verified_researcher', 'ar' => 'باحث موثّق', 'en' => 'Verified researcher', 'description_ar' => 'باحث موثّق بصلاحية اطلاع أوسع على الأرشيف.', 'description_en' => 'Verified researcher with expanded archive access.'],
        ['code' => 'institution', 'ar' => 'مؤسسة', 'en' => 'Institution', 'description_ar' => 'حساب مؤسسي بصلاحية اطلاع أوسع على الأرشيف.', 'description_en' => 'Institutional account with expanded archive access.'],
        ['code' => 'artist_claimed', 'ar' => 'فنان موثّق', 'en' => 'Claimed artist', 'description_ar' => 'فنان أثبت ملكية صفحته ويمكنه تعديلها.', 'description_en' => 'An artist who has claimed and can edit their own profile.'],
        ['code' => 'editor', 'ar' => 'محرر', 'en' => 'Editor', 'description_ar' => 'ينشئ ويحرر الفنانين والأعمال والمواد الأرشيفية والفعاليات.', 'description_en' => 'Creates and edits artists, artworks, archive material and events.'],
        ['code' => 'reviewer', 'ar' => 'مراجع', 'en' => 'Reviewer', 'description_ar' => 'يعمل على قوائم المراجعة ويحل تعارضات المصادر؛ لا يحرر أو ينشر السجلات.', 'description_en' => 'Works the review queues and resolves source conflicts; does not edit or publish records.'],
        ['code' => 'admin', 'ar' => 'مدير', 'en' => 'Admin', 'description_ar' => 'كل صلاحيات المحرر، إضافة إلى إدارة حسابات المستخدمين.', 'description_en' => 'Everything an editor can do, plus managing user accounts.'],
        ['code' => 'superadmin', 'ar' => 'مدير أعلى', 'en' => 'Superadmin', 'description_ar' => 'يملك جميع الصلاحيات في المنصة.', 'description_en' => 'Holds every permission in the platform.'],
    ];

    /**
     * @var array<int, array{name: string, label_ar: string, label_en: string, group: string}>
     */
    private const PERMISSIONS = [
        ['name' => 'activity.view', 'label_ar' => 'عرض سجل التغييرات', 'label_en' => 'View the activity log', 'group' => 'administration'],
        ['name' => 'artists.manage', 'label_ar' => 'إدارة الفنانين', 'label_en' => 'Create and edit artists', 'group' => 'artists'],
        ['name' => 'artists.verify', 'label_ar' => 'توثيق الفنانين', 'label_en' => 'Verify artist records', 'group' => 'artists'],
        ['name' => 'holders.manage', 'label_ar' => 'إدارة الحائزين', 'label_en' => 'Create and edit holders', 'group' => 'holders'],
        ['name' => 'artworks.manage', 'label_ar' => 'إدارة الأعمال الفنية', 'label_en' => 'Create and edit artworks', 'group' => 'artworks'],
        ['name' => 'events.manage', 'label_ar' => 'إدارة الفعاليات', 'label_en' => 'Create and edit events', 'group' => 'events'],
        ['name' => 'archive.manage', 'label_ar' => 'إدارة المواد الأرشيفية', 'label_en' => 'Create and edit archive material', 'group' => 'archive'],
        ['name' => 'archive.publish', 'label_ar' => 'نشر المواد الأرشيفية', 'label_en' => 'Publish archive material', 'group' => 'archive'],
        ['name' => 'artists.publish', 'label_ar' => 'نشر الفنانين', 'label_en' => 'Publish artists', 'group' => 'artists'],
        ['name' => 'artworks.publish', 'label_ar' => 'نشر الأعمال الفنية', 'label_en' => 'Publish artworks', 'group' => 'artworks'],
        ['name' => 'events.publish', 'label_ar' => 'نشر الفعاليات', 'label_en' => 'Publish events', 'group' => 'events'],
        ['name' => 'imports.manage', 'label_ar' => 'إدارة الاستيراد', 'label_en' => 'Run spreadsheet imports', 'group' => 'administration'],
        ['name' => 'dashboard.manage', 'label_ar' => 'إدارة لوحة المتابعة', 'label_en' => 'View any user\'s dashboard', 'group' => 'administration'],
        ['name' => 'source_conflicts.resolve', 'label_ar' => 'حل تعارضات المصادر', 'label_en' => 'Resolve source conflicts', 'group' => 'review'],
        ['name' => 'materials.review', 'label_ar' => 'مراجعة المواد المُقدَّمة', 'label_en' => 'Review submitted material', 'group' => 'review'],
        ['name' => 'review_queue.material_intake', 'label_ar' => 'قائمة مراجعة استلام المواد', 'label_en' => 'Work the material-intake review queue', 'group' => 'review'],
        ['name' => 'proposals.submit', 'label_ar' => 'تقديم مقترحات تعديل', 'label_en' => 'Submit edit proposals', 'group' => 'review'],
        ['name' => 'review_queue.editorial_review', 'label_ar' => 'قائمة المراجعة التحريرية', 'label_en' => 'Work the editorial review queue', 'group' => 'review'],
        ['name' => 'review_queue.archivist_review', 'label_ar' => 'قائمة مراجعة أمين الأرشيف', 'label_en' => 'Work the archivist review queue', 'group' => 'review'],
        ['name' => 'review_queue.data_audit', 'label_ar' => 'قائمة تدقيق البيانات', 'label_en' => 'Work the data-audit review queue', 'group' => 'review'],
        ['name' => 'review_queue.second_source_needed', 'label_ar' => 'قائمة مراجعة الحاجة لمصدر ثانٍ', 'label_en' => 'Work the second-source-needed review queue', 'group' => 'review'],
        ['name' => 'users.manage', 'label_ar' => 'إدارة المستخدمين', 'label_en' => 'Create and edit user accounts', 'group' => 'administration'],
        ['name' => 'roles.manage', 'label_ar' => 'إدارة الأدوار والصلاحيات', 'label_en' => 'Manage roles and their permissions', 'group' => 'administration'],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [];
        foreach (self::PERMISSIONS as $data) {
            $permissions[$data['name']] = Permission::findOrCreate($data['name']);
            $permissions[$data['name']]->forceFill([
                'label_ar' => $data['label_ar'],
                'label_en' => $data['label_en'],
                'group' => $data['group'],
            ])->save();
        }

        $roles = [];
        $isNewRole = [];
        foreach (self::ROLES as $data) {
            $roles[$data['code']] = Role::findOrCreate($data['code']);
            // A built-in role's permissions are administrator-managed once it
            // exists (RoleGuard allows editing them), the same as a custom
            // role's — so the baseline set below is only ever granted at the
            // moment the row is first created. Re-running this seeder against
            // an already-seeded database must not silently undo an
            // administrator's later permission changes to `editor`, `reviewer`,
            // etc. A future code change to a built-in role's baseline
            // permissions therefore only reaches *new* installations; an
            // already-deployed one needs the grant made through the UI.
            $isNewRole[$data['code']] = $roles[$data['code']]->wasRecentlyCreated;
            $roles[$data['code']]->forceFill([
                'name_ar' => $data['ar'],
                'name_en' => $data['en'],
                'description_ar' => $data['description_ar'],
                'description_en' => $data['description_en'],
                'is_built_in' => true,
            ])->save();
        }

        // Editor creates, edits, deletes and submits content for review — it never
        // reviews, approves, or publishes anything (that's Reviewer's/Admin's job).
        $editorPermissions = [
            $permissions['activity.view'], $permissions['artists.manage'], $permissions['artists.verify'],
            $permissions['holders.manage'], $permissions['artworks.manage'], $permissions['events.manage'],
            $permissions['archive.manage'], $permissions['imports.manage'],
            $permissions['dashboard.manage'], $permissions['proposals.submit'],
        ];

        // Reviewer works the review queues and resolves conflicts, on top of
        // everything an Editor can do (Reviewer now holds the full editor
        // permission set too, granted separately below). archive.publish is
        // additionally granted here: publishing archive material is
        // reviewer-or-admin work, not admin-exclusive like the other three.
        $reviewerPermissions = [
            $permissions['activity.view'], $permissions['source_conflicts.resolve'], $permissions['materials.review'],
            $permissions['review_queue.archivist_review'], $permissions['review_queue.data_audit'],
            $permissions['review_queue.second_source_needed'], $permissions['review_queue.editorial_review'],
            $permissions['review_queue.material_intake'], $permissions['archive.publish'],
        ];

        if ($isNewRole['contributor']) {
            $roles['contributor']->givePermissionTo($permissions['proposals.submit']);
        }
        if ($isNewRole['editor']) {
            $roles['editor']->givePermissionTo($editorPermissions);
        }
        if ($isNewRole['reviewer']) {
            $roles['reviewer']->givePermissionTo($editorPermissions);
            $roles['reviewer']->givePermissionTo($reviewerPermissions);
        }
        if ($isNewRole['admin']) {
            $roles['admin']->givePermissionTo($editorPermissions);
            $roles['admin']->givePermissionTo($reviewerPermissions);
            $roles['admin']->givePermissionTo([
                $permissions['users.manage'], $permissions['roles.manage'],
                $permissions['artists.publish'], $permissions['artworks.publish'], $permissions['events.publish'],
            ]);
        }

        // superadmin is the one role that stays fully protected (RoleGuard),
        // so unlike every other built-in role it is still re-synced on every
        // run: every permission that exists, so the API reports them all to
        // the interface, and a permission added later reaches it automatically.
        $superadmin = $roles['superadmin'];
        $superadmin->syncPermissions(Permission::where('guard_name', $superadmin->guard_name)->get());

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
