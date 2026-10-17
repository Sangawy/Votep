<?php
include 'config.php';
session_start();
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Basic Info
    $rank_id = (int)$_POST['rank_id'];
    $full_name = trim($_POST['full_name']);
    $unit_name = trim($_POST['unit_name']);
    $sub_unit = trim($_POST['sub_unit'] ?? '');
    $position = trim($_POST['position']);
    $birth_year = (int)$_POST['birth_year'];
    $birth_place = trim($_POST['birth_place']);
    $marital_status = $_POST['marital_status'];
    $children_count = (int)$_POST['children_count'];
    
    // 2. Professional Info
    $branch = trim($_POST['branch']);
    $military_branch_sinf = $_POST['military_branch_sinf'];
    $education = trim($_POST['education']);
    $military_education = trim($_POST['military_education']);
    
    // 3. Languages - Handle array of checkboxes
    $languages_arr = [];
    if (isset($_POST['langs']) && is_array($_POST['langs'])) {
        foreach ($_POST['langs'] as $l) {
            $l_key = '';
            if ($l == 'کوردی') $l_key = 'kurdi';
            elseif ($l == 'عەرەبی') $l_key = 'arabi';
            elseif ($l == 'ئینگلیزی') $l_key = 'english';
            
            $lvl = $_POST['lang_level_' . $l_key] ?? 'باش';
            $languages_arr[] = "$l ($lvl)";
        }
    }
    $languages = implode(', ', $languages_arr);

    $phone = trim($_POST['phone']);
    $blood_group = $_POST['blood_group'];
    $physical_fitness = trim($_POST['physical_fitness']);
    $health_status = trim($_POST['health_status']);
    $honors_medals = trim($_POST['honors_medals']);
    $injuries = trim($_POST['injuries']);
    $last_promotion_date = !empty($_POST['last_promotion_date']) ? $_POST['last_promotion_date'] : null;
    $promotion_due_date = !empty($_POST['promotion_due_date']) ? $_POST['promotion_due_date'] : null;
    
    // 4. Admin Info
    $id_number = trim($_POST['id_number']);
    $appointment_order = trim($_POST['appointment_order']);
    $appointment_date = !empty($_POST['appointment_date']) ? $_POST['appointment_date'] : null;
    $civil_id = trim($_POST['civil_id']);
    $form_number = trim($_POST['form_number']);
    $voting_number = trim($_POST['voting_number']);
    $biometric_number = trim($_POST['biometric_number']);
    
    // 5. Location Info
    $city = trim($_POST['city']);
    $town = trim($_POST['town']);
    $district = trim($_POST['district']);
    $village = trim($_POST['village']);
    $notes = trim($_POST['notes'] ?? '');

    // Kept Extra Fields
    $gender = $_POST['gender'] ?? 'نێر';
    $education_level = trim($_POST['education_level'] ?? '');
    $job = trim($_POST['job'] ?? '');

    // 6. Handle Unit (Auto-create if not exists)
    $unit_id = 0;
    $stmt_unit = $conn->prepare("SELECT id FROM staff_units WHERE unit_name = ?");
    $stmt_unit->bind_param("s", $unit_name);
    $stmt_unit->execute();
    $res_unit = $stmt_unit->get_result();
    if ($res_unit->num_rows > 0) {
        $unit_id = $res_unit->fetch_assoc()['id'];
    } else {
        $stmt_ins = $conn->prepare("INSERT INTO staff_units (unit_name) VALUES (?)");
        $stmt_ins->bind_param("s", $unit_name);
        $stmt_ins->execute();
        $unit_id = $stmt_ins->insert_id;
        $stmt_ins->close();
    }
    $stmt_unit->close();

    // Photo Handling
    $image_path = "";
    $archive_folder = "assets/uploads/staff_" . time();
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        if (!is_dir($archive_folder)) mkdir($archive_folder, 0777, true);
        $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $new_name = "profile_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $archive_folder . DIRECTORY_SEPARATOR . $new_name)) {
            $image_path = $archive_folder . DIRECTORY_SEPARATOR . $new_name;
        }
    }

    // 7. Save to DB - Using backticks for all columns
    $cols = "`rank_id`, `full_name`, `gender`, `unit_id`, `sub_unit`, `position`, `job`, `birth_year`, `birth_place`, `marital_status`, `children_count`, `branch`, `military_branch_sinf`, `education`, `education_level`, `military_education`, `languages`, `phone`, `blood_group`, `physical_fitness`, `health_status`, `honors_medals`, `injuries`, `last_promotion_date`, `promotion_due_date`, `id_number`, `appointment_order`, `appointment_date`, `civil_id`, `form_number`, `voting_number`, `biometric_number`, `city`, `town`, `district`, `village`, `profile_image`, `archive_folder`, `notes`";
    
    $placeholders = str_repeat("?, ", 38) . "?"; // 39 placeholders
    
    $accurate_types = str_repeat("s", 39);
    
    $stmt = $conn->prepare("INSERT INTO staff_members ($cols) VALUES ($placeholders)");
    $stmt->bind_param($accurate_types, 
        $rank_id, $full_name, $gender, $unit_id, $sub_unit, $position, $job, $birth_year, $birth_place, $marital_status, $children_count, 
        $branch, $military_branch_sinf, $education, $education_level, $military_education, $languages, $phone, $blood_group, 
        $physical_fitness, $health_status, $honors_medals, $injuries, $last_promotion_date, $promotion_due_date, 
        $id_number, $appointment_order, $appointment_date, $civil_id, $form_number, $voting_number, $biometric_number, 
        $city, $town, $district, $village, $image_path, $archive_folder, $notes
    );
    
    if ($stmt->execute()) {
        $staff_id = $stmt->insert_id;
        
        include_once 'includes/audit_logger.php';
        logAudit($conn, $_SESSION['user_id'], 'INSERT', 'staff_members', $staff_id, [], $_POST);

        // Update Service History
        if (isset($_POST['service_unit']) && is_array($_POST['service_unit'])) {
            $stmt_service = $conn->prepare("INSERT INTO staff_service_history (staff_id, unit_name, position, service_period, order_number) VALUES (?, ?, ?, ?, ?)");
            foreach ($_POST['service_unit'] as $index => $s_unit) {
                $s_unit = trim($s_unit);
                if (empty($s_unit)) continue;
                $s_pos = trim($_POST['service_position'][$index] ?? '');
                $s_period = trim($_POST['service_period'][$index] ?? '');
                $s_order = trim($_POST['service_order'][$index] ?? '');
                $stmt_service->bind_param("issss", $staff_id, $s_unit, $s_pos, $s_period, $s_order);
                $stmt_service->execute();
            }
            $stmt_service->close();
        }

        header("Location: staff_list.php?success=1");
    } else {
        die("Error saving: " . $stmt->error);
    }
    $stmt->close();
} else {
    header("Location: staff_list.php");
}
?>
