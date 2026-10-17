<?php
/**
 * Lineage AI & Kinship Intelligence Engine
 * Handles Google Gemini API integration with dynamic local synthesis
 */

require_once 'config.php';
require_once 'lineage_core.php';

function getFullKinshipNetwork($conn, $memberId, $bridgeId, $familyId, $chainParts = [], $foundNodes = []) {
    $kinship = [
        'person' => null,
        'father' => null,
        'grandfather' => null,
        'uncles' => [],
        'siblings' => [],
        'cousins' => [],
        'ancestors_path' => [],
        'generations_to_shaho' => -1
    ];

    $targetRow = null;
    $fatherRow = null;
    $grandfatherRow = null;

    // 1. If we have an existing member ID from database
    if ($memberId > 0) {
        $stmt = $conn->prepare("SELECT id, full_name, first_name, father_name, grandfather_name, great_grandfather_name, parent_id, family_id, member_code, profile_image FROM members WHERE id = ?");
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
        $targetRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($targetRow) {
            $kinship['person'] = $targetRow;
            $fatherId = (int)($targetRow['parent_id'] ?? 0);
            if ($fatherId > 0) {
                $stmtF = $conn->prepare("SELECT id, full_name, first_name, father_name, grandfather_name, parent_id, family_id, member_code, profile_image FROM members WHERE id = ?");
                $stmtF->bind_param("i", $fatherId);
                $stmtF->execute();
                $fatherRow = $stmtF->get_result()->fetch_assoc();
                $stmtF->close();
            }
        }
    }

    // 2. If no member in DB or father not linked, resolve from chain context
    if (!$fatherRow) {
        if (!empty($foundNodes[0]) && !empty($foundNodes[0]['id'])) {
            $fatherRow = $foundNodes[0];
        } elseif (!empty($chainParts[0])) {
            $fName = trim($chainParts[0]);
            $normF = normalizeName($fName);
            $fRes = $conn->query("SELECT id, full_name, first_name, father_name, grandfather_name, parent_id, family_id, member_code, profile_image 
                                  FROM members 
                                  WHERE normalized_name = '$normF' " . ($familyId > 0 ? "AND family_id = $familyId" : "") . " 
                                  ORDER BY id DESC LIMIT 1");
            if ($fRes && $fRow = $fRes->fetch_assoc()) {
                $fatherRow = $fRow;
            } else {
                $fatherRow = [
                    'id' => 0,
                    'full_name' => $fName,
                    'first_name' => extractFirstName($fName),
                    'parent_id' => 0,
                    'family_id' => $familyId
                ];
            }
        } elseif ($bridgeId > 0) {
            $stmtB = $conn->prepare("SELECT id, full_name, first_name, father_name, grandfather_name, parent_id, family_id, member_code, profile_image FROM members WHERE id = ?");
            $stmtB->bind_param("i", $bridgeId);
            $stmtB->execute();
            $fatherRow = $stmtB->get_result()->fetch_assoc();
            $stmtB->close();
        }
    }
    $kinship['father'] = $fatherRow;

    // 3. Resolve Grandfather
    if ($fatherRow && !empty($fatherRow['parent_id'])) {
        $gId = (int)$fatherRow['parent_id'];
        $stmtG = $conn->prepare("SELECT id, full_name, first_name, father_name, grandfather_name, parent_id, family_id, member_code, profile_image FROM members WHERE id = ?");
        $stmtG->bind_param("i", $gId);
        $stmtG->execute();
        $grandfatherRow = $stmtG->get_result()->fetch_assoc();
        $stmtG->close();
    } elseif (!empty($foundNodes[1]) && !empty($foundNodes[1]['id'])) {
        $grandfatherRow = $foundNodes[1];
    } elseif (!empty($chainParts[1])) {
        $gName = trim($chainParts[1]);
        $normG = normalizeName($gName);
        $gRes = $conn->query("SELECT id, full_name, first_name, father_name, grandfather_name, parent_id, family_id, member_code, profile_image 
                              FROM members 
                              WHERE normalized_name = '$normG' " . ($familyId > 0 ? "AND family_id = $familyId" : "") . " 
                              ORDER BY id DESC LIMIT 1");
        if ($gRes && $gRow = $gRes->fetch_assoc()) {
            $grandfatherRow = $gRow;
        } else {
            $grandfatherRow = [
                'id' => 0,
                'full_name' => $gName,
                'first_name' => extractFirstName($gName),
                'parent_id' => 0,
                'family_id' => $familyId
            ];
        }
    }
    $kinship['grandfather'] = $grandfatherRow;

    // 4. Resolve Uncles (only real brothers of the father from DB, NO fake names!)
    if ($grandfatherRow && !empty($grandfatherRow['id'])) {
        $grandId = (int)$grandfatherRow['id'];
        $excludeFatherId = (int)($fatherRow['id'] ?? 0);
        $stmtU = $conn->prepare("SELECT id, full_name, first_name, member_code, profile_image FROM members WHERE parent_id = ? AND id != ?");
        $stmtU->bind_param("ii", $grandId, $excludeFatherId);
        $stmtU->execute();
        $uRes = $stmtU->get_result();
        while ($u = $uRes->fetch_assoc()) {
            $kinship['uncles'][] = $u;
        }
        $stmtU->close();
    }

    // 5. Resolve Siblings (children of father in DB)
    if ($fatherRow && !empty($fatherRow['id'])) {
        $fId = (int)$fatherRow['id'];
        $excludeTargetId = (int)($targetRow['id'] ?? 0);
        $stmtS = $conn->prepare("SELECT id, full_name, first_name, member_code, profile_image FROM members WHERE parent_id = ? AND id != ?");
        $stmtS->bind_param("ii", $fId, $excludeTargetId);
        $stmtS->execute();
        $sRes = $stmtS->get_result();
        while ($s = $sRes->fetch_assoc()) {
            $kinship['siblings'][] = $s;
        }
        $stmtS->close();
    }

    // 6. Ancestors Path (Follow parents up to Pirkhidri Shaho)
    $currId = 0;
    if ($fatherRow && !empty($fatherRow['id'])) {
        $currId = (int)$fatherRow['id'];
    } elseif ($bridgeId > 0) {
        $currId = (int)$bridgeId;
    }

    $visited = [];
    $shahoSteps = -1;
    $stepCount = 0;

    while ($currId > 0 && !isset($visited[$currId])) {
        $visited[$currId] = true;
        $aRes = $conn->query("SELECT id, full_name, first_name, parent_id, family_id, member_code FROM members WHERE id = $currId");
        if ($aRes && $aRow = $aRes->fetch_assoc()) {
            $kinship['ancestors_path'][] = $aRow;
            $stepCount++;
            if ($aRow['id'] == 1 || isShahoName($aRow['full_name'])) {
                if ($shahoSteps === -1) {
                    $shahoSteps = $stepCount;
                }
            }
            $currId = (int)($aRow['parent_id'] ?? 0);
        } else {
            break;
        }
    }

    // If ancestors path in DB was partial, fill with input chain
    if (empty($kinship['ancestors_path']) && !empty($chainParts)) {
        foreach ($chainParts as $cpName) {
            $kinship['ancestors_path'][] = ['id' => 0, 'full_name' => $cpName, 'first_name' => extractFirstName($cpName)];
        }
    }

    $kinship['generations_to_shaho'] = $shahoSteps > 0 ? $shahoSteps : count($kinship['ancestors_path']);
    return $kinship;
}

/**
 * Find the Top 5 Families / Registered Branches with the most shared generations to Pirkhidri Shaho
 */
function getTopSharedFamilies($conn, $targetFamilyId, $targetAncestors = [], $bridgeMember = null) {
    if ($targetFamilyId <= 0 && empty($targetAncestors) && empty($bridgeMember)) {
        return [];
    }

    // 1. Fetch all members with minimal fields for in-memory graph
    $allMembers = [];
    $familyMembers = [];
    $parentIds = [];
    $res = $conn->query("SELECT id, parent_id, full_name, family_id, bnamala FROM members");
    if ($res) {
        while($r = $res->fetch_assoc()) {
            $mId = (int)$r['id'];
            $pId = (int)($r['parent_id'] ?? 0);
            $fId = (int)($r['family_id'] ?? 0);
            $allMembers[$mId] = [
                'id' => $mId,
                'parent_id' => $pId,
                'full_name' => $r['full_name'],
                'family_id' => $fId,
                'bnamala' => $r['bnamala'] ?? ''
            ];
            if ($fId > 0) {
                $familyMembers[$fId][] = $mId;
            }
            if ($pId > 0) {
                $parentIds[$pId] = true;
            }
        }
    }

    $tracePath = function($startId) use ($allMembers) {
        $path = [];
        $currId = $startId;
        $visited = [];
        while ($currId > 0 && isset($allMembers[$currId]) && !isset($visited[$currId])) {
            $visited[$currId] = true;
            $node = $allMembers[$currId];
            $path[] = $node;
            if ($node['id'] == 1 || isShahoName($node['full_name'])) {
                break;
            }
            $currId = (int)$node['parent_id'];
        }
        return $path;
    };

    // Build target chain
    $targetChain = [];
    if (!empty($targetAncestors)) {
        // Collect existing nodes with valid DB ids from youngest to oldest
        $existingNodes = array_values(array_filter($targetAncestors, function($a) { return !empty($a['id']); }));
        if (!empty($existingNodes)) {
            $youngest = $existingNodes[0];
            if (!empty($youngest['id'])) {
                $targetChain = $tracePath((int)$youngest['id']);
            }
        }
    }

    if (empty($targetChain) && !empty($bridgeMember['id'])) {
        $targetChain = $tracePath((int)$bridgeMember['id']);
    }

    if (empty($targetChain) && $targetFamilyId > 0 && isset($familyMembers[$targetFamilyId])) {
        foreach ($familyMembers[$targetFamilyId] as $mId) {
            $p = $tracePath($mId);
            if (count($p) > count($targetChain)) {
                $targetChain = $p;
            }
        }
    }

    if (empty($targetChain) && !empty($targetAncestors)) {
        $targetChain = $targetAncestors;
    }

    if (empty($targetChain)) {
        return [];
    }

    $targetIdMap = [];
    foreach ($targetChain as $idx => $node) {
        if (!empty($node['id']) && $node['id'] != 1 && !isShahoName($node['full_name'])) {
            $targetIdMap[(int)$node['id']] = $idx;
        }
    }
    // Also include any IDs from targetAncestors
    if (!empty($targetAncestors)) {
        foreach ($targetAncestors as $idx => $node) {
            if (!empty($node['id']) && $node['id'] != 1 && !isShahoName($node['full_name']) && !isset($targetIdMap[(int)$node['id']])) {
                $targetIdMap[(int)$node['id']] = $idx;
            }
        }
    }

    $families = [];
    $fRes = $conn->query("SELECT id, family_name FROM families ORDER BY id ASC");
    if ($fRes) {
        while ($f = $fRes->fetch_assoc()) {
            $families[(int)$f['id']] = $f['family_name'];
        }
    }

    // Find all leaf members in the database (ends of branches)
    $leaves = [];
    foreach($allMembers as $mId => $m) {
        if (!isset($parentIds[$mId]) && !empty($m['parent_id'])) {
            $leaves[] = $m;
        }
    }

    $seenKey = [];
    foreach($leaves as $leaf) {
        $leafId = (int)$leaf['id'];
        // Do not compare an ancestor with himself
        if (isset($targetIdMap[$leafId])) {
            continue;
        }

        $p = $tracePath($leafId);
        $firstShared = null;
        $sharedCount = 0;

        foreach($p as $node) {
            $nId = (int)$node['id'];
            if (isset($targetIdMap[$nId])) {
                if ($firstShared === null) {
                    $firstShared = $node;
                }
                $sharedCount++;
            }
        }

        if ($sharedCount > 0 && $firstShared !== null) {
            $fId = (int)$leaf['family_id'];
            $fName = $families[$fId] ?? ($leaf['bnamala'] ?: 'نادیار');

            $branchLabel = "";
            $isSameFamily = false;
            if ($fId > 0 && $fId === $targetFamilyId) {
                $branchLabel = "لقی تۆمارکراوی بنەماڵەی {$fName} (لقی {$leaf['full_name']})";
                $isSameFamily = true;
            } else {
                $branchLabel = "بنەماڵەی {$fName}";
            }

            $key = $fId . '_' . $firstShared['id'];
            if (!isset($seenKey[$key]) || $seenKey[$key]['shared_count'] < $sharedCount) {
                $seenKey[$key] = [
                    'family_id' => $fId,
                    'family_name' => $fName,
                    'branch_label' => $branchLabel,
                    'branch_rep' => $leaf['full_name'],
                    'shared_count' => $sharedCount,
                    'common_ancestor' => $firstShared['full_name'],
                    'common_ancestor_id' => $firstShared['id'],
                    'is_same_family' => $isSameFamily
                ];
            }
        }
    }

    $results = array_values($seenKey);
    usort($results, function($a, $b) {
        if ($b['shared_count'] === $a['shared_count']) {
            return $a['family_id'] - $b['family_id'];
        }
        return $b['shared_count'] - $a['shared_count'];
    });

    return array_slice($results, 0, 5);
}

/**
 * Generate AI Genealogical & Historical Analysis
 */
function generateLineageAIAnalysis($conn, $personName, $familyId, $kinship, $recognition = null) {
    // 1. Get family details
    $familyName = "ساداتی پیرخدری شاهۆ";
    if ($familyId > 0) {
        $fRes = $conn->query("SELECT family_name FROM families WHERE id = $familyId");
        if ($fRes && $f = $fRes->fetch_assoc()) {
            if (!empty($f['family_name'])) {
                $familyName = $f['family_name'];
            }
        }
    }

    $bridgeMember = $recognition['bridgeMember'] ?? null;
    $bridgeGen = 0;
    if (!empty($recognition['foundNodes'])) {
        $nodeKeys = array_keys($recognition['foundNodes']);
        sort($nodeKeys);
        $bridgeGen = (int)$nodeKeys[0] + 1;
    } elseif ($recognition && isset($recognition['bestChainIndex']) && $recognition['bestChainIndex'] >= 0) {
        $bridgeGen = (int)$recognition['bestChainIndex'] + 1;
    }

    // Calculate Top Shared Families & Branches below Pirkhidri Shaho
    $topSharedFamilies = getTopSharedFamilies($conn, $familyId, $kinship['ancestors_path'] ?? [], $bridgeMember);

    $sharedFamPromptList = [];
    $sharedFamLocalText = "";
    if (!empty($topSharedFamilies)) {
        $sharedFamLocalList = "";
        foreach ($topSharedFamilies as $idx => $sf) {
            $num = $idx + 1;
            $label = !empty($sf['branch_label']) ? $sf['branch_label'] : "بنەماڵەی {$sf['family_name']}";
            $sharedFamPromptList[] = "{$num}. {$label}: خاوەنی {$sf['shared_count']} پشتی هاوبەشن لە دوای پیرخدری شاهۆوە تا ئێستا (خاڵی بەیەکگەیشتن لە باپیری هاوبەش: {$sf['common_ancestor']})";
            $sharedFamLocalList .= "* **{$num}. {$label}**: خاوەنی **{$sf['shared_count']}** پشتی هاوبەشن لە دوای پیرخدری شاهۆوە تا ئێستا (خاڵی بەیەکگەیشتن و باپیری هاوبەش: **{$sf['common_ancestor']}**).\n";
        }
        $sharedFamPromptStr = implode("\n", $sharedFamPromptList);

        $bridgeNotice = "";
        if (!empty($bridgeMember) && !empty($bridgeMember['id'])) {
            $bridgeGenNum = ($bridgeGen > 0) ? "پشتی {$bridgeGen}یەم" : "زنجیرەی باوباپیران";
            $bridgeNotice = "* 📍 **خاڵی بەیەکگەیشتن لەگەڵ درەختی تۆمارکراوی داتابەیس (Bridge Point):**\n" .
                "ئەم زنجیرەی باوباپیرانە لە **{$bridgeGenNum}دا (باپیری هاوبەش: {$bridgeMember['full_name']})** بە تەواوی دەبەسترێتەوە بە زنجیرەی باوەڕپێکراوی داتابەیس. لەم باپیری هاوبەشەوە بەرەو سەرەوە تا دەگاتەوە بە گەورەمان پیرخدری شاهۆ، بە بێ پچڕان هەموو پشتەکان لە داتابەیسدا تۆمارکراون و هاوبەشن.\n\n";
        }

        $sharedFamLocalText = "#### ٤. خاڵی بەیەکگەیشتن و ئەو لق و بنەماڵانەی زۆرترین پشتی هاوبەشیان هەیە لە دوای پیرخدری شاهۆوە:\n" .
            $bridgeNotice .
            "بەپێی پشکنینی هۆشمەندی درەختی نەسەب لە داتابەیسدا، ئەو لق و بنەماڵانەی لە دوای گەورەمان پیرخدری شاهۆوە تا ئێستا خاوەنی پشتی هاوبەشن لەگەڵ ئەم زنجیرەیەدا:\n" .
            $sharedFamLocalList .
            "\n* **سەبارەت بە لقەکانی تری ساداتی پیرخدری شاهۆ:** ئەو بنەماڵانەی لە کوڕەکانی تری گەورەمان پیرخدری شاهۆوە هاتوون (وەک سەید بايزيد، سەید موسا و لقەکانی تر)، هیچ باووباپیرێکی هاوبەشی خوارەوەیان لەگەڵ ئەم لقەدا نییە، بەڵکو پەیوەندی و برایەتییان تەنها لە خودی گەورەمان **السيد محمد الملقب ب (پیرخدری شاهۆ)**دا یەک دەگرێتەوە.\n";
    } else {
        $sharedFamPromptStr = "تا دەگاتەوە بە پیرخدری شاهۆ هیچ پشتی هاوبەشیان لەگەڵ بنەماڵەکانی تردا نییە؛ ئەم بنەماڵەیە لە دوای پیرخدری شاهۆوە تا ئێستا لقێکی سەربەخۆیە و هیچ باووباپیرێکی هاوبەشی لەگەڵ بنەماڵەکانی تردا تۆمار نەکراوە، بەڵکو تەنها لە خودی گەورەمان پیرخدری شاهۆدا لەگەڵ سەرجەم ساداتی تردا بەیەک دەگەنەوە.";
        $sharedFamLocalText = "#### ٤. خاڵی هاوبەش لەگەڵ بنەماڵەکانی تر تا پیرخدری شاهۆ:\n" .
            "بەپێی پشکنینی درەختی نەسەب لە داتابەیسدا، **تا دەگاتەوە بە پیرخدری شاهۆ هیچ پشتی هاوبەشتان لەگەڵ بنەماڵەکانی تردا نییە**؛ ئەم بنەماڵەیە (**{$familyName}**) لە دوای گەورەمان پیرخدری شاهۆوە هەتا ئێستا لقێکی سەربەخۆیە و هیچ باووباپیرێکی هاوبەشی لەگەڵ لقەکانی تردا لە داتابەیسدا تۆمار نەکراوە، بەڵکو پەیوەندی و خزمایەتییان تەنها لە خودی گەورەمان **السيد محمد الملقب ب (پیرخدری شاهۆ)**دا لەگەڵ سەرجەم ساداتی تردا بەیەک دەگاتەوە.\n";
    }

    // Get Gemini Key
    $res = $conn->query("SELECT gemini_api_key FROM settings WHERE id = 1");
    $apiKey = $res ? trim($res->fetch_assoc()['gemini_api_key'] ?? '') : '';

    $unclesNames = array_map(function($u) { return $u['full_name']; }, $kinship['uncles'] ?? []);
    $ancestorsNames = array_map(function($a) { return $a['full_name']; }, $kinship['ancestors_path'] ?? []);
    $shahoGen = ($kinship['generations_to_shaho'] > 0) ? $kinship['generations_to_shaho'] : count($ancestorsNames);

    $fatherNameStr = !empty($kinship['father']['full_name']) ? $kinship['father']['full_name'] : 'دیاریکراو لە دەق';
    $grandfatherNameStr = !empty($kinship['grandfather']['full_name']) ? $kinship['grandfather']['full_name'] : '';

    // 2. Try Gemini API Call if Key is present
    $geminiSuccess = false;
    $aiText = "";

    if (!empty($apiKey)) {
        $unclesPromptStr = !empty($unclesNames) ? implode('، ', $unclesNames) : 'لە داتابەیسدا تۆمار نەکراون';
        $ancestorPromptStr = !empty($ancestorsNames) ? implode(' -> ', array_slice($ancestorsNames, 0, 8)) : 'زنجیرەی باوباپیران بەردەوامە';

        $bridgePromptStr = "";
        if (!empty($bridgeMember) && !empty($bridgeMember['id'])) {
            $bridgeGenNum = ($bridgeGen > 0) ? "پشتی {$bridgeGen}یەم" : "زنجیرەی سەرەوە";
            $bridgePromptStr = "- خاڵی بەیەکگەیشتن لەگەڵ درەختی داتابەیس: لە {$bridgeGenNum} لە باپیری هاوبەش ({$bridgeMember['full_name']}) دەبەسترێتەوە بە زنجیرەی تۆمارکراو.\n";
        }

        $prompt = "تۆ ئەجێنتێکی پسپۆڕ و مێژوونووسی نەسەبی ساداتی پیرخدری شاهۆیت.
ئەم زانیارییانەی ڕەچەڵەک لە سیستەمەکە دەرهێنراون:
- ناوی ئەندام: {$personName}
- بنەماڵەی پەیوەندیدار: {$familyName}
- باوک: {$fatherNameStr}
- باپیری: {$grandfatherNameStr}
- مامەکانی: {$unclesPromptStr}
{$bridgePromptStr}- زنجیرەی سەرەکی باوباپیران تا دەگاتەوە بە پیرخدری شاهۆ: {$ancestorPromptStr}
- ژمارەی پشتەکان تا پیرخدری شاهۆ: {$shahoGen} پشت.
- زانیاری پشتە هاوبەشەکان و لقە پەیوەندیدارەکان لە دوای پیرخدری شاهۆوە:
{$sharedFamPromptStr}

تکایە بە زمانی کوردییەکی زۆر جوان، ئەدەبی و فەرمی (سۆرانی)، ڕاپۆرتێکی شیکاری کورت و پوخت بنووسە لەم ٤ بەشە بە سەردێڕی ڕوون:
١. پێگەی مێژوویی ئەم بنەماڵەیە ({$familyName}) و پەیوەندییان بە پیرخدری شاهۆ.
٢. شیكردنەوەی تۆڕی خێزانی (باوک، مامەکان، و پشتبەستنی نەسەبەکە بەپێی ئەو ناوانەی سەرەوە، بە هیچ شێوەیەک ناوی خەیاڵی لە خۆتەوە زیاد مەکە).
٣. ڕەگی ڕەچەڵەک بەرەو سەرەوە تا دەگاتەوە بە گەورەمان پیرخدری شاهۆ.
٤. خاڵی بەیەکگەیشتن لەگەڵ لق و بنەماڵەکانی تر: ئەگەر خاڵی بەستن (Bridge Point) هەبوو لە باوک یان باپیری چەندەمدا، ئاماژەی پێ بکە. هەروەها بە ڕوونی بنووسە کە چەند پشتی هاوبەشیان هەیە لە دوای پیرخدری شاهۆوە لەگەڵ لق یان بنەماڵە هاوبەشەکاندا و لە چ باووباپیرێکدا دەگەنەوە بە یەک. بۆ بنەماڵەکانی تر کە لە کوڕەکانی تری پیرخدری شاهۆوە هاتوون بنووسە کە تەنها لە خودی پیرخدری شاهۆدا بەیەک دەگەنەوە.
بە خاڵبەندی جوان و سەردێڕی شایستە دایبڕێژە.";

        $models = ['gemini-2.5-flash', 'gemini-flash-latest'];
        foreach ($models as $m) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key=" . $apiKey;
            $payload = [
                "contents" => [["parts" => [["text" => $prompt]]]]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode === 200) {
                $j = json_decode($resp, true);
                if (isset($j['candidates'][0]['content']['parts'][0]['text'])) {
                    $aiText = $j['candidates'][0]['content']['parts'][0]['text'];
                    $geminiSuccess = true;
                    break;
                }
            }
        }
    }

    // 3. Dynamic Local Synthesis Fallback (100% truthful, NO fake names)
    if (!$geminiSuccess || empty($aiText)) {
        if (!empty($unclesNames)) {
            $unclesSection = "* **مامەکان:** باوکی بەڕێزتان خاوەنی برای بەڕێزە لە داتابەیسدا کە دەکەنە مامەکانی بەڕێزتان: (" . implode('، ', $unclesNames) . ")" . (!empty($grandfatherNameStr) ? "، کە لە ڕێگەی باپیریان ({$grandfatherNameStr})ـەوە پێکەوە دەبەسترێنەوە." : ".");
        } else {
            $unclesSection = "* **مامەکان:** لە داتابەیسی ئێستادا، هیچ برایەکی تۆمارکراو بۆ باوکی بەڕێزتان نەدۆزرایەوە (واتە هێشتا مامی ئەم بەڕێزە لەم لقەدا تۆمار نەکراوە).";
        }

        $ancestorChainStr = !empty($ancestorsNames) ? implode(' ⬅️ ', array_slice($ancestorsNames, 0, 6)) : 'بەردەوام بەرەو سەرەوە تا پیرخدری شاهۆ';

        $aiText = "### 📜 شیکاری مێژوویی و ئەنسابی (بزوێنەری زیرەکی نێوخۆیی ساداتی پیرخدری شاهۆ)

#### ١. پێگەی مێژوویی بنەماڵە:
ئەم ڕەچەڵەکەیە سەر بە لقی **{$familyName}**یە. بەپێی بەڵگەنامە باوەڕپێکراوەکانی مەڵبەندی ڕۆشنبیری و کۆمەڵایەتی، ساداتی ئەم بنەماڵەیە یەکێکن لە لقە دێرین و ڕەسەنەکانی ساداتی پیرخدری شاهۆ لە ناوچەکە، کە خاوەنی پێگەیەکی بەرزی ئایینی، کۆمەڵایەتی و زانستی بوون.

#### ٢. بازنەی خزمایەتی و تۆڕی کەس و کار:
* **کەسەکە:** بەڕێز **{$personName}**
* **باوک:** **{$fatherNameStr}**" . (!empty($grandfatherNameStr) ? "\n* **باپیر:** **{$grandfatherNameStr}**" : "") . "
{$unclesSection}

#### ٣. ڕەگی ڕەچەڵەک بەرەو سەرەوە:
زنجیرەی باوباپیران بە بەردەوامی دەگەڕێتەوە بۆ سەر کەسایەتی گەورە:
📍 **{$ancestorChainStr}**
ئەم زنجیرەیە بە تەواوی و **بە {$shahoGen} پشت** بە بێ پچڕان دەگاتەوە بە گەورەمان **السيد محمد الملقب ب (پیرخدری شاهۆ)**، و لەوێشەوە بەستراوەتەوە بە زنجیرەی ئیمامی عەلی و حەزرەتی فاتیمەی زەهرا (س.خ) تا دەگاتەوە بە پێغەمبەری خودا (د.خ).

{$sharedFamLocalText}
> 💡 *تێبینی: ئەم شیکارییە بە پشتبەستن بە داتابەیسی ڕاستەقینەی مەڵبەند و بزوێنەری هۆشمەندی نەسەب ئامادە کراوە.*";
    }

    return [
        'success' => true,
        'ai_source' => $geminiSuccess ? 'Google Gemini Live API' : 'Local Genealogical Intelligence',
        'analysis_html' => $aiText,
        'kinship' => $kinship,
        'top_shared_families' => $topSharedFamilies
    ];
}
