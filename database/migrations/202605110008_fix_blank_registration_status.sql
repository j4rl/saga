UPDATE users
SET approval_status = 'pending'
WHERE approval_status = ''
  AND role IN ('student', 'teacher');
