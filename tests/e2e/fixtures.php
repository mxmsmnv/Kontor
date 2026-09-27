<?php

declare(strict_types=1);

use Kontor\Contacts\Domain\Contact;
use Kontor\CRM\Domain\Lead;
use Kontor\CRM\Domain\Pipeline;
use Kontor\CRM\Domain\Stage;

$site = rtrim((string) getenv('KONTOR_E2E_SITE'), '/');
$stateFile = (string) getenv('KONTOR_E2E_STATE');
$editorPassword = (string) getenv('KONTOR_E2E_EDITOR_PASS');
$restrictedPassword = (string) getenv('KONTOR_E2E_RESTRICTED_PASS');
if ($site === '' || $stateFile === '' || $editorPassword === '' || $restrictedPassword === '') {
    fwrite(STDERR, "Set KONTOR_E2E_SITE, KONTOR_E2E_STATE and both test-user password variables.\n");
    exit(2);
}
if (!is_file($site . '/index.php')) {
    throw new RuntimeException('KONTOR_E2E_SITE is not a ProcessWire installation.');
}

chdir($site);
ob_start();
require $site . '/index.php';
ob_end_clean();

$requiredModules = ['KontorContacts', 'KontorCRM', 'KontorCRMIntake'];
foreach ($requiredModules as $moduleName) {
    if (!$modules->isInstalled($moduleName)) {
        throw new RuntimeException("Install {$moduleName} before preparing browser fixtures.");
    }
}

$configureRole = static function (string $roleName, array $permissionNames) use ($roles, $permissions): object {
    $role = $roles->get($roleName);
    if (!$role->id) {
        $role = $roles->add($roleName);
    }
    foreach ($permissions as $permission) {
        if ($role->hasPermission($permission)) {
            $role->removePermission($permission);
        }
    }
    foreach ($permissionNames as $permissionName) {
        $permission = $permissions->get($permissionName);
        if (!$permission->id) {
            throw new RuntimeException("Missing permission {$permissionName}.");
        }
        $role->addPermission($permission);
    }
    $role->save();

    return $role;
};

$configureUser = static function (string $name, string $password, object $role) use ($users): object {
    $user = $users->get($name);
    if (!$user->id) {
        $user = $users->add($name);
    }
    $user->email = $name . '@example.test';
    $user->pass = $password;
    $user->roles->removeAll();
    $user->addRole($role);
    $user->save();

    return $user;
};

$editorPermissions = [
    'page-view', 'kontor-access',
    'kontor-contacts-contact-view', 'kontor-contacts-contact-create', 'kontor-contacts-contact-edit',
    'kontor-crm-lead-view', 'kontor-crm-lead-create', 'kontor-crm-lead-edit', 'kontor-crm-lead-convert',
    'kontor-crm-deal-view', 'kontor-crm-deal-create',
];
$restrictedPermissions = [
    'page-view', 'kontor-access', 'kontor-contacts-contact-view',
    'kontor-crm-lead-view', 'kontor-crm-lead-edit',
    'kontor-crm-deal-view', 'kontor-crm-deal-create',
];
$editor = $configureUser(
    'kontor-e2e-crm-editor',
    $editorPassword,
    $configureRole('kontor-e2e-crm-editor-role', $editorPermissions),
);
$restrictedRole = $configureRole('kontor-e2e-crm-restricted-role', $restrictedPermissions);
$restrictedUsers = [];
foreach (['chromium-desktop', 'chromium-mobile', 'firefox-desktop', 'webkit-mobile'] as $project) {
    $restrictedUsers[$project] = $configureUser(
        'kontor-e2e-crm-restricted-' . $project,
        $restrictedPassword,
        $restrictedRole,
    );
}

// This is a dedicated local acceptance site. Clear only the named fixture
// users' accumulated login throttle state so repeated browser matrices remain
// deterministic without weakening the application's production defaults.
$throttle = $database->pdo()->prepare('DELETE FROM session_login_throttle WHERE name = :name');
foreach ([$editor, ...array_values($restrictedUsers)] as $fixtureUser) {
    $throttle->execute(['name' => (string) $fixtureUser->name]);
}

$organizationUid = (string) $database->pdo()
    ->query('SELECT uid FROM kontor_organizations ORDER BY id LIMIT 1')
    ->fetchColumn();
if ($organizationUid === '') {
    throw new RuntimeException('The browser site has no Kontor organization.');
}

/** @var ProcessWire\KontorCRMIntake $intake */
$intake = $modules->get('KontorCRMIntake');
$intake->service()->configureDefault($organizationUid, 'E2E Qualification', [
    [
        'key' => 'source_channel',
        'label' => 'Source channel',
        'type' => 'select',
        'targets' => ['lead', 'deal'],
        'options' => ['referral' => 'Referral', 'website' => 'Website'],
        'required' => true,
        'binding' => 'source',
    ],
    [
        'key' => 'budget_context',
        'label' => 'Budget context',
        'type' => 'textarea',
        'targets' => ['lead', 'deal'],
        'required' => false,
    ],
]);

/** @var ProcessWire\KontorCRM $crm */
$crm = $modules->get('KontorCRM');
$pipeline = $crm->pipelineRepository()->defaultForEntityType($organizationUid, 'deal');
if ($pipeline === null) {
    $pipeline = Pipeline::create($organizationUid, 'E2E Sales', 'deal', true);
    $crm->pipelineRepository()->save($pipeline);
}
if ($crm->stageRepository()->firstOpenStage($pipeline->uid->toString()) === null) {
    $crm->stageRepository()->save(Stage::create(
        $pipeline->uid->toString(),
        'qualified',
        ['en' => 'Qualified'],
        probability: 25,
        sortOrder: 10,
    ));
}

/** @var ProcessWire\KontorContacts $contacts */
$contacts = $modules->get('KontorContacts');
$contact = Contact::create(
    $organizationUid,
    'Restricted',
    null,
    'Fixture',
    'E2E Restricted Fixture',
    email: 'kontor-e2e-restricted@example.test',
);
$contacts->contactRepository()->save($contact);
$lead = Lead::create(
    $organizationUid,
    'E2E Restricted Conversion Check',
    contactUid: $contact->uid->toString(),
    source: 'referral',
);
$lead->status = 'qualified';
$crm->leadRepository()->save($lead);

$state = [
    'schema_version' => 1,
    'prepared_at' => gmdate(DATE_ATOM),
    'editor_user' => (string) $editor->name,
    'restricted_users' => array_map(
        static fn (object $user): string => (string) $user->name,
        $restrictedUsers,
    ),
    'restricted_lead_uid' => $lead->uid->toString(),
];
if (file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
    throw new RuntimeException('Could not write the browser fixture state.');
}

echo json_encode(['ready' => true] + $state, JSON_UNESCAPED_SLASHES) . "\n";
