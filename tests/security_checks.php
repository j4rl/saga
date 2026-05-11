<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/projects.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

$teacher = [
    'id' => 10,
    'role' => 'teacher',
    'school_id' => 1,
    'full_name' => 'Anna Larare',
];

$ownDraft = [
    'is_public' => 0,
    'school_id' => 1,
    'is_submitted' => 0,
    'is_approved' => 0,
    'supervisor_user_id' => 10,
    'supervisor' => 'Anna Larare',
    'user_id' => 20,
];

$otherDraft = $ownDraft;
$otherDraft['supervisor_user_id'] = 11;
$otherDraft['supervisor'] = 'Erik Larare';

$submittedByOtherTeacher = $otherDraft;
$submittedByOtherTeacher['is_submitted'] = 1;

$publicDraft = $otherDraft;
$publicDraft['is_public'] = 1;

$publicProject = $publicDraft;
$publicProject['is_submitted'] = 1;

$approvedPublicProject = $publicProject;
$approvedPublicProject['is_approved'] = 1;

check(can_view_project($ownDraft, $teacher) === true, 'Handledare ska kunna se eget utkast.');
check(can_view_project($otherDraft, $teacher) === false, 'Larare ska inte kunna se andra larares elevutkast.');
check(can_view_project($submittedByOtherTeacher, $teacher) === true, 'Larare ska kunna se slutligt inlamnade arbeten pa egen skola.');
check(can_view_project($publicDraft, null) === false, 'Publika utkast ska inte kunna visas utan inloggning.');
check(can_view_project($publicProject, null) === false, 'Publika arbeten utan godkannande ska inte kunna visas utan inloggning.');
check(can_view_project($approvedPublicProject, null) === true, 'Publika godkanda arbeten ska kunna visas utan inloggning.');
check(can_unlock_project_submission($submittedByOtherTeacher, $teacher) === false, 'Larare ska inte kunna lasa upp andras inlamningar.');

$ownSubmitted = $ownDraft;
$ownSubmitted['is_submitted'] = 1;
check(can_unlock_project_submission($ownSubmitted, $teacher) === true, 'Handledare ska kunna lasa upp egen elevs inlamning.');
check(can_approve_project($ownSubmitted, $teacher) === true, 'Handledare ska kunna godkanna egen elevs inlamning.');
check(can_approve_project($ownDraft, $teacher) === false, 'Handledare ska inte kunna godkanna utkast.');
check(can_approve_project($submittedByOtherTeacher, $teacher) === false, 'Larare ska inte kunna godkanna andra handledares inlamningar.');
check(project_is_publicly_visible($publicProject) === false, 'Publikt markerade arbeten utan godkannande ska inte vara publikt synliga.');
check(project_is_publicly_visible($approvedPublicProject) === true, 'Godkanda publika inlamningar ska vara publikt synliga.');

$student = [
    'id' => 20,
    'role' => 'student',
    'school_id' => 1,
    'full_name' => 'Exempel Elev',
];
check(can_view_project($ownDraft, $student) === true, 'Elev ska kunna se eget arbete.');
check(can_edit_project_content($ownDraft, $student) === true, 'Elev ska kunna redigera eget utkast.');
check(can_edit_project_content($ownSubmitted, $student) === false, 'Elev ska inte kunna redigera slutligt inlamnat arbete.');

$otherStudent = $student;
$otherStudent['id'] = 21;
check(can_view_project($ownDraft, $otherStudent) === false, 'Elev ska inte kunna se annan elevs privata arbete.');
check(can_edit_project_content($ownDraft, $otherStudent) === false, 'Elev ska inte kunna redigera annan elevs arbete.');

$loginSource = file_get_contents(__DIR__ . '/../login.php') ?: '';
check(!str_contains($loginSource, 'admin/admin123'), 'Inloggningssidan ska inte visa testkonton.');

$functionsSource = file_get_contents(__DIR__ . '/../includes/functions.php') ?: '';
check(str_contains($functionsSource, 'password_reset_attempts'), 'Tabellprefix ska omfatta password_reset_attempts.');
check(
    str_contains($functionsSource, 'UPDATE projects SET category_id = ? WHERE category_id = ?'),
    'Kategorisammanslagning ska flytta alla arbeten fran gammal till ny kategori.'
);
check(
    !str_contains($functionsSource, 'UPDATE projects SET category_id = ? WHERE category_id = ? AND is_submitted'),
    'Kategorisammanslagning ska inte begransas bort fran inlamnade arbeten.'
);

$categoriesSource = file_get_contents(__DIR__ . '/../categories.php') ?: '';
check(str_contains($categoriesSource, 'name="action" value="create"'), 'Superadmin ska kunna skapa kategorier.');
check(str_contains($categoriesSource, 'name="action" value="delete"'), 'Superadmin ska kunna ta bort tomma kategorier.');

$projectsSource = file_get_contents(__DIR__ . '/../includes/projects.php') ?: '';
check(str_contains($projectsSource, 'function can_approve_project_for_teacher'), 'Larare ska kunna godkanna via handledarroll eller kategoriansvar.');
check(str_contains($projectsSource, 'function fetch_category_approver_teacher'), 'Kategori-godkannande ska valja en ansvarig larare.');
check(str_contains($projectsSource, 'ORDER BY category_project_count DESC, u.id ASC'), 'Kategori-godkannande ska ga till lararen med flest arbeten och deterministisk tie-break.');
check(str_contains($projectsSource, 'p.category_id = ?'), 'Kategori-godkannande ska matcha projektkategori.');

$projectEditSource = file_get_contents(__DIR__ . '/../project_edit.php') ?: '';
check(str_contains($projectEditSource, 'supervisor_name_manual'), 'Elever ska kunna ange tidigare handledares namn manuellt.');

$authSource = file_get_contents(__DIR__ . '/../includes/auth.php') ?: '';
check(str_contains($authSource, 'function password_reset_is_rate_limited'), 'Losenordsaterstallning ska ha separat rate limiting.');
check(str_contains($authSource, 'password_reset_record_request($conn, $identifier);'), 'Losenordsaterstallning ska registrera forfragan innan anvandaruppslag ger effekt.');
check(str_contains($authSource, 'password_reset_rate_limited'), 'Rate limit for losenordsaterstallning ska loggas neutralt.');

$configSource = file_get_contents(__DIR__ . '/../config/app.php') ?: '';
check(str_contains($configSource, 'SAGA_APP_BASE_URL'), 'APP_BASE_URL ska kunna sattas via miljo.');

$installerSource = file_get_contents(__DIR__ . '/../includes/installer.php') ?: '';
check(str_contains($installerSource, "define('APP_BASE_URL'"), 'Installerad config ska kunna skriva APP_BASE_URL.');
check(str_contains($installerSource, 'app_base_url'), 'Installeraren ska ha falt for APP_BASE_URL.');

$_SERVER['HTTP_HOST'] = "evil.test\r\nBcc: attacker@example.com";
check(safe_request_host() === 'localhost', 'HTTP_HOST med radbrytning ska inte anvandas i lankar eller headers.');
check(mail_from_domain() === 'localhost', 'E-postavsandare ska falla tillbaka vid ogiltig Host-header.');

$_SERVER['HTTP_HOST'] = 'saga.example:8080';
check(safe_request_host() === 'saga.example:8080', 'Giltig Host-header med port ska accepteras.');
check(mail_from_domain() === 'saga.example', 'E-postavsandare ska ta bort port fran Host-header.');

$themeCss = school_theme_css_vars([
    'theme_custom_enabled' => 1,
    'theme_primary' => '#235b4e',
    'theme_secondary' => '#24527a',
]);
$derivedTheme = derive_school_theme_colors('#235b4e', '#24527a');
check(str_contains($themeCss, ':root[data-theme="light"],:root[data-theme="auto"]'), 'Skolans ljusa tema ska vara avgransat till ljust/auto-ljust lage.');
check(str_contains($themeCss, ':root[data-theme="dark"]'), 'Skolans tema ska ha regler for morkt lage.');
check(str_contains($themeCss, '--bg: ' . $derivedTheme['light']['theme_bg']), 'Skolans bakgrundsfarg ska raknas fram i ljust CSS-lage.');
check(str_contains($themeCss, '--surface: ' . $derivedTheme['light']['theme_surface']), 'Skolans ytfarg ska raknas fram i ljust CSS-lage.');
check(str_contains($themeCss, '--text: ' . $derivedTheme['light']['theme_text']), 'Skolans textfarg ska raknas fram i ljust CSS-lage.');
check(str_contains($themeCss, '--bg: ' . $derivedTheme['dark']['theme_bg']), 'Skolans bakgrundsfarg ska raknas fram i morkt CSS-lage.');

$themeErrors = validate_school_theme_colors([
    'theme_primary' => '#ffffff',
    'theme_secondary' => '#eeeeee',
]);
check($themeErrors === [], 'Skolans tema ska justera svara fargval till lasbara beraknade paletter.');

$invalidThemeErrors = validate_school_theme_colors([
    'theme_primary' => '#12345',
    'theme_secondary' => '#24527a',
]);
check($invalidThemeErrors !== [], 'Skolans tema ska neka ogiltiga fargformat.');

echo "security checks ok\n";
