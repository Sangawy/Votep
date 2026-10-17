<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) die(json_encode(['success' => false, 'message' => 'Unauthorized']));

require_once 'config.php';
require_once 'lineage_core.php';
require_once 'lineage_ai.php';
$conn = getDB();

$action = $_POST['action'] ?? '';

if ($action === 'recognize') {
    $input = $_POST['input'] ?? '';
    if (ob_get_length() > 0) { ob_clean(); }
    if (empty($input)) die(json_encode(['success' => false, 'message' => 'Empty input']));
    set_time_limit(120); 

    $recognition = processLineage($input, $conn);
    
    // Calculate shared families above Shaho
    $detectedFamilyId = (int)($recognition['family_id'] ?? 0);
    $recognition['shared_families'] = getSharedGenerationsAboveShaho($conn, $detectedFamilyId);

    // Extract Kinship network (Uncles, Ancestors path, etc.)
    $mId = 0;
    foreach ($recognition['foundSiblings'] ?? [] as $sib) {
        if (!empty($sib['id'])) {
            $mId = (int)$sib['id'];
            break;
        }
    }
    $bId = (int)($recognition['bridgeMember']['id'] ?? 0);
    $chainParts = $recognition['missingChain'] ?? [];
    $foundNodes = $recognition['foundNodes'] ?? [];
    $recognition['kinship'] = getFullKinshipNetwork($conn, $mId, $bId, $detectedFamilyId, $chainParts, $foundNodes);

    echo json_encode(array_merge(['success' => true], $recognition));
    exit;
}

if ($action === 'ai_analyze') {
    $input = trim($_POST['input'] ?? '');
    $familyId = (int)($_POST['family_id'] ?? 0);
    $memberId = (int)($_POST['member_id'] ?? 0);
    $bridgeId = (int)($_POST['bridge_id'] ?? 0);
    if (ob_get_length() > 0) { ob_clean(); }

    $recognition = processLineage($input, $conn);
    if ($familyId === 0 && !empty($recognition['family_id'])) {
        $familyId = (int)$recognition['family_id'];
    }
    if ($memberId === 0) {
        foreach ($recognition['foundSiblings'] ?? [] as $sib) {
            if (!empty($sib['id'])) {
                $memberId = (int)$sib['id'];
                break;
            }
        }
    }
    if ($bridgeId === 0 && !empty($recognition['bridgeMember']['id'])) {
        $bridgeId = (int)$recognition['bridgeMember']['id'];
    }
    $chainParts = $recognition['missingChain'] ?? [];
    $foundNodes = $recognition['foundNodes'] ?? [];

    $kinship = getFullKinshipNetwork($conn, $memberId, $bridgeId, $familyId, $chainParts, $foundNodes);
    $analysis = generateLineageAIAnalysis($conn, $input, $familyId, $kinship, $recognition);
    echo json_encode($analysis);
    exit;
}

if ($action === 'add_family') {
    $familyName = trim($_POST['family_name'] ?? '');
    if (empty($familyName)) {
        die(json_encode(['success' => false, 'message' => 'ناوی بنەماڵە بەتاڵە!']));
    }
    
    // Check if it already exists
    $stmt = $conn->prepare("SELECT id FROM families WHERE family_name = ?");
    if ($stmt) {
        $stmt->bind_param("s", $familyName);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($res) {
            die(json_encode(['success' => true, 'family_id' => (int)$res['id']])); // Reuse existing
        }
    }
    
    // Insert new family
    $stmt = $conn->prepare("INSERT INTO families (family_name) VALUES (?)");
    if ($stmt) {
        $stmt->bind_param("s", $familyName);
        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            $stmt->close();
            die(json_encode(['success' => true, 'family_id' => $newId]));
        }
        $stmt->close();
    }
    die(json_encode(['success' => false, 'message' => 'تۆمارکردنی بنەماڵە لە داتابەیسدا سەرکەوتوو نەبوو!']));
}

function getOrCreateContextualMember($conn, $chain, $index, $parentId, $familyId, $status = 'inactive') {
    // 1. Try to find the member using 4-generation contextual matching to prevent duplicates
    $existing = findMemberContextual($conn, $chain, $index);
    if ($existing) {
        $existingId = (int)$existing['id'];
        
        // Ensure parent_id is linked to the parent we just created/matched in the loop
        if ($parentId > 0) {
            $checkParent = $conn->query("SELECT parent_id FROM members WHERE id = $existingId");
            if ($cp = $checkParent->fetch_assoc()) {
                if (empty($cp['parent_id']) || $cp['parent_id'] == 0) {
                    $conn->query("UPDATE members SET parent_id = $parentId WHERE id = $existingId");
                }
            }
        }
        return $existingId;
    }
    
    // 2. Fall back to creating it if not found contextually
    $name = trim($chain[$index]);
    $res = findOrCreateCore($conn, $name, $parentId, $familyId, $status);
    return (int)$res['id'];
}

if ($action === 'save_and_link') {
    $siblings = json_decode($_POST['siblings'] ?? '[]', true);
    $chainParts = json_decode($_POST['missingChain'] ?? '[]', true);
    $bridgeId = (int)($_POST['bridgeId'] ?? 0);
    $bestChainIndex = (int)($_POST['bestChainIndex'] ?? -1);
    $familyId = (int)($_POST['familyId'] ?? 0);
    $baseBridgeChain = json_decode($_POST['baseBridgeChain'] ?? '[]', true);

    set_time_limit(180);
    $conn->begin_transaction();
    try {
        // Check if we are bridging to the base lineage
        if ($bridgeId === 0 && !empty($baseBridgeChain) && $bestChainIndex !== -1) {
            // Build the unified chain by merging input chain up to match with base bridge chain
            $unifiedChain = [];
            for ($i = 0; $i < $bestChainIndex; $i++) {
                $unifiedChain[] = $chainParts[$i];
            }
            foreach ($baseBridgeChain as $bName) {
                $unifiedChain[] = $bName;
            }

            // Build this unified chain from the oldest ancestor down to the youngest
            $lastId = 0;
            for ($i = count($unifiedChain) - 1; $i >= 0; $i--) {
                $status = 'inactive';
                $lastId = getOrCreateContextualMember($conn, $unifiedChain, $i, $lastId, $familyId, $status);
            }
        } else {
            // --- SMART BI-DIRECTIONAL LINKING (v4.0) ---
            // 1. Build UPWARDS from the bridge to link all ancestors
            $currentTopId = $bridgeId;
            if ($bridgeId > 0 && $bestChainIndex !== -1) {
                for ($i = $bestChainIndex + 1; $i < count($chainParts); $i++) {
                    $ancestorId = getOrCreateContextualMember($conn, $chainParts, $i, 0, $familyId, 'inactive');
                    
                    // Ensure the bridge/node is linked to this ancestor
                    $conn->query("UPDATE members SET parent_id = $ancestorId WHERE id = $currentTopId AND (parent_id IS NULL OR parent_id = 0)");
                    $currentTopId = $ancestorId;
                }
            }

            // 2. Build DOWNWARDS from the bridge to link all descendants
            $lastId = $bridgeId;
            if ($bridgeId > 0 && $bestChainIndex !== -1) {
                for ($i = $bestChainIndex - 1; $i >= 0; $i--) {
                    $status = 'inactive';
                    $lastId = getOrCreateContextualMember($conn, $chainParts, $i, $lastId, $familyId, $status);
                }
            } else {
                // No bridge found, build entire chain from oldest ancestor down
                $lastId = 0;
                for ($i = count($chainParts) - 1; $i >= 0; $i--) {
                    $status = 'inactive';
                    $lastId = getOrCreateContextualMember($conn, $chainParts, $i, $lastId, $familyId, $status);
                }
            }
        }

        // 3. Link Siblings
        $finalParentId = $lastId;
        foreach($siblings as $sName) {
            if (!empty($chainParts) && normalizeName($sName) === normalizeName($chainParts[0])) {
                $conn->query("UPDATE members SET member_status = 'active' WHERE id = $finalParentId");
                continue;
            }
            // Sibling insertion is active status
            $res = findOrCreateCore($conn, $sName, $finalParentId, $familyId, 'active');
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'زنجیرەکە بە تەواوی و بە بێ پچڕان بەستراوەیەوە']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'هەڵە: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'search_members') {
    $query = trim($_POST['query'] ?? '');
    if (strlen($query) < 2) {
        die(json_encode(['success' => false, 'message' => 'Query too short']));
    }
    
    $normQuery = normalizeName($query);
    
    $sql = "
        SELECT m.id, m.full_name, m.member_code, m.profile_image, m.grandfather_name, m.great_grandfather_name, f.full_name as father_name, m.family_id
        FROM members m
        LEFT JOIN members f ON m.parent_id = f.id
        WHERE m.normalized_name LIKE ? OR m.full_name LIKE ?
        LIMIT 20
    ";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $likeParam = "%" . $normQuery . "%";
        $likeFull = "%" . $query . "%";
        $stmt->bind_param("ss", $likeParam, $likeFull);
        $stmt->execute();
        $res = $stmt->get_result();
        $members = [];
        while ($row = $res->fetch_assoc()) {
            $members[] = $row;
        }
        $stmt->close();
        die(json_encode(['success' => true, 'members' => $members]));
    }
    die(json_encode(['success' => false, 'message' => 'Database error']));
}


function findMemberContextual($conn, $chainParts, $index) {
    $name = trim($chainParts[$index]);
    $norm = normalizeName($name);
    if (empty($norm)) return null;
    
    // 0. Explicit Pirkhidri Shaho Resolution
    if (isShahoName($name)) {
        $res = $conn->query("SELECT m.id, m.full_name, m.parent_id, m.family_id, m.member_code, m.grandfather_name, m.great_grandfather_name, f.full_name as father_name 
                             FROM members m 
                             LEFT JOIN members f ON m.parent_id = f.id 
                             WHERE m.id = 1 OR m.full_name LIKE '%بيرخضر%' OR m.full_name LIKE '%پیرخدر%' 
                             ORDER BY (m.id = 1) DESC 
                             LIMIT 1");
        if ($res && $r = $res->fetch_assoc()) {
            return $r;
        }
    }
    
    // Extract first name for the core candidate
    $firstName = extractFirstName($name);
    $normFirst = normalizeName($firstName);
    
    // Father's first name (index + 1)
    $fatherName = isset($chainParts[$index + 1]) ? extractFirstName($chainParts[$index + 1]) : '';
    $normFather = normalizeName($fatherName);
    
    // Grandfather's first name (index + 2)
    $grandfatherName = isset($chainParts[$index + 2]) ? extractFirstName($chainParts[$index + 2]) : '';
    $normGrand = normalizeName($grandfatherName);
    
    // Great-grandfather's first name (index + 3)
    $greatGrandfatherName = isset($chainParts[$index + 3]) ? extractFirstName($chainParts[$index + 3]) : '';
    $normGreat = normalizeName($greatGrandfatherName);
    
    $matchedRow = null;
    
    // If fatherName is provided, we MUST match it to avoid mismatching names of another family!
    if (!empty($normFather)) {
        $sql = "
            SELECT m.id, m.full_name, m.parent_id, m.family_id, m.member_code, m.grandfather_name, m.great_grandfather_name, f.full_name as father_name 
            FROM members m 
            LEFT JOIN members f ON m.parent_id = f.id 
            WHERE (m.first_name = ? OR m.normalized_name = ? OR m.normalized_name LIKE ?) 
              AND (m.father_name = ? OR f.normalized_name LIKE ? OR f.full_name LIKE ?)
            LIMIT 20
        ";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $likeNorm = "%" . $norm . "%";
            $likeFather = "%" . $normFather . "%";
            $likeFatherRaw = "%" . $fatherName . "%";
            $stmt->bind_param("ssssss", $firstName, $norm, $likeNorm, $fatherName, $likeFather, $likeFatherRaw);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    // Shaho protection: A non-Shaho input MUST NOT match Shaho in DB!
                    if (isShahoName($row['full_name']) && !isShahoName($name)) {
                        continue;
                    }
                    if (!isInvalidRootMatch($row, $chainParts, $index)) {
                        $matchedRow = $row;
                        break;
                    }
                }
            }
            $stmt->close();
        }
        
        if (!$matchedRow) {
            // UNIQUE NAME FALLBACK / گەڕانی یەدەگی ناوی بێهاوتا
            // Only apply for distinctive names, NOT common first names (like عبدالعزیز, محمد, etc.)
            // Candidate must NOT be an active living person, and must have ancestors to verify
            $commonFirstNames = ['محمد', 'علی', 'حسن', 'حسین', 'عبدالعزیز', 'عثمان', 'عمر', 'مصطفی', 'احمد', 'ابراهیم', 'اسماعیل', 'یوسف', 'محمود', 'خالد', 'عبدالرحمن', 'عبدالله'];
            if (!in_array($norm, $commonFirstNames) && (mb_strlen($norm) >= 9 || mb_strpos($name, 'ب(') !== false || mb_strpos($name, 'المشهور') !== false || mb_strpos($name, 'الملقب') !== false)) {
                $checkSql = "SELECT COUNT(*) as cnt FROM members WHERE normalized_name = ? AND (is_alive = 0 OR is_alive IS NULL)";
                $cStmt = $conn->prepare($checkSql);
                if ($cStmt) {
                    $cStmt->bind_param("s", $norm);
                    $cStmt->execute();
                    $cRes = $cStmt->get_result();
                    $cRow = $cRes->fetch_assoc();
                    $cStmt->close();
                    if ($cRow && (int)$cRow['cnt'] === 1) {
                        $uniqueSql = "SELECT m.id, m.full_name, m.parent_id, m.family_id, m.member_code, m.grandfather_name, m.great_grandfather_name, f.full_name as father_name 
                                      FROM members m 
                                      LEFT JOIN members f ON m.parent_id = f.id 
                                      WHERE m.normalized_name = ? AND (m.is_alive = 0 OR m.is_alive IS NULL)
                                      LIMIT 1";
                        $uStmt = $conn->prepare($uniqueSql);
                        if ($uStmt) {
                            $uStmt->bind_param("s", $norm);
                            $uStmt->execute();
                            $uRes = $uStmt->get_result();
                            if ($uRes && $uRes->num_rows > 0) {
                                $row = $uRes->fetch_assoc();
                                if (!isShahoName($row['full_name']) || isShahoName($name)) {
                                    if (!isInvalidRootMatch($row, $chainParts, $index)) {
                                        $matchedRow = $row;
                                    }
                                }
                            }
                            $uStmt->close();
                        }
                    }
                }
            }
        }
    } else {
        // Fallback for single names / root ancestors
        $sql = "
            SELECT m.id, m.full_name, m.parent_id, m.family_id, m.member_code, m.grandfather_name, m.great_grandfather_name, f.full_name as father_name 
            FROM members m 
            LEFT JOIN members f ON m.parent_id = f.id 
            WHERE m.normalized_name = ? OR m.full_name LIKE ?
            LIMIT 20
        ";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $likeName = "%" . $name . "%";
            $stmt->bind_param("ss", $norm, $likeName);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    // Shaho protection:
                    if (isShahoName($row['full_name']) && !isShahoName($name)) {
                        continue;
                    }
                    $rowFirst = !empty($row['first_name']) ? $row['first_name'] : extractFirstName($row['full_name']);
                    if (normalizeName($rowFirst) !== $normFirst && normalizeName($row['full_name']) !== $norm) {
                        continue;
                    }
                    if (!isInvalidRootMatch($row, $chainParts, $index)) {
                        $matchedRow = $row;
                        break;
                    }
                }
            }
            $stmt->close();
        }
    }
    
    // Strict enforcement for children of root ancestor
    if ($matchedRow && isInvalidRootMatch($matchedRow, $chainParts, $index)) {
        return null;
    }
    
    return $matchedRow;
}

function namesMatch($name1, $name2) {
    if (isShahoName($name1) && isShahoName($name2)) {
        return true;
    }
    if (isShahoName($name1) !== isShahoName($name2)) {
        return false;
    }
    $n1 = normalizeName($name1);
    $n2 = normalizeName($name2);
    if (empty($n1) || empty($n2)) return false;
    if ($n1 === $n2) return true;
    
    // First name check using extractFirstName
    $f1 = normalizeName(extractFirstName($name1));
    $f2 = normalizeName(extractFirstName($name2));
    if (!empty($f1) && !empty($f2) && $f1 === $f2) {
        return true;
    }

    // Prefix check (e.g. محمدامین starts with محمد)
    if (mb_strpos($n1, $n2) === 0 || mb_strpos($n2, $n1) === 0) {
        return true;
    }

    // Substring matching for longer names (e.g. with epithets or village names like "محمود الكبير في قريه باني مورد")
    if (mb_strlen($n1, 'UTF-8') >= 4 && mb_strlen($n2, 'UTF-8') >= 4) {
        if (mb_strpos($n1, $n2) !== false || mb_strpos($n2, $n1) !== false) {
            return true;
        }
    }
    
    return false;
}

function validateAncestors($conn, $row, $chainParts, $index) {
    if (!$row) return false;
    
    // Shaho anchor check:
    if (isShahoName($row['full_name'])) {
        return isShahoName($chainParts[$index]);
    } else {
        if (isShahoName($chainParts[$index])) {
            return false;
        }
    }
    
    $dbAncestors = [];
    $currId = (int)$row['id'];
    $visited = [];
    
    // Fetch up to 5 generations of ancestors from database
    for ($k = 0; $k < 5; $k++) {
        if ($currId <= 0 || isset($visited[$currId])) break;
        $visited[$currId] = true;
        
        $res = $conn->query("SELECT parent_id FROM members WHERE id = $currId");
        if ($res && $pRow = $res->fetch_assoc()) {
            $pId = (int)$pRow['parent_id'];
            if ($pId > 0) {
                $res2 = $conn->query("SELECT id, full_name, parent_id FROM members WHERE id = $pId");
                if ($res2 && $row2 = $res2->fetch_assoc()) {
                    $dbAncestors[] = $row2;
                    $currId = $pId;
                } else {
                    break;
                }
            } else {
                break;
            }
        } else {
            break;
        }
    }
    
    // If DB has 0 ancestors, but input has a father specified, we cannot validate!
    if (count($dbAncestors) === 0) {
        if (isset($chainParts[$index + 1]) && !isShahoName($row['full_name'])) {
            return false;
        }
        return true;
    }
    
    // Compare database ancestors with input ancestors step-by-step
    for ($k = 0; $k < count($dbAncestors); $k++) {
        $inputAncestorIndex = $index + 1 + $k;
        if (isset($chainParts[$inputAncestorIndex])) {
            $inputName = $chainParts[$inputAncestorIndex];
            $dbName = $dbAncestors[$k]['full_name'];
            
            if (!namesMatch($inputName, $dbName)) {
                // Immediate parent (k=0) must match strictly
                if ($k === 0) {
                    return false;
                }
                // For deeper ancestors (k >= 1), check for an intermediate gap / offset in input
                $foundNext = false;
                for ($offset = 1; $offset <= 3; $offset++) {
                    if (isset($chainParts[$inputAncestorIndex + $offset])) {
                        if (namesMatch($chainParts[$inputAncestorIndex + $offset], $dbName)) {
                            $foundNext = true;
                            break;
                        }
                    }
                }
                if ($foundNext) continue;
                return false; // Conflict found
            }
        }
    }

    return true; // Verified
}

function isInvalidRootMatch($row, $chainParts, $index) {
    global $conn;
    return !validateAncestors($conn, $row, $chainParts, $index);
}

function processLineage($input, $conn) {
    // 1. Smart Tokenization (v2.0: Handles 3-part, 4-part, traditional بن, and Kurdish کوڕی)
    $tokenized = smartTokenizeName($input);
    $siblingsList = $tokenized['siblings'];
    $chainParts = $tokenized['chain'];

    // 2. Hierarchical Search (Anchor + Smart Propagation)
    $foundNodes = [];
    $bestBridge = null;
    $bestChainIndex = -1;

    // Phase A: Find the first reliable anchor (closest to the leaf / siblings)
    foreach($chainParts as $idx => $name) {
        $row = findMemberContextual($conn, $chainParts, $idx);
        if ($row) {
            $foundNodes[$idx] = $row;
            $bestBridge = $row;
            $bestChainIndex = $idx;
            break; // FOUND OUR PRIMARY ANCHOR! Stop flat searching to prevent false matches on common names.
        }
    }

    // Phase B: Smart Bidirectional Propagation from the primary anchor
    if ($bestChainIndex !== -1) {
        // 1. Propagate UP (from anchor to older ancestors)
        for ($i = $bestChainIndex + 1; $i < count($chainParts); $i++) {
            $prevNode = $foundNodes[$i-1] ?? null;
            if ($prevNode) {
                $pId = (int)$prevNode['parent_id'];
                if ($pId > 0) {
                    $res = $conn->query("SELECT m.id, m.full_name, m.parent_id, m.family_id, m.member_code, m.grandfather_name, m.great_grandfather_name, f.full_name as father_name 
                                         FROM members m LEFT JOIN members f ON m.parent_id = f.id 
                                         WHERE m.id = $pId");
                    if ($row = $res->fetch_assoc()) {
                        if (namesMatch($row['full_name'], $chainParts[$i])) {
                            $foundNodes[$i] = $row;
                        }
                    }
                }
            }
        }

        // 2. Propagate DOWN (from anchor to descendants)
        for ($i = $bestChainIndex - 1; $i >= 0; $i--) {
            $nextNode = $foundNodes[$i+1] ?? null;
            if ($nextNode) {
                $pId = (int)$nextNode['id'];
                $res = $conn->query("SELECT m.id, m.full_name, m.parent_id, m.family_id, m.member_code, m.grandfather_name, m.great_grandfather_name, f.full_name as father_name 
                                     FROM members m LEFT JOIN members f ON m.parent_id = f.id 
                                     WHERE m.parent_id = $pId");
                while($row = $res->fetch_assoc()) {
                    if (namesMatch($row['full_name'], $chainParts[$i])) {
                        $foundNodes[$i] = $row;
                        break;
                    }
                }
            }
        }
    }

    // Phase C: Multi-Anchor Gap Solver (for disconnected segments in the DB)
    for ($pass = 0; $pass < 3; $pass++) {
        $newAnchorFound = false;
        foreach($chainParts as $idx => $name) {
            if (isset($foundNodes[$idx])) continue; // Already resolved
            
            $row = findMemberContextual($conn, $chainParts, $idx);
            if ($row) {
                $foundNodes[$idx] = $row;
                $newAnchorFound = true;
                
                // Propagate UP from this new anchor
                for ($i = $idx + 1; $i < count($chainParts); $i++) {
                    if (isset($foundNodes[$i])) break; // Met another resolved segment
                    $prevNode = $foundNodes[$i-1] ?? null;
                    if ($prevNode) {
                        $pId = (int)$prevNode['parent_id'];
                        if ($pId > 0) {
                            $res2 = $conn->query("SELECT m.id, m.full_name, m.parent_id, m.family_id, m.member_code, m.grandfather_name, m.great_grandfather_name, f.full_name as father_name 
                                                 FROM members m LEFT JOIN members f ON m.parent_id = f.id 
                                                 WHERE m.id = $pId");
                            if ($row2 = $res2->fetch_assoc()) {
                                if (namesMatch($row2['full_name'], $chainParts[$i])) {
                                    $foundNodes[$i] = $row2;
                                }
                            }
                        }
                    }
                }
                
                // Propagate DOWN from this new anchor
                for ($i = $idx - 1; $i >= 0; $i--) {
                    if (isset($foundNodes[$i])) break; // Met another resolved segment
                    $nextNode = $foundNodes[$i+1] ?? null;
                    if ($nextNode) {
                        $pId = (int)$nextNode['id'];
                        $res2 = $conn->query("SELECT m.id, m.full_name, m.parent_id, m.family_id, m.member_code, m.grandfather_name, m.great_grandfather_name, f.full_name as father_name 
                                             FROM members m LEFT JOIN members f ON m.parent_id = f.id 
                                             WHERE m.parent_id = $pId");
                        while($row2 = $res2->fetch_assoc()) {
                            if (namesMatch($row2['full_name'], $chainParts[$i])) {
                                $foundNodes[$i] = $row2;
                                break;
                            }
                        }
                    }
                }
            }
        }
        if (!$newAnchorFound) break;
    }

    // Ensure Pirkhidri Shaho is recognized if present in chain
    foreach ($chainParts as $idx => $name) {
        if (!isset($foundNodes[$idx]) && isShahoName($name)) {
            $shahoRes = $conn->query("SELECT m.id, m.full_name, m.parent_id, m.family_id, m.member_code, m.grandfather_name, m.great_grandfather_name, f.full_name as father_name 
                                      FROM members m LEFT JOIN members f ON m.parent_id = f.id 
                                      WHERE m.id = 1 OR m.full_name LIKE '%بيرخضر%' OR m.full_name LIKE '%پیرخدر%' 
                                      ORDER BY (m.id = 1) DESC LIMIT 1");
            if ($shahoRes && $shahoRow = $shahoRes->fetch_assoc()) {
                $foundNodes[$idx] = $shahoRow;
            }
        }
    }

    // 4. Siblings Recognition (linked to the leaf/child node)
    $foundSiblings = [];
    $pId = (isset($foundNodes[0])) ? (int)$foundNodes[0]['id'] : 0;
    $fatherName = $chainParts[0] ?? '';

    foreach($siblingsList as $idx => $sName) {
        $found = null;
        $normS = normalizeName($sName);
        $sFirst = extractFirstName($sName);
        $normSFirst = normalizeName($sFirst);

        // A. If father node is in DB, search children of that father
        if ($pId > 0) {
            $res = $conn->query("SELECT id, full_name, member_code, family_id, parent_id FROM members WHERE parent_id = $pId");
            while($row = $res->fetch_assoc()) {
                if (isShahoName($row['full_name']) && !isShahoName($sName)) {
                    continue;
                }
                $rowFirst = normalizeName(extractFirstName($row['full_name']));
                if (normalizeName($row['full_name']) === $normS || $rowFirst === $normSFirst) {
                    $found = $row;
                    break;
                }
            }
        }

        // B. If not found by parent_id, search directly in members table ONLY if fatherName is provided AND matches both first_name and father_name
        if (!$found && !empty($fatherName)) {
            $fFirst = extractFirstName($fatherName);
            $normFFirst = normalizeName($fFirst);

            $stmt = $conn->prepare("
                SELECT m.id, m.full_name, m.member_code, m.parent_id, m.family_id, m.grandfather_name, f.full_name as father_full_name
                FROM members m 
                LEFT JOIN members f ON m.parent_id = f.id 
                WHERE (m.first_name = ? OR m.normalized_name = ?) 
                  AND (m.father_name = ? OR f.normalized_name LIKE ?)
                LIMIT 10
            ");
            if ($stmt) {
                $likeFather = "%" . $normFFirst . "%";
                $stmt->bind_param("ssss", $sFirst, $normS, $fFirst, $likeFather);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    if (isShahoName($row['full_name']) && !isShahoName($sName)) {
                        continue;
                    }
                    // Validate ancestor consistency if chain has grandfather
                    if (isset($chainParts[1])) {
                        $expectedGrand = normalizeName(extractFirstName($chainParts[1]));
                        if (!empty($expectedGrand) && !empty($row['grandfather_name'])) {
                            $dbGrand = normalizeName($row['grandfather_name']);
                            if ($dbGrand !== $expectedGrand && mb_strpos($dbGrand, $expectedGrand) === false && mb_strpos($expectedGrand, $dbGrand) === false) {
                                continue; // Grandfather conflict!
                            }
                        }
                    }
                    $found = $row;
                    break;
                }
                $stmt->close();
            }
        }

        // C. If still not found, ONLY match full_name if NO chain was entered (e.g. searching a single full name)
        if (!$found && count($siblingsList) === 1 && empty($chainParts)) {
            $stmt = $conn->prepare("
                SELECT id, full_name, member_code, parent_id, family_id 
                FROM members 
                WHERE normalized_name = ? OR full_name = ?
                LIMIT 5
            ");
            if ($stmt) {
                $stmt->bind_param("ss", $normS, $sName);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    if (isShahoName($row['full_name']) && !isShahoName($sName)) {
                        continue;
                    }
                    $found = $row;
                    break;
                }
                $stmt->close();
            }
        }

        $foundSiblings[$idx] = $found;
    }

    // 5. Match against Base Lineage (from Settings)
    /* 
     * ⚠️ WARNING / ئاگاداری گرنگ:
     * DO NOT CHANGE THIS LOGIC! DO NOT REVERT TO FORWARD MATCHING!
     * ئەم لۆژیکە بە هیچ شێوەیەک دەستکاری مەکە! 
     * 1. We MUST scan the chain from OLDEST to YOUNGEST (count($chainParts)-1 down to 0) to avoid 
     * false positive matches on common names (like Mahmoud, Mohammad, Ali) close to the leaf nodes.
     * 2. For short common names (<= 10 characters), we MUST enforce EXACT MATCHING to prevent 
     * "Mahmoud" matching with "Mahmoud Al-Madini" and creating a wrong bridge!
     */
    $baseChain = getBaseChain($conn);
    $baseBridge = null;
    if (!empty($baseChain)) {
        // Loop from the oldest ancestor (end of chainParts) to the youngest (start of chainParts)
        // to match unique ancestors (e.g. Shaho, Madini) rather than common ones (e.g. Mahmoud, Mohammad)
        for ($idx = count($chainParts) - 1; $idx >= 0; $idx--) {
            $name = $chainParts[$idx];
            $normName = normalizeName($name);
            if (empty($normName)) continue;
            
            foreach ($baseChain as $bIdx => $bName) {
                $normBName = normalizeName($bName);
                if (empty($normBName)) continue;
                
                // For common short names, require exact or near-exact match to avoid false positive bridging
                $isCommon = (mb_strlen($normName, 'UTF-8') <= 10);
                $matched = false;
                
                if ($isCommon) {
                    $matched = ($normName === $normBName);
                } else {
                    $matched = ($normName === $normBName || mb_strpos($normBName, $normName) !== false || mb_strpos($normName, $normBName) !== false);
                }
                
                if ($matched) {
                    // Check for conflicts between input ancestors and base chain ancestors
                    $conflict = false;
                    for ($k = 1; ($idx + $k) < count($chainParts) && ($bIdx + $k) < count($baseChain); $k++) {
                        $inputAncestor = normalizeName($chainParts[$idx + $k]);
                        $baseAncestor = normalizeName($baseChain[$bIdx + $k]);
                        if (!empty($inputAncestor) && !empty($baseAncestor)) {
                            // Check for conflict: if they don't match or one doesn't contain the other
                            $c = true;
                            if ($inputAncestor === $baseAncestor) {
                                $c = false;
                            } elseif (mb_strlen($inputAncestor) > 3 && mb_strlen($baseAncestor) > 3) {
                                if (mb_strpos($inputAncestor, $baseAncestor) !== false || mb_strpos($baseAncestor, $inputAncestor) !== false) {
                                    $c = false;
                                }
                            }
                            if ($c) {
                                $conflict = true;
                                break;
                            }
                        }
                    }
                    
                    if (!$conflict) {
                        $fullBaseChain = array_slice($baseChain, $bIdx);
                        $baseBridge = [
                            'name' => $bName,
                            'index' => $idx,
                            'baseIndex' => $bIdx,
                            'fullChain' => $fullBaseChain
                        ];
                        break 2;
                    }
                }
            }
        }
    }

    // 6. Smart family detection (scan all matched ancestors and found siblings to find a family association)
    $detectedFamilyId = 0;
    if ($bestBridge && isset($bestBridge['family_id']) && (int)$bestBridge['family_id'] > 0) {
        $detectedFamilyId = (int)$bestBridge['family_id'];
    } else {
        foreach ($foundNodes as $node) {
            if ($node && isset($node['family_id']) && (int)$node['family_id'] > 0) {
                $detectedFamilyId = (int)$node['family_id'];
                break;
            }
        }
        if ($detectedFamilyId === 0) {
            foreach ($foundSiblings as $sib) {
                if ($sib && isset($sib['family_id']) && (int)$sib['family_id'] > 0) {
                    $detectedFamilyId = (int)$sib['family_id'];
                    break;
                }
            }
        }
    }

    // Calculate generations to Pirkhidri Shaho
    $generationsToShaho = -1;
    foreach ($foundNodes as $fIdx => $fNode) {
        if ($fNode && ((int)$fNode['id'] === 1 || isShahoName($fNode['full_name']))) {
            $generationsToShaho = $fIdx + 1;
            break;
        }
    }
    if ($generationsToShaho === -1 && $bestBridge) {
        $dbSteps = getGenerationsToShaho($conn, $bestBridge['id']);
        if ($dbSteps >= 0) {
            $generationsToShaho = ($bestChainIndex + 1) + $dbSteps;
        }
    } elseif ($generationsToShaho === -1 && $baseBridge) {
        $generationsToShaho = ($baseBridge['index'] + 1) - $baseBridge['baseIndex'];
    }

    $baseAncestors = [];
    $oldestNode = null;
    for ($i = count($chainParts) - 1; $i >= 0; $i--) {
        if (isset($foundNodes[$i]) && $foundNodes[$i] !== null) {
            $oldestNode = $foundNodes[$i];
            break;
        }
    }
    
    if ($oldestNode) {
        $currId = (int)$oldestNode['parent_id'];
        $visitedIds = [];
        while ($currId > 0 && !isset($visitedIds[$currId])) {
            $visitedIds[$currId] = true;
            $res = $conn->query("SELECT m.id, m.full_name, m.parent_id, m.family_id, m.member_code, m.grandfather_name, m.great_grandfather_name, f.full_name as father_name 
                                 FROM members m LEFT JOIN members f ON m.parent_id = f.id 
                                 WHERE m.id = $currId");
            if ($row = $res->fetch_assoc()) {
                $baseAncestors[] = $row;
                $currId = (int)$row['parent_id'];
            } else {
                break;
            }
        }
    }

    return [
        'siblings' => $siblingsList, 'foundSiblings' => $foundSiblings, 'missingChain' => $chainParts,
        'foundNodes' => $foundNodes, 'bridgeMember' => $bestBridge, 'bestChainIndex' => $bestChainIndex,
        'baseBridge' => $baseBridge,
        'family_id' => $detectedFamilyId,
        'generations_to_shaho' => $generationsToShaho,
        'baseAncestors' => $baseAncestors
    ];
}

function getGenerationsToShaho($conn, $memberId) {
    $steps = 0;
    $currentId = (int)$memberId;
    $visited = [];
    while ($currentId > 0 && !in_array($currentId, $visited)) {
        $visited[] = $currentId;
        $res = $conn->query("SELECT parent_id, full_name FROM members WHERE id = $currentId");
        if ($res && $row = $res->fetch_assoc()) {
            if ($currentId === 1 || $currentId === 28 || isShahoName($row['full_name'])) {
                return $steps;
            }
            $parentId = (int)$row['parent_id'];
            if ($parentId > 0) {
                $steps++;
                $currentId = $parentId;
            } else {
                break;
            }
        } else {
            break;
        }
    }
    return -1;
}

function getBaseChain($conn) {
    $res = $conn->query("SELECT base_lineage FROM settings WHERE id = 1");
    if (!$res) return [];
    $row = $res->fetch_assoc();
    $text = trim($row['base_lineage'] ?? '');
    if (empty($text)) return [];
    
    // Clean blessings
    $text = preg_replace('/ ـ?صلى الله عليه و سلم ـ?/u', '', $text);
    $text = preg_replace('/ ـ?صلى الله عليه وسلم ـ?/u', '', $text);
    $text = preg_replace('/(?<!\s)(?:بن|ابن|بنت|ابنة|ابنە)/u', ' بن', $text);
    $text = preg_replace('/(?:بن|ابن|بنت|ابنة|ابنە)(?!\s)/u', 'بن ', $text);
    $text = preg_replace('/\s+(?:بن|ابن|بنت|ابنة|ابنە)\s+/u', ' بن ', " $text ");
    $parts = explode(' بن ', $text);
    $chain = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if (!empty($p)) $chain[] = $p;
    }
    return $chain;
}

function getSharedGenerationsAboveShaho($conn, $activeFamilyId) {
    $res = $conn->query("SELECT id, parent_id, family_id FROM members");
    if (!$res) return [];
    
    $parents = [];
    $families = [];
    while ($row = $res->fetch_assoc()) {
        $id = (int)$row['id'];
        $pId = (int)$row['parent_id'];
        $fId = (int)$row['family_id'];
        $parents[$id] = $pId;
        $families[$id] = $fId;
    }
    
    $shahoAncestors = [];
    $curr = 28;
    $visited = [];
    while ($curr > 0 && !isset($visited[$curr])) {
        $visited[$curr] = true;
        $shahoAncestors[] = $curr;
        $curr = $parents[$curr] ?? 0;
    }
    
    $familyAncestors = [];
    foreach ($families as $mId => $fId) {
        if ($fId == $activeFamilyId || $fId == 0) continue;
        
        $curr = $mId;
        $visitedM = [];
        while ($curr > 0 && !isset($visitedM[$curr])) {
            $visitedM[$curr] = true;
            if (in_array($curr, $shahoAncestors)) {
                $familyAncestors[$fId][$curr] = true;
            }
            $curr = $parents[$curr] ?? 0;
        }
    }
    
    $sharedList = [];
    foreach ($familyAncestors as $fId => $ancestors) {
        // Find the lowest common ancestor (LCA) in the Shaho chain
        // This is the ancestor that is closest to Shaho (i.e. smallest index in shahoAncestors)
        $lowestIndex = -1;
        foreach ($shahoAncestors as $index => $ancId) {
            if (isset($ancestors[$ancId])) {
                $lowestIndex = $index;
                break;
            }
        }
        
        if ($lowestIndex >= 0) {
            // Since the chain terminates exactly at Pirkhidri Shaho (ID 28), they meet at Shaho,
            // which represents exactly 1 shared generation in the displayed list (Pirkhidri Shaho itself).
            $sharedCount = 1;
            
            $fRes = $conn->query("SELECT family_name FROM families WHERE id = $fId");
            if ($fRow = $fRes->fetch_assoc()) {
                $sharedList[] = [
                    'family_name' => $fRow['family_name'],
                    'shared_count' => $sharedCount,
                    'shared_ancestor_ids' => [28] // Shaho itself is the union point
                ];
            }
        }
    }
    
    return $sharedList;
}
?>
