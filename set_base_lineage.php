<?php
require_once 'config.php';
$conn = getDB();

$baseLineage = "السيد محمد الملقب ب (بيرخضر الشاهو) بن السيد محمود المديني بن السيد جعفر بن السيد حسين بن السيد محمود الينبوعي العاصم بن السيد رحمة الله المعروف بالعارف بالله بن السيد موسى الداعي الى الله بن السيد حسين الصامت بن السيد هلال بن السيد محمد الملقب بالخلف بن السيد منهال بن السيد ماجد بن السيد عبدالرحمن بن السيد قاسم بن السيد ادریس ابن السلطان جعفر بن السيد الامام علي النقي بن السيد الامام محمد الجواد التقي بن السيد الامام علي الرضا بن السيد الامام موسى الكاظم بن السيد الامام جعفر الصادق بن السيد الامام محمد الباقر بن السيد الامام زين العابدين بن السيد الامام حسين الشهيد بن السيد الامام علي بن ابي طالب زوج فاطمة الزهراء بنت محمد رسول الله بن عبدالله ـ صلى الله عليه و سلم ـ";

// Ensure column exists
$conn->query("ALTER TABLE settings ADD COLUMN IF NOT EXISTS base_lineage TEXT AFTER description");

$stmt = $conn->prepare("UPDATE settings SET base_lineage = ? WHERE id = 1");
$stmt->bind_param("s", $baseLineage);
if ($stmt->execute()) {
    echo "Base lineage updated successfully.";
} else {
    echo "Error: " . $conn->error;
}
unlink(__FILE__); // Delete this script after execution
