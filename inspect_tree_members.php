<?php
require_once __DIR__ . '/config.php';
$c = getDB();
$res = $c->query("SELECT id, full_name, first_name, father_name, grandfather_name, family_id, parent_id FROM members WHERE full_name LIKE '%مصطف%' OR full_name LIKE '%امیر%' OR full_name LIKE '%ئەمیر%' OR full_name LIKE '%عبدالله%' LIMIT 30");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id']} | name: {$r['full_name']} | first: {$r['first_name']} | father: {$r['father_name']} | grand: {$r['grandfather_name']} | fam: {$r['family_id']} | parent: {$r['parent_id']}\n";
}
