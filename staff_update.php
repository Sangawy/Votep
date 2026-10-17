<?php
include 'config.php';
session_start();
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    
    // Debug log
    file_put_contents('debug_post.log', print_r($_POST, true));
    $stmt_old = $conn->prepare("SELECT * FROM staff_members WHERE id = ?");
    $stmt_old->bind_param("i", $id);
    $stmt_old->execute();
    $old_data = $stmt_old->get_result()->fetch_assoc();
    $stmt_old->close();

    $rank_id = (int)$_POST['rank_id'];
    $full_name = trim($_POST['full_name']);
    $unit_name = trim($_POST['unit_name']);
    $sub_unit = trim($_POST['sub_unit'] ?? '');
    $position = trim($_POST['position']);
    $birth_year = (int)$_POST['birth_year'];
    $birth_place = trim($_POST['birth_place']);
    $marital_status = $_POST['marital_status'];
    $children_count = (int)$_POST['children_count'];
    $branch = trim($_POST['branch']);
    $military_branch_sinf = $_POST['military_branch_sinf'];
    $education = trim($_POST['education']);
    $military_education = trim($_POST['military_education']);
    
    // Handle Languages from checkboxes
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
    $id_number = trim($_POST['id_number']);
    $appointment_order = trim($_POST['appointment_order']);
    $appointment_date = !empty($_POST['appointment_date']) ? $_POST['appointment_date'] : null;
    $civil_id = trim($_POST['civil_id']);
    $form_number = trim($_POST['form_number']);
    $voting_number = trim($_POST['voting_number']);
    $biometric_number = trim($_POST['biometric_number']);
    $city = trim($_POST['city']);
    $town = trim($_POST['town']);
    $district = trim($_POST['district']);
    $village = trim($_POST['village']);
    $notes = trim($_POST['notes'] ?? '');

    // Kept Extra Fields
    $gender = $_POST['gender'] ?? 'نێر';
    $education_level = trim($_POST['education_level'] ?? '');
    $job = trim($_POST['job'] ?? '');

    // Handle Unit
    $unit_id = 0;
    $unit_res = $conn->query("SELECT id FROM staff_units WHERE unit_name = '" . $conn->real_escape_string($unit_name) . "'");
    if ($unit_res->num_rows > 0) {
        $unit_id = $unit_res->fetch_assoc()['id'];
    } else {
        $conn->query("INSERT INTO staff_units (unit_name) VALUES ('" . $conn->real_escape_string($unit_name) . "')");
        $unit_id = $conn->insert_id;
    }

    // Photo Handling
    $image_path = $old_data['profile_image'];
    $final_archive_path = $old_data['archive_folder'];
    if (empty($final_archive_path)) {
        $final_archive_path = "assets/uploads/staff_" . time();
    }

    if (isset($_POST['delete_photo']) && $_POST['delete_photo'] == '1') {
        if (!empty($image_path) && file_exists($image_path) && strpos($image_path, 'assets/') === false) {
            @unlink($image_path);
        }
        $image_path = "";
    }

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $new_name = "profile_" . time() . "." . $ext;
        if (!is_dir($final_archive_path)) mkdir($final_archive_path, 0777, true);
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $final_archive_path . DIRECTORY_SEPARATOR . $new_name)) {
            $image_path = $final_archive_path . DIRECTORY_SEPARATOR . $new_name;
        }
    }

    // Update DB - Using backticks for all columns to avoid reserved word conflicts like 'position'
    $cols = "`rank_id`=?, `full_name`=?, `gender`=?, `unit_id`=?, `sub_unit`=?, `position`=?, `job`=?, `birth_year`=?, `birth_place`=?, `marital_status`=?, `children_count`=?, `branch`=?, `military_branch_sinf`=?, `education`=?, `education_level`=?, `military_education`=?, `languages`=?, `phone`=?, `blood_group`=?, `physical_fitness`=?, `health_status`=?, `honors_medals`=?, `injuries`=?, `last_promotion_date`=?, `promotion_due_date`=?, `id_number`=?, `appointment_order`=?, `appointment_date`=?, `civil_id`=?, `form_number`=?, `voting_number`=?, `biometric_number`=?, `city`=?, `town`=?, `district`=?, `village`=?, `profile_image`=?, `archive_folder`=?, `notes`=?";
    
    $accurate_types = str_repeat("s", 40);
    
    $stmt = $conn->prepare("UPDATE staff_members SET $cols WHERE id=?");
    $stmt->bind_param($accurate_types, 
        $rank_id, $full_name, $gender, $unit_id, $sub_unit, $position, $job, $birth_year, $birth_place, $marital_status, $children_count, 
        $branch, $military_branch_sinf, $education, $education_level, $military_education, $languages, $phone, $blood_group, 
        $physical_fitness, $health_status, $honors_medals, $injuries, $last_promotion_date, $promotion_due_date, 
        $id_number, $appointment_order, $appointment_date, $civil_id, $form_number, $voting_number, $biometric_number, 
        $city, $town, $district, $village, $image_path, $final_archive_path, $notes, $id
    );
    
    if ($stmt->execute()) {
        include_once 'includes/audit_logger.php';
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'staff_members', $id, $old_data, $_POST);

        // Update Service History
        $conn->query("DELETE FROM staff_service_history WHERE staff_id = $id");
        if (isset($_POST['service_unit']) && is_array($_POST['service_unit'])) {
            $stmt_service = $conn->prepare("INSERT INTO staff_service_history (staff_id, unit_name, position, service_period, order_number) VALUES (?, ?, ?, ?, ?)");
            foreach ($_POST['service_unit'] as $index => $s_unit) {
                $s_unit = trim($s_unit);
                if (empty($s_unit)) continue;
                $s_pos = trim($_POST['service_position'][$index] ?? '');
                $s_period = trim($_POST['service_period'][$index] ?? '');
                $s_order = trim($_POST['service_order'][$index] ?? '');
                $stmt_service->bind_param("issss", $id, $s_unit, $s_pos, $s_period, $s_order);
                $stmt_service->execute();
            }
            $stmt_service->close();
        }

        header("Location: staff_list.php?updated=1&id=$id");
    } else {
        die("Error updating: " . $stmt->error);
    }
    $stmt->close();
} else {
    header("Location: staff_list.php");
}
?>
