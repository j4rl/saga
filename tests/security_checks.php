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

echo "security checks ok\n";
