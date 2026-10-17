<?php
require_once __DIR__ . '/config.php';
$c = getDB();
$res = $c->query("SELECT id, full_name, first_name, father_name, grandfather_name, great_grandfather_name, parent_id FROM members ORDER BY id DESC LIMIT 50");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id']} | full_name: '{$r['full_name']}' | first: '{$r['first_name']}' | fa: '{$r['father_name']}' | g: '{$r['grandfather_name']}' | gg: '{$r['great_grandfather_name']}' | p: {$r['parent_id']}\n";
}
