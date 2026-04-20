<?php
declare(strict_types=1);

function handle_project_submission(mysqli $conn, array $user, ?array $existingProject): array
{
    verify_csrf();

    $title = trim((string) ($_POST['title'] ?? ''));
    $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
    $supervisor = trim((string) ($_POST['supervisor'] ?? ''));
    $abstractText = trim((string) ($_POST['abstract_text'] ?? ''));
    $summaryText = trim((string) ($_POST['summary_text'] ?? ''));
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $isSubmitted = isset($_POST['is_submitted']) ? 1 : 0;

    $errors = [];

    if ($title === '' || mb_strlen($title) > 180) {
        $errors[] = 'Rubrik är obligatorisk och får vara högst 180 tecken.';
    }

    if (mb_strlen($subtitle) > 180) {
        $errors[] = 'Underrubriken får vara högst 180 tecken.';
    }

    if ($supervisor === '' || mb_strlen($supervisor) > 120) {
        $errors[] = 'Handledare är obligatorisk och får vara högst 120 tecken.';
    }

    if ($abstractText === '') {
        $errors[] = 'Abstract är obligatoriskt.';
    }

    if ($summaryText === '') {
        $errors[] = 'Sammanfattning är obligatorisk.';
    }

    $upload = validate_pdf_upload($_FILES['pdf_file'] ?? [], $existingProject === null);
    if (!$upload['ok']) {
        $errors[] = $upload['error'];
    }

    if ($errors) {
        return [
            'ok' => false,
            'errors' => $errors,
            'data' => compact('title', 'subtitle', 'supervisor', 'abstractText', 'summaryText', 'isPublic', 'isSubmitted'),
        ];
    }

    $storedFile = null;

    try {
        if ($upload['file']) {
            $storedFile = store_uploaded_pdf($upload['file']);
        }

        $conn->begin_transaction();

        $submittedAt = $isSubmitted === 1 ? date('Y-m-d H:i:s') : null;

        if ($existingProject) {
            $projectId = (int) $existingProject['id'];

            if ($storedFile) {
                execute_prepared(
                    $conn,
                    'UPDATE projects
                     SET title = ?, subtitle = ?, supervisor = ?, abstract_text = ?, summary_text = ?,
                         pdf_filename = ?, pdf_original_name = ?, is_public = ?, is_submitted = ?,
                         submitted_at = ?, updated_at = NOW()
                     WHERE id = ? AND user_id = ?',
                    'sssssssiisii',
                    [
                        $title,
                        $subtitle,
                        $supervisor,
                        $abstractText,
                        $summaryText,
                        $storedFile['stored_name'],
                        $storedFile['original_name'],
                        $isPublic,
                        $isSubmitted,
                        $submittedAt,
                        $projectId,
                        (int) $user['id'],
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
                     SET title = ?, subtitle = ?, supervisor = ?, abstract_text = ?, summary_text = ?,
                         is_public = ?, is_submitted = ?, submitted_at = ?, updated_at = NOW()
                     WHERE id = ? AND user_id = ?',
                    'sssssiisii',
                    [
                        $title,
                        $subtitle,
                        $supervisor,
                        $abstractText,
                        $summaryText,
                        $isPublic,
                        $isSubmitted,
                        $submittedAt,
                        (int) $existingProject['id'],
                        (int) $user['id'],
                    ]
                );
            }

            log_event($conn, (int) $user['id'], 'project_update', 'project', $projectId);
        } else {
            $pdfFilename = $storedFile['stored_name'] ?? null;
            $pdfOriginalName = $storedFile['original_name'] ?? null;

            $stmt = execute_prepared(
                $conn,
                'INSERT INTO projects
                 (user_id, school_id, title, subtitle, supervisor, abstract_text, summary_text,
                  pdf_filename, pdf_original_name, is_public, is_submitted, submitted_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'iisssssssiis',
                [
                    (int) $user['id'],
                    (int) $user['school_id'],
                    $title,
                    $subtitle,
                    $supervisor,
                    $abstractText,
                    $summaryText,
                    $pdfFilename,
                    $pdfOriginalName,
                    $isPublic,
                    $isSubmitted,
                    $submittedAt,
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


