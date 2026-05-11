<?php
declare(strict_types=1);

function handle_project_submission(mysqli $conn, array $user, ?array $existingProject): array
{
    verify_csrf();
    ensure_privacy_consent_columns($conn);

    $canEditContent = $existingProject
        ? can_edit_project_content($existingProject, $user)
        : $user['role'] === 'student';
    $canUnlockSubmission = $existingProject ? can_unlock_project_submission($existingProject, $user) : false;

    if (!$canEditContent && !$canUnlockSubmission) {
        return [
            'ok' => false,
            'errors' => ['Du har inte behörighet att ändra arbetet.'],
        ];
    }

    if ($existingProject && !$canEditContent && $canUnlockSubmission) {
        $projectId = (int) $existingProject['id'];
        $isSubmitted = isset($_POST['is_submitted']) ? 1 : 0;

        if ($isSubmitted === 0) {
            execute_prepared(
                $conn,
                'UPDATE projects
                 SET is_public = 0, is_submitted = 0, is_approved = 0,
                     submitted_at = NULL, approved_at = NULL, approved_by = NULL, updated_at = NOW()
                 WHERE id = ? AND is_submitted = 1',
                'i',
                [$projectId]
            );
            log_event($conn, (int) $user['id'], 'project_unlock_submission', 'project', $projectId);
        }

        return ['ok' => true, 'project_id' => $projectId, 'unlocked' => $isSubmitted === 0];
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
    $supervisorUserId = (int) ($_POST['supervisor_user_id'] ?? 0);
    $manualSupervisorName = trim((string) ($_POST['supervisor_name_manual'] ?? ''));
    $categoryName = normalize_category_name((string) ($_POST['category_name'] ?? ''));
    $abstractText = trim((string) ($_POST['abstract_text'] ?? ''));
    $summaryText = trim((string) ($_POST['summary_text'] ?? ''));
    $requestedPublic = isset($_POST['is_public']) ? 1 : 0;
    $isSubmitted = isset($_POST['is_submitted']) ? 1 : 0;

    $errors = [];
    $teacher = null;
    $category = null;
    $projectSchoolId = (int) ($existingProject['school_id'] ?? $user['school_id']);
    $projectOwnerId = (int) ($existingProject['user_id'] ?? $user['id']);
    $studentName = (string) ($existingProject['student_name'] ?? $user['full_name']);
    $canManagePublication = $user['role'] === 'student' && $projectOwnerId === (int) $user['id'];
    $wasSubmitted = $existingProject && (int) $existingProject['is_submitted'] === 1;
    $isPublic = $canManagePublication ? $requestedPublic : (int) ($existingProject['is_public'] ?? 0);
    if ($isSubmitted === 0) {
        $isPublic = 0;
    }

    if ($title === '' || mb_strlen($title) > 180) {
        $errors[] = 'Titel är obligatorisk och får vara högst 180 tecken.';
    }

    if (mb_strlen($subtitle) > 180) {
        $errors[] = 'Undertiteln får vara högst 180 tecken.';
    }

    if ($supervisorUserId <= 0) {
        if ($manualSupervisorName === '' || mb_strlen($manualSupervisorName, 'UTF-8') > 120) {
            $errors[] = 'Ange handledarens namn, högst 120 tecken, om handledaren inte längre finns som aktiv lärare.';
        }
    } else {
        $teacher = fetch_school_teacher($conn, $supervisorUserId, $projectSchoolId);
        if (!$teacher) {
            $errors[] = 'Handledaren måste vara en godkänd lärare på din skola.';
        }
    }

    if ($categoryName === '' || mb_strlen($categoryName) > 120) {
        $errors[] = 'Kategori är obligatorisk och får vara högst 120 tecken.';
    } else {
        $category = find_or_create_project_category($conn, $categoryName);
        if (!$category) {
            $errors[] = 'Kategorin kunde inte sparas.';
        }
    }

    if ($abstractText === '') {
        $errors[] = 'Abstract är obligatoriskt.';
    }

    if ($summaryText === '') {
        $errors[] = 'Sammanfattning är obligatorisk.';
    }

    if ($isSubmitted === 1 && !$wasSubmitted && empty($_POST['confirm_submission'])) {
        $errors[] = 'Bekräfta att du vill lämna in arbetet slutgiltigt.';
    }

    if ($canManagePublication && $requestedPublic === 1 && $isSubmitted === 0) {
        $errors[] = 'Arbetet kan bara göras publikt när slutlig inlämning är ikryssad.';
    }
    if ($canManagePublication && $requestedPublic === 1 && empty($_POST['confirm_publication_consent'])) {
        $errors[] = 'Du behöver samtycka till att arbetet och ditt namn blir sökbart innan du publicerar arbetet.';
    }

    $upload = validate_pdf_upload($_FILES['pdf_file'] ?? [], $existingProject === null);
    if (!$upload['ok']) {
        $errors[] = $upload['error'];
    }

    $schoolProfile = fetch_school_profile($conn, $projectSchoolId);
    $requiresPdfForSubmission = (int) ($schoolProfile['require_pdf_for_submission'] ?? 0) === 1;
    $hasExistingPdf = $existingProject && !empty($existingProject['pdf_filename']);
    $hasNewPdf = !empty($upload['file']);
    if ($isSubmitted === 1 && $requiresPdfForSubmission && !$hasExistingPdf && !$hasNewPdf) {
        $errors[] = 'Skolans regel kräver att en PDF är uppladdad innan slutlig inlämning.';
    }

    if ($errors) {
        return [
            'ok' => false,
            'errors' => $errors,
            'data' => compact('title', 'subtitle', 'supervisorUserId', 'manualSupervisorName', 'categoryName', 'abstractText', 'summaryText', 'isPublic', 'isSubmitted'),
        ];
    }

    $storedFile = null;

    try {
        if ($upload['file']) {
            $storedFile = store_uploaded_pdf($upload['file']);
        }

        $conn->begin_transaction();

        $submittedAt = null;
        if ($isSubmitted === 1) {
            $submittedAt = $existingProject
                && (int) $existingProject['is_submitted'] === 1
                && !empty($existingProject['submitted_at'])
                    ? (string) $existingProject['submitted_at']
                    : date('Y-m-d H:i:s');
        }
        $publicationConsentAt = null;
        $publicationConsentVersion = null;
        if ($isPublic === 1) {
            $publicationConsentAt = $existingProject
                && (int) ($existingProject['is_public'] ?? 0) === 1
                && !empty($existingProject['publication_consent_at'] ?? null)
                    ? (string) $existingProject['publication_consent_at']
                    : date('Y-m-d H:i:s');
            $publicationConsentVersion = publication_consent_version();
        }
        $supervisorName = $teacher ? (string) $teacher['full_name'] : $manualSupervisorName;
        $storedSupervisorUserId = $teacher ? $supervisorUserId : null;
        $categoryId = (int) $category['id'];

        if ($existingProject) {
            $projectId = (int) $existingProject['id'];

            if ($storedFile) {
                execute_prepared(
                    $conn,
                    'UPDATE projects
                     SET title = ?, subtitle = ?, category_id = ?, supervisor = ?, supervisor_user_id = ?,
                         abstract_text = ?, summary_text = ?, pdf_filename = ?, pdf_original_name = ?,
                         is_public = ?, is_submitted = ?, submitted_at = ?,
                         publication_consent_at = ?, publication_consent_version = ?, updated_at = NOW()
                     WHERE id = ?',
                    'ssisissssiisssi',
                    [
                        $title,
                        $subtitle,
                        $categoryId,
                        $supervisorName,
                        $storedSupervisorUserId,
                        $abstractText,
                        $summaryText,
                        $storedFile['stored_name'],
                        $storedFile['original_name'],
                        $isPublic,
                        $isSubmitted,
                        $submittedAt,
                        $publicationConsentAt,
                        $publicationConsentVersion,
                        $projectId,
                    ]
                );

                execute_prepared(
                    $conn,
                    'INSERT INTO upload_versions (project_id, stored_filename, original_name, uploaded_by)
                     VALUES (?, ?, ?, ?)',
                    'issi',
                    [$projectId, $storedFile['stored_name'], $storedFile['original_name'], (int) $user['id']]
                );
            } else {
                execute_prepared(
                    $conn,
                    'UPDATE projects
                     SET title = ?, subtitle = ?, category_id = ?, supervisor = ?, supervisor_user_id = ?,
                         abstract_text = ?, summary_text = ?, is_public = ?, is_submitted = ?,
                         submitted_at = ?, publication_consent_at = ?, publication_consent_version = ?,
                         updated_at = NOW()
                     WHERE id = ?',
                    'ssisissiisssi',
                    [
                        $title,
                        $subtitle,
                        $categoryId,
                        $supervisorName,
                        $storedSupervisorUserId,
                        $abstractText,
                        $summaryText,
                        $isPublic,
                        $isSubmitted,
                        $submittedAt,
                        $publicationConsentAt,
                        $publicationConsentVersion,
                        $projectId,
                    ]
                );
            }

            if ($isSubmitted === 0) {
                execute_prepared(
                    $conn,
                    'UPDATE projects
                     SET is_approved = 0, approved_at = NULL, approved_by = NULL
                     WHERE id = ?',
                    'i',
                    [$projectId]
                );
            }

            log_event($conn, (int) $user['id'], 'project_update', 'project', $projectId);
        } else {
            $pdfFilename = $storedFile['stored_name'] ?? null;
            $pdfOriginalName = $storedFile['original_name'] ?? null;

            $stmt = execute_prepared(
                $conn,
                'INSERT INTO projects
                 (user_id, school_id, category_id, title, subtitle, supervisor, supervisor_user_id,
                  abstract_text, summary_text, pdf_filename, pdf_original_name, is_public, is_submitted, submitted_at,
                  publication_consent_at, publication_consent_version)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'iiisssissssiisss',
                [
                    $projectOwnerId,
                    $projectSchoolId,
                    $categoryId,
                    $title,
                    $subtitle,
                    $supervisorName,
                    $storedSupervisorUserId,
                    $abstractText,
                    $summaryText,
                    $pdfFilename,
                    $pdfOriginalName,
                    $isPublic,
                    $isSubmitted,
                    $submittedAt,
                    $publicationConsentAt,
                    $publicationConsentVersion,
                ]
            );

            $projectId = (int) $stmt->insert_id;

            execute_prepared(
                $conn,
                'INSERT INTO upload_versions (project_id, stored_filename, original_name, uploaded_by)
                 VALUES (?, ?, ?, ?)',
                'issi',
                [$projectId, $pdfFilename, $pdfOriginalName, (int) $user['id']]
            );

            log_event($conn, (int) $user['id'], 'project_create', 'project', $projectId);
        }

        $conn->commit();

        if ($isSubmitted === 1 && !$wasSubmitted) {
            if ($teacher) {
                $teacherUser = fetch_one_prepared($conn, 'SELECT email FROM users WHERE id = ? LIMIT 1', 'i', [(int) $teacher['id']]);
                if ($teacherUser && !empty($teacherUser['email'])) {
                    send_email_notification(
                        $conn,
                        (string) $teacherUser['email'],
                        'Gymnasiearbete slutgiltigt inlämnat',
                        $studentName . ' har lämnat in "' . $title . '" slutgiltigt i SAGA.'
                    );
                }
            } else {
                $approverTeacher = fetch_category_approver_teacher($conn, $projectSchoolId, $categoryId, $projectId);
                if ($approverTeacher && !empty($approverTeacher['email'])) {
                    send_email_notification(
                        $conn,
                        (string) $approverTeacher['email'],
                        'Gymnasiearbete väntar på kategorigodkännande',
                        $studentName . ' har lämnat in "' . $title . '" med tidigare handledare ' . $supervisorName . '. Arbetet har lagts på dig eftersom du har flest handledda arbeten i samma kategori.'
                    );
                }
            }
        }

        return ['ok' => true, 'project_id' => $projectId];
    } catch (Throwable $exception) {
        $conn->rollback();

        if ($storedFile) {
            $path = UPLOAD_DIR . DIRECTORY_SEPARATOR . $storedFile['stored_name'];
            if (is_file($path)) {
                unlink($path);
            }
        }

        return [
            'ok' => false,
            'errors' => ['Kunde inte spara arbetet. Kontrollera uppgifterna och försök igen.'],
        ];
    }
}
