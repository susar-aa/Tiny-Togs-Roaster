<?php
// generate_roster.php - Core roster generation algorithm using Stochastic Joint CSP Solver
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please login.']);
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);

// START OUTPUT BUFFER
ob_start();

set_time_limit(0); 
ini_set('memory_limit', '512M'); 
require_once 'db.php';

function sendJsonResponse($data) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['success' => false, 'message' => 'Invalid request method.']);
}

$debug_logs = [];
$deepest_fail_reason = "";
$max_day_reached = -1;
$script_start = microtime(true);
$iteration_count = 0;
$timeout_reached = false;
$last_progress_write = 0; 

function updateProgress($percent, $message, $status = 'processing', $roster_state = null) {
    global $last_progress_write;
    $current_time = microtime(true);
    
    if ($percent >= 100 || $status === 'error' || ($current_time - $last_progress_write >= 0.5)) {
        $data = [
            'percent' => min(100, max(0, $percent)),
            'message' => $message,
            'status' => $status,
            'roster' => $roster_state 
        ];
        
        $out = ob_get_contents();
        ob_clean();
        file_put_contents(__DIR__ . '/progress.json', json_encode($data), LOCK_EX);
        echo $out; 
        
        $last_progress_write = $current_time;
    }
}

function logDebug($msg) {
    global $debug_logs;
    $debug_logs[] = $msg;
    if (count($debug_logs) > 300) array_shift($debug_logs); 
}

try {
    updateProgress(0, 'Initializing Bulletproof CSP Generator...');
    
    $year = (int)($_POST['year'] ?? date('Y'));
    $month = (int)($_POST['month'] ?? date('m'));

    if (!$year || !$month) throw new Exception("Year and month are required.");

    $month_str = str_pad($month, 2, '0', STR_PAD_LEFT);
    $start_date = "$year-$month_str-01";
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $end_date = "$year-$month_str-" . str_pad($days_in_month, 2, '0', STR_PAD_LEFT);

    updateProgress(5, 'Fetching employee data & mapping legacy roles...');
    $stmt_emp = $pdo->query("SELECT * FROM employees");
    $employees = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);
    if (count($employees) === 0) throw new Exception("No employees found. Please register employees first.");
    
    foreach ($employees as $k => $e) {
        $role = isset($e['role']) ? $e['role'] : null;
        if (!$role) {
            $is_anc = isset($e['is_anchor']) ? $e['is_anchor'] : 0;
            $is_csh = isset($e['is_cashier']) ? $e['is_cashier'] : 0;
            if ($is_anc) $role = 'Anchor';
            elseif ($is_csh) $role = 'Cashier';
            else $role = 'Rotating';
            $employees[$k]['role'] = $role;
        }
    }

    updateProgress(10, 'Checking Induwara API for Sri Lankan Holidays...');
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM cached_holidays WHERE YEAR(holiday_date) = ?");
    $stmt_count->execute([$year]);
    
    if ((int)$stmt_count->fetchColumn() === 0) {
        $url = "https://induwara.lk/api/v1/holidays?year={$year}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ilk_live_10ede3621d457ccba9d0b0fdfec027ef48bf41cfd263d250']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $resObj = json_decode($response, true);
            if (isset($resObj['ok']) && $resObj['ok'] && isset($resObj['data']['holidays'])) {
                $ins_stmt = $pdo->prepare("INSERT IGNORE INTO cached_holidays (holiday_date, day_type, description) VALUES (?, ?, ?)");
                foreach ($resObj['data']['holidays'] as $h) {
                    $nameLower = strtolower($h['name']);
                    $is_poya = ((strpos($nameLower, 'poya') !== false) || (strpos($nameLower, 'vesak') !== false)) && !((strpos($nameLower, 'following') !== false) || (strpos($nameLower, 'after') !== false));
                    if ($h['public'] || $h['mercantile'] || $is_poya) {
                        $ins_stmt->execute([$h['date'], $is_poya ? 'Poya' : 'Public Holiday', $h['name']]);
                    }
                }
            }
        }
    }

    $stmt_cached = $pdo->prepare("SELECT * FROM cached_holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date ASC");
    $stmt_cached->execute([$start_date, $end_date]);
    $cached = [];
    foreach ($stmt_cached->fetchAll(PDO::FETCH_ASSOC) as $row) $cached[$row['holiday_date']] = $row;

    $stmt_overrides = $pdo->prepare("SELECT * FROM monthly_holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date ASC");
    $stmt_overrides->execute([$start_date, $end_date]);
    $overrides = [];
    foreach ($stmt_overrides->fetchAll(PDO::FETCH_ASSOC) as $row) $overrides[$row['holiday_date']] = $row;

    $calendar_days = [];
    for ($d = 1; $d <= $days_in_month; $d++) {
        $date_str = "$year-$month_str-" . str_pad($d, 2, '0', STR_PAD_LEFT);
        $day_of_week = (int)date('N', strtotime($date_str)); 
        
        if (isset($overrides[$date_str])) {
            $override_type = $overrides[$date_str]['day_type'];
            $day_type = ($override_type === 'Weekday') ? (($day_of_week === 6 || $day_of_week === 7) ? 'Weekend' : 'Weekday') : $override_type;
        } elseif (isset($cached[$date_str])) {
            $day_type = $cached[$date_str]['day_type'];
        } else {
            $day_type = ($day_of_week === 6 || $day_of_week === 7) ? 'Weekend' : 'Weekday';
        }
        $calendar_days[] = ['date' => $date_str, 'day_type' => $day_type];
    }

    updateProgress(15, 'Analyzing manual leave configurations...');
    $stmt_leaves = $pdo->prepare("SELECT * FROM leave_requests WHERE requested_date BETWEEN ? AND ? AND status = 'Approved'");
    $stmt_leaves->execute([$start_date, $end_date]);
    $approved_leaves = [];
    foreach ($stmt_leaves->fetchAll(PDO::FETCH_ASSOC) as $lv) $approved_leaves[$lv['emp_id']][$lv['requested_date']] = true;

    $male_anchor_ids = [];
    foreach ($employees as $emp) {
        if ($emp['gender'] === 'Male' && (isset($emp['role']) && $emp['role'] === 'Anchor')) {
            $male_anchor_ids[] = $emp['emp_id'];
        }
    }

    $max_attempts = 20; 
    $roster_solved = false;
    $roster = [];

    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
        $roster = [];
        $off_counts = [];
        $iteration_count = 0; 
        $timeout_reached = false;
        
        $relaxation_level = floor(($attempt - 1) / 5); 
        
        foreach ($employees as $e) {
            $roster[$e['emp_id']] = [];
            $off_counts[$e['emp_id']] = 0;
        }

        logDebug("--- Starting Generation Attempt #{$attempt} (Relaxation Level: {$relaxation_level}) ---");
        $msg = ($relaxation_level == 0) ? "Initiating core back-tracking solver loop..." : "Attempt #{$attempt} (Learning): Relaxing constraints to bypass dead-ends...";
        if ($relaxation_level >= 3) $msg = "Attempt #{$attempt} (God Mode): Overriding all hard boundaries to force schedule completion...";
        
        updateProgress(15, $msg, 'processing', $roster);
        
        $roster_solved = solveRosterJoint(0, $calendar_days, $employees, $roster, $off_counts, $approved_leaves, $male_anchor_ids, $relaxation_level);
        
        if ($roster_solved && !$timeout_reached) break; 
        else logDebug("Attempt #{$attempt} aborted. Reason: " . $deepest_fail_reason);
    }

    $time_end = microtime(true);

    if (!$roster_solved) {
        throw new Exception("Solver exhausted all {$max_attempts} attempts. Final Block Reason: " . $deepest_fail_reason . " Check Console Logs.");
    }

    updateProgress(95, "Valid schedule found! Saving mathematical matrix to database...", 'processing', $roster);
    $pdo->beginTransaction();
    $stmt_clear = $pdo->prepare("DELETE FROM monthly_roster WHERE date BETWEEN ? AND ?");
    $stmt_clear->execute([$start_date, $end_date]);

    $stmt_ins = $pdo->prepare("INSERT INTO monthly_roster (emp_id, date, shift_code, is_emergency_swap, swapped_with_emp_id) VALUES (?, ?, ?, 0, NULL)");

    foreach ($roster as $emp_id => $dates) {
        foreach ($dates as $date => $shift_code) {
            if ($shift_code !== null) $stmt_ins->execute([$emp_id, $date, $shift_code]);
        }
    }

    $pdo->commit();
    updateProgress(100, "Complete!", 'processing', $roster);
    
    if ($attempt > 1) array_unshift($debug_logs, "SOLVER STRUGGLED DUE TO: " . $deepest_fail_reason);

    sendJsonResponse([
        'success' => true, 
        'message' => "Timetable generated successfully in " . round($time_end - $script_start, 2) . "s! (Took {$attempt} attempts)",
        'debug_log' => $debug_logs
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    updateProgress(100, 'Generation failed.', 'error');
    
    sendJsonResponse([
        'success' => false, 
        'message' => $e->getMessage(), 
        'debug_log' => $debug_logs ?? []
    ]);
}

// --- CSP SOLVER FUNCTIONS ---
function solveRotatingShifts($idx, $working_rotating, $day_idx, $calendar_days, &$roster, $employees, &$counts, &$off_counts, &$valid_choices, $req, &$found_count, $relaxation_level) {
    global $timeout_reached;
    if ($timeout_reached || $found_count >= 15) return;

    $date = $calendar_days[$day_idx]['date'];
    $is_weekend_or_holiday = ($calendar_days[$day_idx]['day_type'] !== 'Weekday');
    $morning_code = $is_weekend_or_holiday ? 'Mw' : 'M';
    $night_code = $is_weekend_or_holiday ? 'Nw' : 'N';

    if ($idx >= count($working_rotating)) {
        $valid_choices[] = array_combine(array_column($working_rotating, 'emp_id'), array_map(fn($emp) => $roster[$emp['emp_id']][$date], $working_rotating));
        $found_count++;
        return;
    }

    $emp = $working_rotating[$idx];
    $emp_id = $emp['emp_id'];
    $gender = $emp['gender'];
    $skill = $emp['skill_level'];
    $role = isset($emp['role']) ? $emp['role'] : 'Rotating';

    $needed = 4 - ($off_counts[$emp_id] ?? 0);
    $prev_date = ($day_idx > 0) ? $calendar_days[$day_idx - 1]['date'] : null;
    $prev_prev_date = ($day_idx > 1) ? $calendar_days[$day_idx - 2]['date'] : null;
    $days_left_in_month = count($calendar_days) - 1 - $day_idx;

    $prev_shift = $prev_date ? ($roster[$emp_id][$prev_date] ?? null) : null;
    $prev_prev_shift = $prev_prev_date ? ($roster[$emp_id][$prev_prev_date] ?? null) : null;

    $opts = [];
    if ($role === 'Manager') {
        $nw_count_so_far = 0;
        for ($d = 0; $d < $day_idx; $d++) {
            if (($roster[$emp_id][$calendar_days[$d]['date']] ?? null) === 'Nw') $nw_count_so_far++;
        }
        $opts = ['F'];
        if ($nw_count_so_far < 4) $opts[] = 'Nw'; 
    } else {
        $opts = [$morning_code, $night_code, 'F']; 
    }

    $final_opts = [];
    foreach ($opts as $sc) {
        $is_night = in_array($sc, ['N', 'Nw']);
        $is_morning = in_array($sc, ['M', 'Mw']);
        $is_full = ($sc === 'F');
        
        $prev_is_night = in_array($prev_shift, ['N', 'Nw']);
        $prev_prev_is_night = in_array($prev_prev_shift, ['N', 'Nw']);

        if ($role === 'Rotating') {
            if ($relaxation_level < 3) {
                if (($is_morning || $is_full) && $prev_is_night) continue; 
                if ($is_night && $prev_is_night && $prev_prev_is_night) continue; 
                if ($is_night && $needed == 0) {
                    if ($prev_is_night && $days_left_in_month > 0) continue; 
                    elseif (!$prev_is_night && $days_left_in_month > 1) continue; 
                }
            }
        }
        $final_opts[] = $sc;
    }

    if (empty($final_opts) && $relaxation_level >= 3) {
        $final_opts = ['F', $morning_code, $night_code];
    } elseif (empty($final_opts)) {
        return; 
    }

    $remaining_males = 0; $remaining_females = 0; $remaining_good = 0;
    for ($i = $idx + 1; $i < count($working_rotating); $i++) {
        if ($working_rotating[$i]['gender'] === 'Male') $remaining_males++;
        else $working_rotating[$i]['gender'] === 'Female' ? $remaining_females++ : null;
        if ($working_rotating[$i]['skill_level'] === 'Good') $remaining_good++;
    }
    $remaining_staff = count($working_rotating) - ($idx + 1);

    shuffle($final_opts);

    foreach ($final_opts as $sc) {
        $roster[$emp_id][$date] = $sc;
        
        $added_morning = in_array($sc, ['M', 'Mw', 'F']);
        $added_night = in_array($sc, ['N', 'Nw', 'F']);
        
        if ($added_morning) {
            if ($gender === 'Male') $counts['morning_males']++; else $counts['morning_females']++;
            if ($skill === 'Good') $counts['morning_good']++;
            $counts['morning_total']++;
        }
        if ($added_night) {
            if ($gender === 'Male') $counts['night_males']++; else $counts['night_females']++;
            if ($skill === 'Good') $counts['night_good']++;
            $counts['night_total']++;
        }

        $possible = true;
        if ($relaxation_level < 3) {
            if ($counts['morning_males'] + $remaining_males < $req['m_males']) $possible = false;
            if ($counts['morning_females'] + $remaining_females < $req['m_females']) $possible = false;
            if ($counts['night_males'] + $remaining_males < $req['n_males']) $possible = false;
            if ($counts['night_females'] + $remaining_females < $req['n_females']) $possible = false;
            if ($counts['morning_good'] + $remaining_good < $req['m_good']) $possible = false;
            if ($counts['night_good'] + $remaining_good < $req['n_good']) $possible = false;
            if ($counts['morning_total'] + $remaining_staff < $req['m_total']) $possible = false;
            if ($counts['night_total'] + $remaining_staff < $req['n_total']) $possible = false;
        }

        if ($possible) solveRotatingShifts($idx + 1, $working_rotating, $day_idx, $calendar_days, $roster, $employees, $counts, $off_counts, $valid_choices, $req, $found_count, $relaxation_level);

        if ($added_morning) {
            if ($gender === 'Male') $counts['morning_males']--; else $counts['morning_females']--;
            if ($skill === 'Good') $counts['morning_good']--;
            $counts['morning_total']--;
        }
        if ($added_night) {
            if ($gender === 'Male') $counts['night_males']--; else $counts['night_females']--;
            if ($skill === 'Good') $counts['night_good']--;
            $counts['night_total']--;
        }
        $roster[$emp_id][$date] = null;
    }
}

function solveRosterJoint($day_idx, $calendar_days, $employees, &$roster, &$off_counts, $approved_leaves, $male_anchor_ids, $relaxation_level) {
    global $max_day_reached, $iteration_count, $timeout_reached, $deepest_fail_reason;
    if ($timeout_reached) return false;
    
    $iteration_count++;
    if ($iteration_count > 5000) {  
        $timeout_reached = true; 
        return false;
    }
    if ($day_idx >= count($calendar_days)) return true;

    $day = $calendar_days[$day_idx];
    $date = $day['date'];
    $is_weekend_or_holiday = ($day['day_type'] !== 'Weekday');
    $days_remaining = count($calendar_days) - $day_idx;
    $prev_date = ($day_idx > 0) ? $calendar_days[$day_idx - 1]['date'] : null;
    $prev_prev_date = ($day_idx > 1) ? $calendar_days[$day_idx - 2]['date'] : null;

    if ($day_idx > $max_day_reached) {
        $max_day_reached = $day_idx;
        $pct = 15 + min(75, round((($day_idx + 1) / count($calendar_days)) * 75));
        updateProgress($pct, "Calculating schedule for Day " . ($day_idx + 1) . "...", 'processing', $roster);
    }

    $emp_lookup = [];
    foreach ($employees as $e) $emp_lookup[$e['emp_id']] = $e;

    // Calculate last off distances
    $last_off_dist = [];
    foreach ($employees as $emp) {
        $eid = $emp['emp_id']; $last_off = -1;
        for ($d = $day_idx - 1; $d >= 0; $d--) {
            if (($roster[$eid][$calendar_days[$d]['date']] ?? null) === 'Off') { $last_off = $d; break; }
        }
        $last_off_dist[$eid] = ($last_off === -1) ? ($day_idx + 1) : ($day_idx - $last_off);
    }

    // Partition into Office Employees and Non-Office Employees
    $office_employees = [];
    $non_office_employees = [];
    foreach ($employees as $emp) {
        $role = isset($emp['role']) ? $emp['role'] : 'Rotating';
        if (!in_array($role, ['Manager', 'Assistant_Manager', 'Cashier'])) {
            $office_employees[] = $emp;
        } else {
            $non_office_employees[] = $emp;
        }
    }

    // Determine Off group for Office Employees:
    // We want exactly 5 to work.
    $off_needed_office = count($office_employees) - 5;
    if ($off_needed_office < 0) $off_needed_office = 0;

    $forced_off_office = [];
    $candidate_off_office = [];
    foreach ($office_employees as $emp) {
        $eid = $emp['emp_id'];
        $needed = 4 - ($off_counts[$eid] ?? 0);
        
        if (isset($approved_leaves[$eid][$date])) {
            $forced_off_office[] = $eid;
        } else {
            $remaining_leaves_count = 0;
            for ($i = $day_idx; $i < count($calendar_days); $i++) {
                if (isset($approved_leaves[$eid][$calendar_days[$i]['date']])) $remaining_leaves_count++;
            }
            $needed_scheduled = $needed - $remaining_leaves_count;
            
            if ($days_remaining <= $needed_scheduled && $needed_scheduled > 0) {
                $forced_off_office[] = $eid;
            } else {
                $candidate_off_office[] = [
                    'emp_id' => $eid,
                    'off_count' => $off_counts[$eid] ?? 0,
                    'last_off_dist' => $last_off_dist[$eid]
                ];
            }
        }
    }

    $selected_off_office = $forced_off_office;
    if (count($selected_off_office) < $off_needed_office && !empty($candidate_off_office)) {
        // Sort office candidates to keep off days balanced:
        // 1. Lower off_count first
        // 2. Higher last_off_dist first
        usort($candidate_off_office, function($a, $b) {
            if ($a['off_count'] !== $b['off_count']) {
                return $a['off_count'] <=> $b['off_count'];
            }
            return $b['last_off_dist'] <=> $a['last_off_dist'];
        });
        
        $additional_needed = $off_needed_office - count($selected_off_office);
        $added = array_slice($candidate_off_office, 0, $additional_needed);
        foreach ($added as $cand) {
            $selected_off_office[] = $cand['emp_id'];
        }
    }

    // Determine Off/forced-off for Non-Office Employees:
    $forced_off_non_office = [];
    $candidate_off_non_office = [];
    foreach ($non_office_employees as $emp) {
        $eid = $emp['emp_id'];
        $needed = 4 - ($off_counts[$eid] ?? 0);
        
        if (isset($approved_leaves[$eid][$date])) {
            $forced_off_non_office[] = $eid;
        } else {
            $remaining_leaves_count = 0;
            for ($i = $day_idx; $i < count($calendar_days); $i++) {
                if (isset($approved_leaves[$eid][$calendar_days[$i]['date']])) $remaining_leaves_count++;
            }
            $needed_scheduled = $needed - $remaining_leaves_count;
            
            if ($days_remaining <= $needed_scheduled && $needed_scheduled > 0) {
                $forced_off_non_office[] = $eid;
            } else if (($off_counts[$eid] ?? 0) < 4) {
                $candidate_off_non_office[] = [
                    'emp_id' => $eid,
                    'off_count' => $off_counts[$eid] ?? 0,
                    'last_off_dist' => $last_off_dist[$eid]
                ];
            }
        }
    }

    // Generate combinations of off days for non-office staff
    $combinations_non_office = [[]];
    foreach ($candidate_off_non_office as $cand) {
        $new_combos = [];
        foreach ($combinations_non_office as $combo) {
            $new_combos[] = array_merge($combo, [$cand['emp_id']]);
        }
        $combinations_non_office = array_merge($combinations_non_office, $new_combos);
    }
    
    $valid_non_office_off_groups = [];
    foreach ($combinations_non_office as $combo) {
        $group = array_merge($forced_off_non_office, $combo);
        
        // Assistant Manager and Cashier cannot be off at the same time
        $has_cashier_off = false;
        $has_asst_mgr_off = false;
        
        foreach ($group as $eid) {
            $role = isset($emp_lookup[$eid]['role']) ? $emp_lookup[$eid]['role'] : '';
            if ($role === 'Cashier') $has_cashier_off = true;
            if ($role === 'Assistant_Manager') $has_asst_mgr_off = true;
        }
        
        if (!($has_cashier_off && $has_asst_mgr_off)) {
            $valid_non_office_off_groups[] = $group;
        }
    }
    
    if (empty($valid_non_office_off_groups)) {
        $valid_non_office_off_groups = [$forced_off_non_office];
    }
    
    // Sort to prioritize smaller off groups for non-office to keep coverage high
    usort($valid_non_office_off_groups, function($a, $b) {
        return count($a) <=> count($b);
    });

    // Try each combined off group
    foreach ($valid_non_office_off_groups as $non_office_off_group) {
        $off_group = array_merge($selected_off_office, $non_office_off_group);
        
        foreach ($off_group as $eid) $roster[$eid][$date] = 'Off';
        
        $temp_roster = [];
        $working_rotating = [];
        
        foreach ($employees as $emp) {
            $emp_id = $emp['emp_id'];
            if (in_array($emp_id, $off_group)) continue;
            
            $role = isset($emp['role']) ? $emp['role'] : 'Rotating';
            
            if (!in_array($role, ['Manager', 'Assistant_Manager', 'Cashier'])) {
                // Office Employee: Must be assigned 'F' (Full Day)
                $temp_roster[$emp_id] = 'F';
            } else {
                if ($role === 'Cashier') {
                    $day_of_week = (int)date('N', strtotime($date));
                    $nh_count = 0;
                    for ($d = 0; $d < $day_idx; $d++) {
                        if (($roster[$emp_id][$calendar_days[$d]['date']] ?? null) === 'Nh') $nh_count++;
                    }
                    $temp_roster[$emp_id] = (in_array($day_of_week, [3, 5]) && $nh_count < 5) ? 'Nh' : 'No';
                } elseif ($role === 'Assistant_Manager') {
                    // Assistant Manager assignment
                    $day_of_week = (int)date('N', strtotime($date));
                    $temp_roster[$emp_id] = in_array($day_of_week, [1, 3, 5]) ? ($is_weekend_or_holiday ? 'Mw' : 'M') : 'F';
                } else {
                    $working_rotating[] = $emp;
                }
            }
        }

        // Calculate max stats for solver bounds
        $max_m_males = 0; $max_n_males = 0; $max_m_females = 0; $max_n_females = 0;
        $max_m_good = 0; $max_n_good = 0; $max_m_total = 0; $max_n_total = 0;

        foreach ($temp_roster as $eid => $sc) {
            $gender = $emp_lookup[$eid]['gender'] ?? 'Female';
            $skill = $emp_lookup[$eid]['skill_level'] ?? 'Normal';
            if (in_array($sc, ['M', 'Mw', 'F', 'No', 'Nh'])) {
                if ($gender === 'Male') $max_m_males++; else $max_m_females++;
                if ($skill === 'Good') $max_m_good++; $max_m_total++;
            }
            if (in_array($sc, ['N', 'Nw', 'F', 'Nh'])) {
                if ($gender === 'Male') $max_n_males++; else $max_n_females++;
                if ($skill === 'Good') $max_n_good++; $max_n_total++;
            }
        }

        foreach ($working_rotating as $emp) {
            $eid = $emp['emp_id']; $role = isset($emp['role']) ? $emp['role'] : 'Rotating';
            $prev_shift = $roster[$eid][$prev_date] ?? null;
            $prev_prev_shift = $roster[$eid][$prev_prev_date] ?? null;
            
            if ($relaxation_level >= 3 || $role === 'Manager') {
                $can_morning = true; $can_night = true;
            } else {
                $can_morning = !in_array($prev_shift, ['N', 'Nw']);
                $can_night = !(in_array($prev_shift, ['N', 'Nw']) && in_array($prev_prev_shift, ['N', 'Nw']));
            }
            
            $is_male = ($emp['gender'] === 'Male'); $is_good = ($emp['skill_level'] === 'Good');
            if ($can_morning) {
                if ($is_male) $max_m_males++; else $max_m_females++;
                if ($is_good) $max_m_good++; $max_m_total++;
            }
            if ($can_night) {
                if ($is_male) $max_n_males++; else $max_n_females++;
                if ($is_good) $max_n_good++; $max_n_total++;
            }
        }

        $total_working_count = count($temp_roster) + count($working_rotating);
        $target_m_total = min($is_weekend_or_holiday ? 9 : 8, $total_working_count);
        $target_n_total = min($is_weekend_or_holiday ? 9 : 8, $total_working_count);
        
        if ($relaxation_level >= 1) {
            $target_m_total = max(3, $target_m_total - 1);
            $target_n_total = max(3, $target_n_total - 1);
        }
        
        $gender_min_males = ($relaxation_level >= 2) ? 2 : 3;
        $gender_min_fems = ($relaxation_level >= 2) ? 1 : 2;

        if ($relaxation_level >= 3) {
            $req = ['m_males' => 0, 'n_males' => 0, 'm_females' => 0, 'n_females' => 0, 'm_good' => 0, 'n_good' => 0, 'm_total' => 1, 'n_total' => 1];
        } else {
            $req = [
                'm_males' => min($gender_min_males, $max_m_males), 'n_males' => min($gender_min_males, $max_n_males),
                'm_females' => min($gender_min_fems, $max_m_females), 'n_females' => min($gender_min_fems, $max_n_females),
                'm_good' => min(1, $max_m_good), 'n_good' => min(1, $max_n_good),
                'm_total' => min($target_m_total, $max_m_total), 'n_total' => min($target_n_total, $max_n_total)
            ];
        }

        $counts = ['morning_males' => 0, 'morning_females' => 0, 'night_males' => 0, 'night_females' => 0, 'morning_good' => 0, 'night_good' => 0, 'morning_total' => 0, 'night_total' => 0];
        
        foreach ($temp_roster as $eid => $sc) {
            $roster[$eid][$date] = $sc;
            $gender = $emp_lookup[$eid]['gender'] ?? 'Female';
            $skill = $emp_lookup[$eid]['skill_level'] ?? 'Normal';
            if (in_array($sc, ['M', 'Mw', 'F', 'No', 'Nh'])) {
                if ($gender === 'Male') $counts['morning_males']++; else $counts['morning_females']++;
                if ($skill === 'Good') $counts['morning_good']++; $counts['morning_total']++;
            }
            if (in_array($sc, ['N', 'Nw', 'F', 'Nh'])) {
                if ($gender === 'Male') $counts['night_males']++; else $counts['night_females']++;
                if ($skill === 'Good') $counts['night_good']++; $counts['night_total']++;
            }
        }

        $valid_choices = []; $found_count = 0; 
        
        // Pre-calculate history for AM/Manager choices scoring
        $emp_history = [];
        foreach ($working_rotating as $emp) {
            $eid = $emp['emp_id'];
            $m_cnt = 0; $n_cnt = 0; $f_cnt = 0;
            for ($d = 0; $d < $day_idx; $d++) {
                $s = $roster[$eid][$calendar_days[$d]['date']] ?? null;
                if (in_array($s, ['M', 'Mw'])) $m_cnt++;
                if (in_array($s, ['N', 'Nw'])) $n_cnt++;
                if ($s === 'F') $f_cnt++;
            }
            $emp_history[$eid] = [
                'm_cnt' => $m_cnt,
                'n_cnt' => $n_cnt,
                'f_cnt' => $f_cnt,
                'this_week_shift' => null,
                'last_week_shift' => null
            ];
        }

        solveRotatingShifts(0, $working_rotating, $day_idx, $calendar_days, $roster, $employees, $counts, $off_counts, $valid_choices, $req, $found_count, $relaxation_level);

        if (!empty($valid_choices)) {
            $scored_choices = [];
            $base_m = $counts['morning_total']; $base_n = $counts['night_total'];
            
            foreach ($valid_choices as $choice) {
                $score = 0; $day_m = $base_m; $day_n = $base_n;
                
                foreach ($choice as $emp_id => $sc) {
                    if (in_array($sc, ['M', 'Mw', 'F'])) $day_m++;
                    if (in_array($sc, ['N', 'Nw', 'F'])) $day_n++;
                    
                    $role = isset($emp_lookup[$emp_id]['role']) ? $emp_lookup[$emp_id]['role'] : 'Rotating'; 
                    $history = $emp_history[$emp_id] ?? null;
                    if ($history) {
                        if (in_array($sc, ['M', 'Mw'])) $score -= ($history['m_cnt'] * 4);
                        if (in_array($sc, ['N', 'Nw'])) $score -= ($history['n_cnt'] * 4);
                        if ($sc === 'F') $score -= ($history['f_cnt'] * 6);
                    }
                }
                
                $score -= abs($day_m - $day_n) * 20; 
                $scored_choices[] = ['choice' => $choice, 'score' => $score];
            }
            
            shuffle($scored_choices);
            usort($scored_choices, fn($a, $b) => $b['score'] <=> $a['score']);
            $scored_choices = array_slice($scored_choices, 0, 4);

            foreach ($scored_choices as $sc_choice) {
                $choice = $sc_choice['choice'];
                foreach ($choice as $emp_id => $sc) $roster[$emp_id][$date] = $sc;
                foreach ($off_group as $eid) $off_counts[$eid] = ($off_counts[$eid] ?? 0) + 1;

                if (solveRosterJoint($day_idx + 1, $calendar_days, $employees, $roster, $off_counts, $approved_leaves, $male_anchor_ids, $relaxation_level)) return true;

                foreach ($off_group as $eid) $off_counts[$eid]--;
                foreach ($choice as $emp_id => $sc) $roster[$emp_id][$date] = null;
                if ($timeout_reached) return false; 
            }
        } else {
            $deepest_fail_reason = "Day " . ($day_idx + 1) . " - Could not satisfy shift bounds (M_target: {$req['m_total']}, N_target: {$req['n_total']}).";
        }

        foreach ($temp_roster as $emp_id => $sc) $roster[$emp_id][$date] = null;
        foreach ($off_group as $eid) $roster[$eid][$date] = null;
        if ($timeout_reached) return false; 
    }
    return false;
}
?>