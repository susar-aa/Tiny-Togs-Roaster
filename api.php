<?php
// api.php - API endpoint for AJAX calls
session_start();

header('Content-Type: application/json');

// Check authorization
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please login.']);
    exit;
}

require_once 'db.php';

function checkGenderBalanceAfterSwap($pdo, $date, $emp_id, $replacement_id, $original_shift, $repl_shift) {
    $stmt = $pdo->prepare("
        SELECT mr.emp_id, mr.shift_code, e.gender
        FROM monthly_roster mr
        JOIN employees e ON mr.emp_id = e.emp_id
        WHERE mr.date = ?
    ");
    $stmt->execute([$date]);
    $roster_today = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $simulated_shifts = [];
    foreach ($roster_today as $row) {
        $id = (int)$row['emp_id'];
        $shift = $row['shift_code'];
        $gender = $row['gender'];

        if ($id === $emp_id) {
            $shift = 'Off';
        } elseif ($id === $replacement_id) {
            if ($repl_shift === 'Off') {
                $shift = $original_shift;
            } else {
                $shift = 'F';
            }
        }
        $simulated_shifts[] = [
            'gender' => $gender,
            'shift' => $shift
        ];
    }

    $morning_males = 0; $morning_females = 0;
    $night_males = 0; $night_females = 0;

    foreach ($simulated_shifts as $sim) {
        $gender = $sim['gender'];
        $shift = $sim['shift'];

        if (in_array($shift, ['M', 'Mw', 'F'])) {
            if ($gender === 'Male') $morning_males++; else $morning_females++;
        }
        if (in_array($shift, ['N', 'Nw', 'F'])) {
            if ($gender === 'Male') $night_males++; else $night_females++;
        }
    }

    $warnings = [];
    if ($morning_males < 2) $warnings[] = "only {$morning_males} Male(s) on the Morning Shift";
    if ($morning_females < 2) $warnings[] = "only {$morning_females} Female(s) on the Morning Shift";
    if ($night_males < 2) $warnings[] = "only {$night_males} Male(s) on the Night Shift";
    if ($night_females < 2) $warnings[] = "only {$night_females} Female(s) on the Night Shift";

    if (empty($warnings)) return null;
    return "Warning: This swap results in " . implode(" and ", $warnings) . ".";
}

// NEW: Validates two employees directly trading whatever shifts they currently hold
function checkGenderBalanceAfterDirectSwap($pdo, $date, $emp1_id, $emp2_id, $shift1, $shift2) {
    $stmt = $pdo->prepare("
        SELECT mr.emp_id, mr.shift_code, e.gender, e.skill_level
        FROM monthly_roster mr
        JOIN employees e ON mr.emp_id = e.emp_id
        WHERE mr.date = ?
    ");
    $stmt->execute([$date]);
    $roster_today = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $simulated_shifts = [];
    $good_count = 0;

    foreach ($roster_today as $row) {
        $id = (int)$row['emp_id'];
        $shift = $row['shift_code'];
        $gender = $row['gender'];
        $skill = $row['skill_level'];

        // Apply direct swap simulation
        if ($id === $emp1_id) $shift = $shift2;
        elseif ($id === $emp2_id) $shift = $shift1;

        if ($shift !== 'Off' && $skill === 'Good') $good_count++;

        $simulated_shifts[] = ['gender' => $gender, 'shift' => $shift];
    }

    $warnings = [];
    if ($good_count < 3) $warnings[] = "only {$good_count} Good staff working (Minimum 3 required)";

    $morning_males = 0; $morning_females = 0;
    $night_males = 0; $night_females = 0;

    foreach ($simulated_shifts as $sim) {
        $g = $sim['gender']; $s = $sim['shift'];
        if (in_array($s, ['M', 'Mw', 'F'])) {
            if ($g === 'Male') $morning_males++; else $morning_females++;
        }
        if (in_array($s, ['N', 'Nw', 'F'])) {
            if ($g === 'Male') $night_males++; else $night_females++;
        }
    }

    if ($morning_males < 2) $warnings[] = "only {$morning_males} Male(s) on Morning Shift";
    if ($morning_females < 2) $warnings[] = "only {$morning_females} Female(s) on Morning Shift";
    if ($night_males < 2) $warnings[] = "only {$night_males} Male(s) on Night Shift";
    if ($night_females < 2) $warnings[] = "only {$night_females} Female(s) on Night Shift";

    if (empty($warnings)) return null;
    return "Warning: This direct swap results in " . implode(", ", $warnings) . ".";
}

function checkGenderBalanceAfterShiftChange($pdo, $date, $emp_id, $new_shift) {
    $stmt = $pdo->prepare("
        SELECT mr.emp_id, mr.shift_code, e.gender, e.skill_level
        FROM monthly_roster mr
        JOIN employees e ON mr.emp_id = e.emp_id
        WHERE mr.date = ?
    ");
    $stmt->execute([$date]);
    $roster_today = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $simulated_shifts = [];
    $good_count = 0;

    foreach ($roster_today as $row) {
        $id = (int)$row['emp_id'];
        $shift = $row['shift_code'];
        $gender = $row['gender'];
        $skill = $row['skill_level'];

        if ($id === $emp_id) {
            $shift = $new_shift;
        }

        if ($shift !== 'Off' && $skill === 'Good') {
            $good_count++;
        }

        $simulated_shifts[] = ['gender' => $gender, 'shift' => $shift];
    }

    $warnings = [];
    if ($good_count < 3) {
        $warnings[] = "only {$good_count} Good staff working (Minimum 3 required)";
    }

    $morning_males = 0; $morning_females = 0;
    $night_males = 0; $night_females = 0;

    foreach ($simulated_shifts as $sim) {
        $g = $sim['gender']; $s = $sim['shift'];
        if (in_array($s, ['M', 'Mw', 'F'])) {
            if ($g === 'Male') $morning_males++; else $morning_females++;
        }
        if (in_array($s, ['N', 'Nw', 'F'])) {
            if ($g === 'Male') $night_males++; else $night_females++;
        }
    }

    if ($morning_males < 2) $warnings[] = "only {$morning_males} Male(s) on Morning Shift";
    if ($morning_females < 2) $warnings[] = "only {$morning_females} Female(s) on Morning Shift";
    if ($night_males < 2) $warnings[] = "only {$night_males} Male(s) on Night Shift";
    if ($night_females < 2) $warnings[] = "only {$night_females} Female(s) on Night Shift";

    if (empty($warnings)) return null;
    return "Warning: This shift change results in " . implode(", ", $warnings) . ".";
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_employees':
        try {
            $stmt = $pdo->query("SELECT * FROM employees ORDER BY role ASC, name ASC");
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $employees]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save_employee':
        try {
            $emp_id = $_POST['emp_id'] ?? null;
            $name = trim($_POST['name'] ?? '');
            $skill_level = $_POST['skill_level'] ?? 'Normal';
            $role = $_POST['role'] ?? 'Rotating';
            $gender = $_POST['gender'] ?? 'Female';

            if (empty($name)) throw new Exception("Employee name cannot be empty.");
            if ($gender !== 'Male' && $gender !== 'Female') throw new Exception("Invalid gender specification.");

            if ($emp_id) {
                $stmt = $pdo->prepare("UPDATE employees SET name = ?, skill_level = ?, role = ?, gender = ? WHERE emp_id = ?");
                $stmt->execute([$name, $skill_level, $role, $gender, $emp_id]);
                $message = "Employee updated successfully.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO employees (name, skill_level, role, gender) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $skill_level, $role, $gender]);
                $emp_id = $pdo->lastInsertId();
                $message = "Employee added successfully.";
            }
            echo json_encode(['success' => true, 'message' => $message, 'emp_id' => $emp_id]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_employee':
        try {
            $emp_id = (int)($_POST['emp_id'] ?? 0);
            if (!$emp_id) throw new Exception("Invalid employee ID.");
            $stmt = $pdo->prepare("DELETE FROM employees WHERE emp_id = ?");
            $stmt->execute([$emp_id]);
            echo json_encode(['success' => true, 'message' => "Employee deleted successfully."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_calendar':
        try {
            $year = (int)($_GET['year'] ?? date('Y'));
            $month = (int)($_GET['month'] ?? date('m'));
            $month_str = str_pad($month, 2, '0', STR_PAD_LEFT);
            $start_date = "$year-$month_str-01";
            $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $end_date = "$year-$month_str-" . str_pad($days_in_month, 2, '0', STR_PAD_LEFT);

            $stmt_cached = $pdo->prepare("SELECT * FROM cached_holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date ASC");
            $stmt_cached->execute([$start_date, $end_date]);
            $cached = [];
            foreach ($stmt_cached->fetchAll(PDO::FETCH_ASSOC) as $row) $cached[$row['holiday_date']] = $row;

            $stmt_overrides = $pdo->prepare("SELECT * FROM monthly_holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date ASC");
            $stmt_overrides->execute([$start_date, $end_date]);
            $overrides = [];
            foreach ($stmt_overrides->fetchAll(PDO::FETCH_ASSOC) as $row) $overrides[$row['holiday_date']] = $row;

            $calendar = [];
            for ($d = 1; $d <= $days_in_month; $d++) {
                $date_str = "$year-$month_str-" . str_pad($d, 2, '0', STR_PAD_LEFT);
                $day_of_week = (int)date('N', strtotime($date_str)); 
                $day_name = date('l', strtotime($date_str));

                if (isset($overrides[$date_str])) {
                    $override_type = $overrides[$date_str]['day_type'];
                    if ($override_type === 'Weekday') {
                        $day_type = ($day_of_week === 6 || $day_of_week === 7) ? 'Weekend' : 'Weekday';
                        $description = $day_name . ' (Forced Standard)';
                    } else {
                        $day_type = $override_type;
                        $description = $overrides[$date_str]['description'] ?: $override_type;
                    }
                } elseif (isset($cached[$date_str])) {
                    $day_type = $cached[$date_str]['day_type'];
                    $description = $cached[$date_str]['description'] ?: $day_type;
                } else {
                    $day_type = ($day_of_week === 6 || $day_of_week === 7) ? 'Weekend' : 'Weekday';
                    $description = $day_name;
                }

                $calendar[] = ['date' => $date_str, 'day_type' => $day_type, 'description' => $description];
            }
            echo json_encode(['success' => true, 'data' => $calendar]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'update_day_type':
        try {
            $date = $_POST['date'] ?? '';
            $day_type = $_POST['day_type'] ?? 'Weekday';
            $description = trim($_POST['description'] ?? '');

            if (empty($date)) throw new Exception("Date is required.");

            if ($day_type === 'Default') {
                $stmt = $pdo->prepare("DELETE FROM monthly_holidays WHERE holiday_date = ?");
                $stmt->execute([$date]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO monthly_holidays (holiday_date, day_type, description) VALUES (?, ?, ?) 
                                       ON DUPLICATE KEY UPDATE day_type = ?, description = ?");
                $stmt->execute([$date, $day_type, $description, $day_type, $description]);
            }
            echo json_encode(['success' => true, 'message' => "Holiday configuration updated successfully."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_leave_requests':
        try {
            $year = (int)($_GET['year'] ?? date('Y'));
            $month = (int)($_GET['month'] ?? date('m'));
            $month_str = str_pad($month, 2, '0', STR_PAD_LEFT);
            $start_date = "$year-$month_str-01";
            $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $end_date = "$year-$month_str-" . str_pad($days_in_month, 2, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("SELECT lr.*, e.name as employee_name 
                                   FROM leave_requests lr
                                   JOIN employees e ON lr.emp_id = e.emp_id
                                   WHERE lr.requested_date BETWEEN ? AND ? 
                                   ORDER BY lr.requested_date ASC, e.name ASC");
            $stmt->execute([$start_date, $end_date]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save_leave_request':
        try {
            $emp_id = (int)($_POST['emp_id'] ?? 0);
            $date = $_POST['date'] ?? '';
            $status = $_POST['status'] ?? 'Approved';

            if (!$emp_id || empty($date)) throw new Exception("Employee and date are required.");

            $emp_chk = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE emp_id = ?");
            $emp_chk->execute([$emp_id]);
            if ($emp_chk->fetchColumn() == 0) throw new Exception("Employee not found.");

            $year_month = date('Y-m', strtotime($date));
            $start_date = "$year_month-01";
            $days_in_month = date('t', strtotime($date));
            $end_date = "$year_month-$days_in_month";

            $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE emp_id = ? AND requested_date BETWEEN ? AND ?");
            $stmt_count->execute([$emp_id, $start_date, $end_date]);
            $leave_count = $stmt_count->fetchColumn();

            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE emp_id = ? AND requested_date = ?");
            $stmt_check->execute([$emp_id, $date]);
            $already_requested = $stmt_check->fetchColumn() > 0;

            if (!$already_requested && $leave_count >= 4) {
                throw new Exception("Maximum quota of 4 leave requests reached.");
            }

            $stmt = $pdo->prepare("INSERT INTO leave_requests (emp_id, requested_date, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?");
            $stmt->execute([$emp_id, $date, $status, $status]);

            echo json_encode(['success' => true, 'message' => "Leave request saved successfully."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_leave_request':
        try {
            $request_id = (int)($_POST['request_id'] ?? 0);
            if (!$request_id) throw new Exception("Invalid request ID.");
            $stmt = $pdo->prepare("DELETE FROM leave_requests WHERE request_id = ?");
            $stmt->execute([$request_id]);
            echo json_encode(['success' => true, 'message' => "Leave request removed successfully."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_roster':
        try {
            $year = (int)($_GET['year'] ?? date('Y'));
            $month = (int)($_GET['month'] ?? date('m'));
            $month_str = str_pad($month, 2, '0', STR_PAD_LEFT);
            $start_date = "$year-$month_str-01";
            $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $end_date = "$year-$month_str-" . str_pad($days_in_month, 2, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("SELECT mr.* FROM monthly_roster mr WHERE mr.date BETWEEN ? AND ? ORDER BY mr.date ASC");
            $stmt->execute([$start_date, $end_date]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'clear_roster':
        try {
            $year = (int)($_POST['year'] ?? date('Y'));
            $month = (int)($_POST['month'] ?? date('m'));
            $month_str = str_pad($month, 2, '0', STR_PAD_LEFT);
            $start_date = "$year-$month_str-01";
            $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $end_date = "$year-$month_str-" . str_pad($days_in_month, 2, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("DELETE FROM monthly_roster WHERE date BETWEEN ? AND ?");
            $stmt->execute([$start_date, $end_date]);
            echo json_encode(['success' => true, 'message' => 'Monthly roster cleared successfully.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_replacements':
        try {
            $emp_id = (int)($_GET['emp_id'] ?? 0);
            $date = $_GET['date'] ?? '';

            if (!$emp_id || empty($date)) throw new Exception("Employee ID and Date are required.");

            $stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ?");
            $stmt->execute([$emp_id]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$emp) throw new Exception("Employee not found.");

            $stmt_shift = $pdo->prepare("SELECT shift_code FROM monthly_roster WHERE emp_id = ? AND date = ?");
            $stmt_shift->execute([$emp_id, $date]);
            $original_shift = $stmt_shift->fetchColumn();

            if (!$original_shift || $original_shift === 'Off') throw new Exception("Employee is not scheduled to work on this day.");

            $stmt_candidates = $pdo->prepare("
                SELECT e.*, mr.shift_code
                FROM employees e
                JOIN monthly_roster mr ON e.emp_id = mr.emp_id
                WHERE mr.date = ? 
                  AND e.emp_id != ?
                  AND e.role = ?
                ORDER BY e.name ASC
            ");
            $stmt_candidates->execute([$date, $emp_id, $emp['role']]);
            $candidates = $stmt_candidates->fetchAll(PDO::FETCH_ASSOC);

            $stmt_good_count = $pdo->prepare("
                SELECT COUNT(*) 
                FROM monthly_roster mr
                JOIN employees e ON mr.emp_id = e.emp_id
                WHERE mr.date = ? AND mr.shift_code != 'Off' AND e.skill_level = 'Good'
            ");
            $stmt_good_count->execute([$date]);
            $current_good_count = (int)$stmt_good_count->fetchColumn();

            $filtered_candidates = [];
            foreach ($candidates as $c) {
                $repl_shift = $c['shift_code'];
                $swap_type = '';

                if ($repl_shift === 'Off') {
                    $swap_type = 'Swap';
                } else {
                    $is_opp = false;
                    if (in_array($original_shift, ['M', 'Mw']) && in_array($repl_shift, ['N', 'Nw'])) $is_opp = true;
                    elseif (in_array($original_shift, ['N', 'Nw']) && in_array($repl_shift, ['M', 'Mw'])) $is_opp = true;
                    elseif ($original_shift === 'F' && in_array($repl_shift, ['M', 'Mw', 'N', 'Nw'])) $is_opp = true;

                    if ($is_opp) $swap_type = 'Upgrade';
                    else continue;
                }

                $would_be_valid = true;
                $reason = "";

                if ($emp['skill_level'] === 'Good' && $c['skill_level'] === 'Normal') {
                    if ($current_good_count - 1 < 3) {
                        $would_be_valid = false;
                        $reason = "Violates 'Min 3 Good staff' rule (Only $current_good_count currently scheduled)";
                    }
                }

                $warning = checkGenderBalanceAfterSwap($pdo, $date, $emp_id, $c['emp_id'], $original_shift, $repl_shift);

                $filtered_candidates[] = [
                    'emp_id' => $c['emp_id'],
                    'name' => $c['name'],
                    'skill_level' => $c['skill_level'],
                    'role' => $c['role'],
                    'shift_code' => $repl_shift,
                    'type' => $swap_type,
                    'is_valid' => $would_be_valid,
                    'reason' => $reason,
                    'warning' => $warning
                ];
            }
            echo json_encode(['success' => true, 'data' => $filtered_candidates, 'current_good_count' => $current_good_count, 'replaced_skill' => $emp['skill_level']]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // NEW: Handles Drag and Drop instant swapping!
    case 'direct_swap':
        try {
            $emp1_id = (int)($_POST['emp1_id'] ?? 0);
            $emp2_id = (int)($_POST['emp2_id'] ?? 0);
            $date = $_POST['date'] ?? '';
            $force = (int)($_POST['force'] ?? 0);

            if (!$emp1_id || !$emp2_id || empty($date)) throw new Exception("Employees and date are required.");

            $stmt_emp1 = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ?");
            $stmt_emp1->execute([$emp1_id]);
            $emp1 = $stmt_emp1->fetch(PDO::FETCH_ASSOC);

            $stmt_emp2 = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ?");
            $stmt_emp2->execute([$emp2_id]);
            $emp2 = $stmt_emp2->fetch(PDO::FETCH_ASSOC);

            if (!$emp1 || !$emp2) throw new Exception("Employee not found.");

            $stmt_s1 = $pdo->prepare("SELECT shift_code FROM monthly_roster WHERE emp_id = ? AND date = ?");
            $stmt_s1->execute([$emp1_id, $date]);
            $shift1 = $stmt_s1->fetchColumn();

            $stmt_s2 = $pdo->prepare("SELECT shift_code FROM monthly_roster WHERE emp_id = ? AND date = ?");
            $stmt_s2->execute([$emp2_id, $date]);
            $shift2 = $stmt_s2->fetchColumn();

            if ($shift1 === false || $shift2 === false) throw new Exception("One or both employees not scheduled for this month.");
            if ($shift1 === $shift2) throw new Exception("Both employees already have the same shift.");

            if ($emp1['role'] !== $emp2['role']) {
                throw new Exception("Cannot swap employees with different core roles ({$emp1['role']} vs {$emp2['role']}).");
            }

            $gender_warning = checkGenderBalanceAfterDirectSwap($pdo, $date, $emp1_id, $emp2_id, $shift1, $shift2);
            if ($gender_warning && !$force) {
                echo json_encode(['success' => false, 'is_warning' => true, 'message' => $gender_warning]);
                exit;
            }

            $pdo->beginTransaction();

            $upd1 = $pdo->prepare("UPDATE monthly_roster SET shift_code = ?, is_emergency_swap = 1, swapped_with_emp_id = ? WHERE emp_id = ? AND date = ?");
            $upd1->execute([$shift2, $emp2_id, $emp1_id, $date]);

            $upd2 = $pdo->prepare("UPDATE monthly_roster SET shift_code = ?, is_emergency_swap = 1, swapped_with_emp_id = ? WHERE emp_id = ? AND date = ?");
            $upd2->execute([$shift1, $emp1_id, $emp2_id, $date]);

            $pdo->commit();

            echo json_encode(['success' => true, 'message' => "Shifts successfully swapped via Drag & Drop!"]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'execute_swap':
        try {
            $emp_id = (int)($_POST['emp_id'] ?? 0);
            $replacement_id = (int)($_POST['replacement_id'] ?? 0);
            $date = $_POST['date'] ?? '';
            $force = (int)($_POST['force'] ?? 0);

            if (!$emp_id || !$replacement_id || empty($date)) throw new Exception("Employee, replacement, and date are required.");

            $stmt_emp = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ?");
            $stmt_emp->execute([$emp_id]);
            $emp = $stmt_emp->fetch(PDO::FETCH_ASSOC);

            $stmt_repl = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ?");
            $stmt_repl->execute([$replacement_id]);
            $repl = $stmt_repl->fetch(PDO::FETCH_ASSOC);

            if (!$emp || !$repl) throw new Exception("Employee or replacement not found.");

            $stmt_shift = $pdo->prepare("SELECT shift_code FROM monthly_roster WHERE emp_id = ? AND date = ?");
            $stmt_shift->execute([$emp_id, $date]);
            $original_shift = $stmt_shift->fetchColumn();

            if (!$original_shift || $original_shift === 'Off') throw new Exception("Employee is not scheduled to work on this day.");

            $stmt_repl_shift = $pdo->prepare("SELECT shift_code FROM monthly_roster WHERE emp_id = ? AND date = ?");
            $stmt_repl_shift->execute([$replacement_id, $date]);
            $repl_shift = $stmt_repl_shift->fetchColumn();

            if (!$repl_shift) throw new Exception("Replacement employee is not scheduled on this day.");

            if ($emp['role'] !== $repl['role']) throw new Exception("Cannot swap employees with different core roles.");

            if ($emp['skill_level'] === 'Good' && $repl['skill_level'] === 'Normal') {
                $stmt_good = $pdo->prepare("
                    SELECT COUNT(*) FROM monthly_roster mr
                    JOIN employees e ON mr.emp_id = e.emp_id
                    WHERE mr.date = ? AND mr.shift_code != 'Off' AND e.skill_level = 'Good'
                ");
                $stmt_good->execute([$date]);
                $good_count = (int)$stmt_good->fetchColumn();

                if ($good_count - 1 < 3) throw new Exception("Swap failed: Leaves only " . ($good_count - 1) . " Good employee(s) working on $date.");
            }

            $gender_warning = checkGenderBalanceAfterSwap($pdo, $date, $emp_id, $replacement_id, $original_shift, $repl_shift);
            if ($gender_warning && !$force) {
                echo json_encode(['success' => false, 'is_warning' => true, 'message' => $gender_warning]);
                exit;
            }

            $pdo->beginTransaction();

            $upd_emp = $pdo->prepare("UPDATE monthly_roster SET shift_code = 'Off', is_emergency_swap = 1, swapped_with_emp_id = ? WHERE emp_id = ? AND date = ?");
            $upd_emp->execute([$replacement_id, $emp_id, $date]);

            if ($repl_shift === 'Off') {
                $upd_repl = $pdo->prepare("UPDATE monthly_roster SET shift_code = ?, is_emergency_swap = 1, swapped_with_emp_id = ? WHERE emp_id = ? AND date = ?");
                $upd_repl->execute([$original_shift, $emp_id, $replacement_id, $date]);
                $msg = "Emergency swap executed successfully! {$repl['name']} is now working the '{$original_shift}' shift, and {$emp['name']} has been set to OFF.";
            } else {
                $upd_repl = $pdo->prepare("UPDATE monthly_roster SET shift_code = 'F', is_emergency_swap = 1, swapped_with_emp_id = ? WHERE emp_id = ? AND date = ?");
                $upd_repl->execute([$emp_id, $replacement_id, $date]);
                $msg = "Emergency Shift Upgrade executed successfully! {$repl['name']} is upgraded from '{$repl_shift}' to 'F', and {$emp['name']} has been set to OFF.";
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => $msg]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_users':
        try {
            $stmt = $pdo->query("SELECT id, username, created_at FROM users ORDER BY username ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $users]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save_user':
        try {
            $user_id = $_POST['user_id'] ?? null;
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username)) throw new Exception("Username cannot be empty.");

            if ($user_id) {
                // Update
                if (!empty($password)) {
                    if (strlen($password) < 6) throw new Exception("Password must be at least 6 characters.");
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
                    $stmt->execute([$username, $passwordHash, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                    $stmt->execute([$username, $user_id]);
                }
                
                // If updated user is current user, update session username
                if ($_SESSION['user_id'] == $user_id) {
                    $_SESSION['username'] = $username;
                }
                
                $message = "User updated successfully.";
            } else {
                // Create
                if (empty($password)) throw new Exception("Password is required for new users.");
                if (strlen($password) < 6) throw new Exception("Password must be at least 6 characters.");
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt->execute([$username, $passwordHash]);
                $user_id = $pdo->lastInsertId();
                $message = "User created successfully.";
            }
            echo json_encode(['success' => true, 'message' => $message]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_user':
        try {
            $user_id = (int)($_POST['user_id'] ?? 0);
            if (!$user_id) throw new Exception("Invalid user ID.");

            if ($_SESSION['user_id'] == $user_id) {
                throw new Exception("You cannot delete your own logged-in account.");
            }

            // Check total users count to prevent locking out
            $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if ($count <= 1) {
                throw new Exception("Cannot delete the last user in the system.");
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            echo json_encode(['success' => true, 'message' => "User deleted successfully."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_shifts':
        try {
            $stmt = $pdo->query("SELECT * FROM shifts ORDER BY shift_name ASC");
            $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $shifts]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'change_shift':
        try {
            $emp_id = (int)($_POST['emp_id'] ?? 0);
            $date = $_POST['date'] ?? '';
            $new_shift = $_POST['shift_code'] ?? '';
            $force = (int)($_POST['force'] ?? 0);

            if (!$emp_id || empty($date) || empty($new_shift)) {
                throw new Exception("Employee, date, and new shift are required.");
            }

            $stmt_emp = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ?");
            $stmt_emp->execute([$emp_id]);
            $emp = $stmt_emp->fetch(PDO::FETCH_ASSOC);
            if (!$emp) throw new Exception("Employee not found.");

            // Check if shift is valid
            $stmt_check_shift = $pdo->prepare("SELECT COUNT(*) FROM shifts WHERE shift_code = ?");
            $stmt_check_shift->execute([$new_shift]);
            if ($stmt_check_shift->fetchColumn() == 0) {
                throw new Exception("Invalid shift code.");
            }

            // Check gender balance / roster constraint warnings
            $warning = checkGenderBalanceAfterShiftChange($pdo, $date, $emp_id, $new_shift);
            if ($warning && !$force) {
                echo json_encode(['success' => false, 'is_warning' => true, 'message' => $warning]);
                exit;
            }

            // Perform update
            $pdo->beginTransaction();

            // Check if record exists in monthly_roster
            $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM monthly_roster WHERE emp_id = ? AND date = ?");
            $stmt_chk->execute([$emp_id, $date]);
            if ($stmt_chk->fetchColumn() > 0) {
                // Update
                $stmt_upd = $pdo->prepare("
                    UPDATE monthly_roster 
                    SET shift_code = ?, is_emergency_swap = 1, swapped_with_emp_id = NULL 
                    WHERE emp_id = ? AND date = ?
                ");
                $stmt_upd->execute([$new_shift, $emp_id, $date]);
            } else {
                // Insert
                $stmt_ins = $pdo->prepare("
                    INSERT INTO monthly_roster (emp_id, date, shift_code, is_emergency_swap, swapped_with_emp_id) 
                    VALUES (?, ?, ?, 1, NULL)
                ");
                $stmt_ins->execute([$emp_id, $date, $new_shift]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Shift successfully updated for {$emp['name']} to '{$new_shift}'."]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid API Action.']);
        break;
}
?>