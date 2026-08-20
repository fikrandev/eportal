<?php
/**
 * E-Graduation dashboard API.
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/auth_helper.php';

grad_auth();

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'stats':
        getStats();
        break;
    default:
        json_response(400, false, 'Invalid action');
}

function getStats()
{
    try {
        $user = grad_auth();
        $academicYearId = grad_active_year_id();
        $isAdmin = !empty($user['can_manage_graduation']);
        $scopedClasses = $user['scoped_classes'] ?? [];
        $stats = [];

        // Total Siswa (E-Graduation only)
        if ($isAdmin) {
            $stmt = db()->prepare("SELECT COUNT(*) FROM grad_student_accounts WHERE academic_year_id = ? AND status = 1");
            $stmt->execute([$academicYearId]);
        } else {
            $placeholders = count($scopedClasses) > 0 ? implode(',', array_fill(0, count($scopedClasses), '?')) : "''";
            $stmt = db()->prepare("
                SELECT COUNT(a.id) 
                FROM grad_student_accounts a
                JOIN students s ON s.id = a.student_id
                WHERE a.academic_year_id = ? AND a.status = 1 AND s.kelas IN ($placeholders)
            ");
            $stmt->execute(array_merge([$academicYearId], $scopedClasses));
        }
        $stats['total_siswa'] = (int) $stmt->fetchColumn();

        // Total Kelas (E-Graduation only)
        if ($isAdmin) {
            $stmt = db()->prepare("
                SELECT COUNT(DISTINCT s.kelas) 
                FROM grad_student_accounts a
                JOIN students s ON s.id = a.student_id
                WHERE a.academic_year_id = ? AND a.status = 1 AND s.kelas <> ''
            ");
            $stmt->execute([$academicYearId]);
        } else {
            $stats['total_kelas'] = count($scopedClasses);
        }
        if (!$isAdmin && !isset($stats['total_kelas'])) {
            $stats['total_kelas'] = 0;
        } elseif ($isAdmin) {
            $stats['total_kelas'] = (int) $stmt->fetchColumn();
        }

        // Total Kelompok & Mapel (Stay global or based on classes?)
        // Usually, these are global settings, but if we want to be strict:
        $stmt = db()->prepare("SELECT COUNT(*) FROM grad_subject_groups WHERE academic_year_id = ?");
        $stmt->execute([$academicYearId]);
        $stats['total_kelompok'] = (int) $stmt->fetchColumn();

        $stmt = db()->prepare("SELECT COUNT(*) FROM grad_subjects WHERE academic_year_id = ?");
        $stmt->execute([$academicYearId]);
        $stats['total_mapel'] = (int) $stmt->fetchColumn();

        // Total No Surat
        if ($isAdmin) {
            $stmt = db()->prepare("SELECT COUNT(*) FROM grad_student_letters WHERE academic_year_id = ?");
            $stmt->execute([$academicYearId]);
        } else {
            $placeholders = count($scopedClasses) > 0 ? implode(',', array_fill(0, count($scopedClasses), '?')) : "''";
            $stmt = db()->prepare("
                SELECT COUNT(l.id) 
                FROM grad_student_letters l
                JOIN students s ON s.id = l.student_id
                WHERE l.academic_year_id = ? AND s.kelas IN ($placeholders)
            ");
            $stmt->execute(array_merge([$academicYearId], $scopedClasses));
        }
        $stats['total_no_surat'] = (int) $stmt->fetchColumn();

        $stmt = db()->prepare("SELECT start_number, total, letter_format, graduation_date, signing_date, headmaster_name FROM grad_letter_settings WHERE academic_year_id = ?");
        $stmt->execute([$academicYearId]);
        $stats['letter_setting'] = $stmt->fetch();

        $stmt = db()->prepare("
            SELECT g.id, g.kode, g.nama, g.tipe, COUNT(s.id) as total_mapel
            FROM grad_subject_groups g
            LEFT JOIN grad_subjects s ON s.group_id = g.id
            WHERE g.academic_year_id = ?
            GROUP BY g.id
            ORDER BY g.urutan ASC, g.kode ASC
        ");
        $stmt->execute([$academicYearId]);
        $stats['per_kelompok'] = $stmt->fetchAll();

        // List Kelas
        if ($isAdmin) {
            $stmt = db()->prepare("
                SELECT s.kelas, COUNT(a.id) as total_siswa
                FROM grad_student_accounts a
                JOIN students s ON s.id = a.student_id
                WHERE a.academic_year_id = ? AND a.status = 1 AND s.kelas <> ''
                GROUP BY s.kelas
                ORDER BY s.kelas ASC
            ");
            $stmt->execute([$academicYearId]);
            $stats['kelas'] = $stmt->fetchAll();
        } else {
            if (count($scopedClasses) > 0) {
                $placeholders = implode(',', array_fill(0, count($scopedClasses), '?'));
                $stmt = db()->prepare("
                    SELECT s.kelas, COUNT(a.id) as total_siswa
                    FROM grad_student_accounts a
                    JOIN students s ON s.id = a.student_id
                    WHERE a.academic_year_id = ? AND a.status = 1 AND s.kelas IN ($placeholders)
                    GROUP BY s.kelas
                    ORDER BY s.kelas ASC
                ");
                $stmt->execute(array_merge([$academicYearId], $scopedClasses));
                $stats['kelas'] = $stmt->fetchAll();
            } else {
                $stats['kelas'] = [];
            }
        }

        json_response(200, true, 'Dashboard stats', $stats);
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}
