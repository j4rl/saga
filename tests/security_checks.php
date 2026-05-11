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
    'theme_bg' => '#f6f7f9',
    'theme_surface' => '#ffffff',
    'theme_text' => '#20242a',
]);
check(str_contains($themeCss, ':root[data-theme="light"],:root[data-theme="auto"]'), 'Skolans ljusa tema ska vara avgransat till ljust/auto-ljust lage.');
check(str_contains($themeCss, ':root[data-theme="dark"]'), 'Skolans tema ska ha regler for morkt lage.');
check(str_contains($themeCss, '--bg: #f6f7f9'), 'Skolans bakgrundsfarg ska appliceras i ljust CSS-lage.');
check(str_contains($themeCss, '--surface: #ffffff'), 'Skolans ytfarg ska appliceras i ljust CSS-lage.');
check(str_contains($themeCss, '--text: #20242a'), 'Skolans textfarg ska appliceras i ljust CSS-lage.');

$contrastErrors = validate_school_theme_colors([
    'theme_primary' => '#235b4e',
    'theme_secondary' => '#cccccc',
    'theme_bg' => '#ffffff',
    'theme_surface' => '#ffffff',
    'theme_text' => '#eeeeee',
]);
check($contrastErrors !== [], 'Skolans tema ska neka olasbara kontraster.');

echo "security checks ok\n";
