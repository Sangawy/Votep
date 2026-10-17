<?php
/**
 * Lineage Core Architecture v1.5 (Ultra-Robust Search)
 */

function normalizeName($text) {
    if (empty($text)) return "";
    $s = (string)$text;
    $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{200E}\x{200F}\x{00A0}]/u', '', $s);
    
    // Remove diacritics
    $s = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $s);

    // Common Kurdish-Arabic Name Variants
    $variantPairs = [
        '/\b(?:بيرخصر|پیرخصر|بيرخدر|پیرخدر|بيرخزر|پیرخزر)\b/u' => 'پیرخضر',
        '/\bخصر\b/u' => 'خضر',
        '/\bمحەمەد\b/u' => 'محمد',
        '/\bئەحمەد\b/u' => 'احمد',
        '/\bڕەزا\b/u' => 'رضا',
        '/\bرەزا\b/u' => 'رضا',
        '/\bمەحموود\b/u' => 'محمود',
        '/\bمەحمود\b/u' => 'محمود',
        '/\bعەلی\b/u' => 'علی',
        '/\bحوسێن\b/u' => 'حسین',
        '/\bحوسەین\b/u' => 'حسین',
        '/\bحەسەن\b/u' => 'حسن',
        '/\bئیبراهیم\b/u' => 'ابراهیم',
        '/\bئیسماعیل\b/u' => 'اسماعیل',
        '/\bعومەر\b/u' => 'عمر',
        '/\bعوسمان\b/u' => 'عثمان',
        '/\bخدر\b/u' => 'خضر',
        '/\bخزر\b/u' => 'خضر',
        '/\bمستەفا\b/u' => 'مصطفی',
        '/\bتەها\b/u' => 'طه',
    ];
    $s = preg_replace(array_keys($variantPairs), array_values($variantPairs), $s);

    $s = str_replace(['ابن'], 'بن', $s);
    $map = [
        'ە' => 'ه', 'ة' => 'ه', 'ھ' => 'ه', 'ہ' => 'ه', 'ﻩ' => 'ه', 'ﻫ' => 'ه', 
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 
        'ي' => 'ی', 'ى' => 'ی', 'ێ' => 'ی',
        'ك' => 'ک', 'گ' => 'ک',
        'ڕ' => 'ر',
        'ڵ' => 'ل',
        'ڤ' => 'ف',
        'ۆ' => 'و', 'ؤ' => 'و'
    ];
    $s = str_replace(array_keys($map), array_values($map), $s);
    $s = str_replace('ـ', '', $s);
    $s = " " . mb_strtolower($s, 'UTF-8') . " ";
    $titles = ['السيد', 'السید', 'سەید', 'سید', 'ملا', 'مەلا', 'الشيخ', 'الشیخ', 'شێخ', 'شیخ', 'ئیمام', 'امام'];
    foreach($titles as $t) {
        $tNorm = str_replace(array_keys($map), array_values($map), mb_strtolower($t, 'UTF-8'));
        $s = preg_replace('/\s+' . preg_quote($tNorm, '/') . '\s+/u', ' ', $s);
    }
    $cleaned = preg_replace('/[^\p{L}\p{N}]+/u', '', $s);
    if (empty($cleaned)) {
        // If stripping titles wiped out the entire name, then the title was actually the given name (e.g. "إمام" or "شيخ")
        $s = preg_replace('/\s+(?:السید|السيد|سید|سەید)\s+/u', ' ', " " . mb_strtolower($text, 'UTF-8') . " ");
        $s = str_replace(array_keys($map), array_values($map), $s);
        $cleaned = preg_replace('/[^\p{L}\p{N}]+/u', '', $s);
    }
    return trim($cleaned);
}

function isShahoName($name) {
    if (empty($name)) return false;
    $norm = normalizeName($name);
    $hasKhidr = (mb_strpos($norm, 'پیرخضر') !== false || mb_strpos($norm, 'بیرخضر') !== false || mb_strpos($norm, 'پیرخدر') !== false || mb_strpos($norm, 'بیرخدر') !== false || mb_strpos($norm, 'پیرخصر') !== false || mb_strpos($norm, 'بیرخصر') !== false);
    $hasShaho = (mb_strpos($norm, 'شاهو') !== false);
    return ($hasKhidr && $hasShaho) || ($hasKhidr && mb_strpos($norm, 'زاهد') !== false);
}

function getSearchToken($raw) {
    return normalizeName($raw);
}

/**
 * Smart Name Tokenizer v2.0
 * Parses 3-part, 4-part, traditional (بن), Kurdish (کوڕی), and compound names seamlessly.
 */
function smartTokenizeName($input) {
    if (empty(trim($input))) return ['siblings' => [], 'chain' => []];

    // Normalize spacing and zero-width chars
    $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{200E}\x{200F}\x{00A0}]/u', ' ', $input);
    
    // Space out punctuation and brackets so words aren't glued e.g. )بن or (قريه
    $s = preg_replace('/([()\[\]{}،,])/u', ' $1 ', $s);

    // Fix merged "السيد" + name e.g. "السيدابراهيم" -> "السيد ابراهيم"
    $s = preg_replace('/(\bالسید|\bالسيد)([\p{L}])/u', '$1 $2', $s);
    
    // Space out merged words like الزاهدالملقب -> الزاهد الملقب
    $s = preg_replace('/(\p{L})(الملقب|الملقبة|الملقبە|الملقبه)/u', '$1 $2', $s);

    // Convert Kurdish relationship words to standard connectors
    $s = preg_replace('/\s+(?:کوڕانی|کورانی)\s+/u', ' ابناء ', $s);
    $s = preg_replace('/\s+(?:کوڕی|کوری)\s+/u', ' بن ', $s);
    $s = preg_replace('/\s+کچی\s+/u', ' بنت ', $s);

    // Standardize traditional connectors (word boundaries to prevent partial match)
    $s = preg_replace('/(?<!\p{L})(?:بن|ابن|بنت|ابنة|ابنە)(?!\p{L})/u', ' بن ', " $s ");
    $s = trim(preg_replace('/\s+/u', ' ', $s));

    $siblingsList = [];
    $rawTokens = [];

    $abnaCount = mb_substr_count($s, 'ابناء');
    $firstBinPos = mb_strpos($s, ' بن ');

    // Handle compound multi-branch family trees with multiple "ابناء" followed by "بن" chain
    if ($abnaCount > 1 && $firstBinPos !== false) {
        $beforeBin = trim(mb_substr($s, 0, $firstBinPos));
        $afterBin = trim(mb_substr($s, $firstBinPos + mb_strlen(' بن ')));

        $clauses = explode('ابناء', $beforeBin);
        $pairs = [];
        $prevChildrenStr = $clauses[0];

        for ($i = 1; $i < count($clauses); $i++) {
            $currStr = trim($clauses[$i]);
            if ($i === count($clauses) - 1) {
                $father = $currStr;
                $pairs[] = [
                    'children_raw' => $prevChildrenStr,
                    'father' => $father
                ];
            } else {
                if (preg_match('/^((?:السید|السيد|سید|سەید)?\s*[\p{L}]+(?:\s+(?:الدین|الدين|الله|اللە))?)\s*(?:[،,]|و|\s)\s*(.*)$/u', $currStr, $m)) {
                    $father = trim($m[1]);
                    $nextChildren = trim($m[2]);
                } else {
                    $words = explode(' ', $currStr);
                    $father = implode(' ', array_slice($words, 0, 2));
                    $nextChildren = implode(' ', array_slice($words, 2));
                }
                $pairs[] = [
                    'children_raw' => $prevChildrenStr,
                    'father' => $father
                ];
                $prevChildrenStr = $nextChildren;
            }
        }

        // Map child names -> father
        $fatherOf = [];
        foreach ($pairs as $pair) {
            $f = $pair['father'];
            $cList = preg_split('/\s+و\s+|،|,/u', $pair['children_raw']);
            foreach ($cList as $c) {
                $normC = normalizeName($c);
                if (!empty($normC)) {
                    $fatherOf[$normC] = $f;
                }
            }
        }

        $upwardChain = [];
        $currFather = $pairs[0]['father'] ?? '';
        $visited = [];

        while (!empty($currFather)) {
            $upwardChain[] = $currFather;
            $normCurr = normalizeName($currFather);
            $visited[$normCurr] = true;
            
            $foundFather = null;
            foreach ($fatherOf as $childNorm => $f) {
                if ($childNorm === $normCurr || mb_strpos($normCurr, $childNorm) !== false || mb_strpos($childNorm, $normCurr) !== false) {
                    $foundFather = $f;
                    break;
                }
            }
            if ($foundFather && !isset($visited[normalizeName($foundFather)])) {
                $currFather = $foundFather;
            } else {
                break;
            }
        }

        $binAncestors = array_filter(array_map('trim', explode(' بن ', $afterBin)));
        $fullChain = $upwardChain;
        foreach ($binAncestors as $ba) {
            $ba = trim(preg_replace('/\s+[بپ]$/u', '', $ba));
            if (!empty($ba)) {
                $fullChain[] = $ba;
            }
        }

        $sibs = [];
        $delimited = preg_replace('/(?<=\S)\s+(?=(?:السید|السيد|سید|سەید|کاک|کاکە)\s+)/u', ' ، ', $pairs[0]['children_raw']);
        $rawList = preg_split('/\s+و\s+|،|,/u', $delimited);
        foreach ($rawList as $item) {
            $item = trim($item);
            if (mb_strlen($item) >= 2) $sibs[] = $item;
        }

        return [
            'siblings' => $sibs,
            'chain' => $fullChain
        ];
    }

    if (mb_strpos($s, ' ابناء ') !== false || mb_strpos($s, 'ابناء') !== false) {
        $parts = explode('ابناء', $s);
        $sibsStr = trim($parts[0]);
        // Insert delimiter before repeated honorifics like "السيد" or "سید" or "سەید"
        // e.g. "السيد مريوان السيد محمد" -> "السيد مريوان ، السيد محمد"
        $delimited = preg_replace('/(?<=\S)\s+(?=(?:السید|السيد|سید|سەید|کاک|کاکە)\s+)/u', ' ، ', $sibsStr);
        $rawList = preg_split('/\s+و\s+|،|,/u', $delimited);
        foreach ($rawList as $item) {
            $item = trim($item);
            if (empty($item)) continue;
            // If an item still contains multiple single names without honorifics
            $words = preg_split('/\s+/u', $item);
            if (count($words) > 1 && !preg_match('/\b(السید|السيد|سید|سەید|کاک|کاکە)\b/u', $item)) {
                $compoundsAbd = ['عبد', 'عەبد', 'عەبدول', 'عبدال'];
                $compoundsSuffix = ['الدین', 'الدين', 'ئەدین', 'الله', 'اللە', 'الرحمن'];
                $muhammadPartners = ['امین', 'أمين', 'علی', 'صالح', 'باقر', 'تقی', 'جواد', 'مهدی', 'صادق', 'حسین', 'حسن'];

                $wIdx = 0;
                while ($wIdx < count($words)) {
                    $w = $words[$wIdx];
                    $nextW = $words[$wIdx+1] ?? '';
                    if (in_array($w, $compoundsAbd) && !empty($nextW)) {
                        $siblingsList[] = $w . ' ' . $nextW;
                        $wIdx += 2;
                    } elseif (in_array($nextW, $compoundsSuffix)) {
                        $siblingsList[] = $w . ' ' . $nextW;
                        $wIdx += 2;
                    } elseif (($w === 'محمد' || $w === 'محەمەد') && in_array($nextW, $muhammadPartners)) {
                        $siblingsList[] = $w . ' ' . $nextW;
                        $wIdx += 2;
                    } else {
                        $siblingsList[] = $w;
                        $wIdx++;
                    }
                }
            } else {
                if (mb_strlen($item) >= 2) $siblingsList[] = $item;
            }
        }
        $remainder = trim($parts[1]);
    } else {
        $remainder = trim($s);
    }

    if (mb_strpos($remainder, ' بن ') !== false) {
        $parts = array_filter(array_map('trim', explode(' بن ', $remainder)));
        foreach($parts as $i => $part) {
            if ($i === 0 && empty($siblingsList)) {
                $delimited = preg_replace('/(?<=\S)\s+(?=(?:السید|السيد|سید|سەید|کاک|کاکە)\s+)/u', ' ، ', $part);
                $delimited = preg_replace('/\s*(?:والسید|والسيد)\s+/u', ' ، السيد ', $delimited);
                $delimited = preg_replace('/\s+(?:و|وە)\s+/u', ' ، ', $delimited);
                if (preg_match('/(،|,)/u', $delimited)) {
                    foreach(preg_split('/[،,]/u', $delimited) as $sib) {
                        $sib = trim($sib);
                        if (mb_strlen($sib) >= 2) $siblingsList[] = $sib;
                    }
                } else {
                    $siblingsList[] = $part;
                }
            } else {
                $rawTokens[] = $part;
            }
        }
    } else {
        // Space-separated or comma-separated name (e.g. سامان علاءالدین رضا قادر)
        $words = preg_split('/\s+/u', $remainder);
        
        $compoundsAbd = ['عبد', 'عەبد', 'عەبدول', 'عبدال'];
        
        $compoundsSuffix = [
            'الدین', 'الدين', 'ئەدین', 'ئەلدین', 'الاسلام', 
            'الله', 'اللە', 'الرحمن', 'الکریم', 'الرضا', 
            'الصادق', 'الباقر', 'الکاظم', 'الجواد', 'الهادی'
        ];

        $muhammadPartners = [
            'علی', 'امین', 'صالح', 'طاهر', 'باقر', 'تقی', 'جواد', 'مهدی', 'صادق', 'حسین', 'حسن', 'رشید', 'نوری'
        ];
        
        $aliPartners = [
            'رضا', 'اصغر', 'اکبر', 'حیدر', 'نقی'
        ];

        $honorifics = ['ملا', 'مەلا', 'سید', 'سەید', 'السید', 'السيد', 'الشيخ', 'الشیخ', 'شێخ', 'شیخ', 'حاجی', 'حاج', 'کاک', 'مامۆستا'];

        $reconstructed = [];
        $wCount = count($words);
        for ($i = 0; $i < $wCount; $i++) {
            $w = $words[$i];
            $nextW = $words[$i+1] ?? '';

            // 0. Honorific + Name (e.g. مەلا علاءالدین, سەید سامان)
            if (in_array($w, $honorifics) && !empty($nextW)) {
                // If next word is also compound (e.g. ملا عبد الرحمن or ملا علاء الدین), combine them together
                $combined = $w . ' ' . $nextW;
                $i++;
                $nextNextW = $words[$i+1] ?? '';
                if ((in_array($nextW, $compoundsAbd) || in_array($nextNextW, $compoundsSuffix)) && !empty($nextNextW)) {
                    $combined .= ' ' . $nextNextW;
                    $i++;
                }
                $reconstructed[] = $combined;
                continue;
            }

            // 1. Abd + X (e.g. عبد الرحمن, عبدالقادر)
            if (in_array($w, $compoundsAbd) && !empty($nextW)) {
                $reconstructed[] = $w . ' ' . $nextW;
                $i++;
                continue;
            }

            // 2. Name + الدین / الله (e.g. علاء الدین, صلاح الدین, فضل الله)
            if (in_array($nextW, $compoundsSuffix)) {
                $reconstructed[] = $w . ' ' . $nextW;
                $i++;
                continue;
            }

            // 3. Muhammad + specific partner (e.g. محمد امین, محمد علی)
            if (($w === 'محمد' || $w === 'محەمەد') && in_array($nextW, $muhammadPartners)) {
                $reconstructed[] = $w . ' ' . $nextW;
                $i++;
                continue;
            }

            // 4. Ali + specific partner (e.g. علی رضا)
            if ($w === 'علی' && in_array($nextW, $aliPartners)) {
                $reconstructed[] = $w . ' ' . $nextW;
                $i++;
                continue;
            }

            $reconstructed[] = $w;
        }

        // Identify siblings vs ancestors
        $chainStart = 1;

        if (empty($siblingsList)) {
            // Check if "و" exists in siblings (e.g. سامان و کاروان ...)
            if (isset($reconstructed[1]) && $reconstructed[1] === 'و' && isset($reconstructed[2])) {
                $siblingsList[] = $reconstructed[0];
                $siblingsList[] = $reconstructed[2];
                $chainStart = 3;
            } else {
                $siblingsList[] = $reconstructed[0];
                $chainStart = 1;
            }
        } else {
            $chainStart = 0;
        }

        for ($k = $chainStart; $k < count($reconstructed); $k++) {
            if (!empty($reconstructed[$k])) {
                $rawTokens[] = $reconstructed[$k];
            }
        }
    }

    return [
        'siblings' => $siblingsList,
        'chain' => $rawTokens
    ];
}

function searchMembersSmart($token, $fatherToken, $conn) {
    if (mb_strlen($token) < 2) return [];
    $token = $conn->real_escape_string($token);
    
    // ULTRA-ROBUST: Search in both normalized and full_name with OR
    // This catches rows even if normalized_name column is corrupted or stale
    $sql = "SELECT m.*, f.full_name as father_name 
            FROM members m 
            LEFT JOIN members f ON m.parent_id = f.id 
            WHERE (m.normalized_name LIKE '%$token%' OR m.full_name LIKE '%$token%')";
    
    if (!empty($fatherToken)) {
        $fatherToken = $conn->real_escape_string($fatherToken);
        $sql .= " AND (f.normalized_name LIKE '%$fatherToken%' OR f.full_name LIKE '%$fatherToken%' OR f.id IS NULL)";
    }
    
    $sql .= " LIMIT 100";
    $res = $conn->query($sql);
    $results = [];
    if ($res) { while($row = $res->fetch_assoc()) $results[] = $row; }
    
    return $results;
}

function wouldCreateCycle($conn, $memberId, $newParentId) {
    if ($newParentId === null || $newParentId == 0) return false;
    if ($memberId === $newParentId) return true;
    $visited = [$memberId => true]; $currentId = $newParentId;
    $stmt = $conn->prepare("SELECT parent_id FROM members WHERE id = ?");
    if (!$stmt) return true;
    for ($i = 0; $i < 100; $i++) {
        $stmt->bind_param("i", $currentId); $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if (!$res || $res['parent_id'] === null || $res['parent_id'] == 0) { $stmt->close(); return false; }
        $currentId = (int)$res['parent_id'];
        if (isset($visited[$currentId])) { $stmt->close(); return true; }
        $visited[$currentId] = true;
    }
    $stmt->close(); return true; 
}

function updateMemberNameFields($conn, $memberId) {
    $memberId = (int)$memberId;
    if ($memberId <= 0) return;
    
    $stmt = $conn->prepare("
        SELECT m.id, m.full_name, m.parent_id,
               p1.full_name as p1_name, p2.full_name as p2_name, p3.full_name as p3_name
        FROM members m
        LEFT JOIN members p1 ON m.parent_id = p1.id
        LEFT JOIN members p2 ON p1.parent_id = p2.id
        LEFT JOIN members p3 ON p2.parent_id = p3.id
        WHERE m.id = ?
    ");
    if (!$stmt) return;
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        $first_name = extractFirstName($row['full_name']);
        $father_name = extractFirstName($row['p1_name']);
        $grandfather_name = extractFirstName($row['p2_name']);
        $great_grandfather_name = extractFirstName($row['p3_name']);
        
        $up = $conn->prepare("
            UPDATE members 
            SET first_name = ?, father_name = ?, grandfather_name = ?, great_grandfather_name = ?
            WHERE id = ?
        ");
        if ($up) {
            $up->bind_param("ssssi", $first_name, $father_name, $grandfather_name, $great_grandfather_name, $memberId);
            $up->execute();
            $up->close();
        }
    }
}

function findOrCreateCore($conn, $name, $pId, $fId, $status = 'inactive') {
    $name = trim($name); $norm = normalizeName($name);
    if (empty($norm)) return 0;
    if (mb_strlen($norm, 'UTF-8') < 2) return ['id' => 0, 'status' => 'INVALID_NAME', 'norm' => $norm];
    $pIdVal = (empty($pId) || $pId == 0) ? null : (int)$pId;
    
    // Check if a member with exact normalized name and parent ID exists in this family
    $stmt = $conn->prepare("SELECT id FROM members WHERE normalized_name = ? AND family_id = ? AND (parent_id <=> ?) ORDER BY id ASC LIMIT 1");
    if (!$stmt) return ['id' => 0, 'status' => 'SQL_PREPARE_FAILED', 'norm' => $norm];
    $stmt->bind_param("sii", $norm, $fId, $pIdVal); $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($res) return ['id' => (int)$res['id'], 'status' => 'REUSED', 'norm' => $norm];
    
    // Only reuse an existing member without parent_id (claiming it) if:
    // 1. We are explicitly building an ancestor upwards (where $pIdVal is NULL/0).
    // 2. AND that existing member has parent_id IS NULL/0.
    if ($pIdVal === null) {
        $stmt = $conn->prepare("SELECT id, parent_id, full_name FROM members WHERE normalized_name = ? AND family_id = ? AND (parent_id IS NULL OR parent_id = 0) ORDER BY id ASC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("si", $norm, $fId); $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $existingId = (int)$row['id'];
                $stmt->close();
                return ['id' => $existingId, 'status' => 'REUSED', 'norm' => $norm];
            }
            $stmt->close();
        }
    }
    
    // Inherit bnamala and village if available
    $village = '';
    $bnamala = '';
    if ($pIdVal) {
        $pRes = $conn->query("SELECT village, bnamala FROM members WHERE id = $pIdVal");
        if ($pRow = $pRes->fetch_assoc()) {
            $village = $pRow['village'] ?? '';
            $bnamala = $pRow['bnamala'] ?? '';
        }
    }
    if (empty($bnamala) && $fId > 0) {
        $fRes = $conn->query("SELECT family_name, origin_area FROM families WHERE id = $fId");
        if ($fRow = $fRes->fetch_assoc()) {
            $bnamala = $fRow['family_name'] ?? '';
            if (empty($village)) $village = $fRow['origin_area'] ?? '';
        }
    }

    $isAlive = ($status === 'active') ? 1 : 0;
    // Otherwise, create a new record for this distinct lineage node!
    $sql = "INSERT INTO members (full_name, normalized_name, parent_id, family_id, member_status, bnamala, village, is_alive, is_official_member, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return ['id' => 0, 'status' => 'SQL_PREPARE_FAILED', 'norm' => $norm];
    $stmt->bind_param("ssissssi", $name, $norm, $pIdVal, $fId, $status, $bnamala, $village, $isAlive);
    if (!$stmt->execute()) { $stmt->close(); return ['id' => 0, 'status' => 'SQL_EXECUTE_FAILED', 'norm' => $norm]; }
    $newId = $conn->insert_id; $stmt->close();
    
    // Update 4-generation name fields
    updateMemberNameFields($conn, $newId);

    return ['id' => $newId, 'status' => 'CREATED', 'norm' => $norm];
}

function extractSingleAndFatherName($fullName, $isShaho = false) {
    if (empty(trim($fullName))) return ['single' => '', 'father' => '', 'core' => ''];
    if ($isShaho || isShahoName($fullName)) {
        return ['single' => $fullName, 'father' => '', 'core' => 'محمد'];
    }

    // 1. Remove parenthetical nicknames like ( وێڵه ) or (قريه باني مورد) or ( بابه شێخ صفاخانه )
    $cleaned = preg_replace('/\s*\(.*?\)/u', '', $fullName);
    // Remove "الملقب بـ ..." or "الملقب ب..." or "الملقب ..."
    $cleaned = preg_replace('/\s+الملقب\s+(?:بـ|ب)?\s*[\p{L}\s]+/u', '', $cleaned);
    // Remove location suffixes at end like "في قرية باني مورد"
    $cleaned = preg_replace('/\s+في\s+[\p{L}\s]+/u', '', $cleaned);

    // Normalize spacing
    $cleaned = trim(preg_replace('/\s+/u', ' ', $cleaned));

    $titles = [
        'العلامة', 'العلامه', 
        'الحاجی', 'الحاجى', 'الحاج', 'حاجی', 'حاجى', 'حاج',
        'الشيخ', 'الشیخ', 'شێخ', 'شیخ', 'بابەشێخ', 'بابە شێخ', 'بابەشیخ',
        'ملا', 'مەلا', 'مولا', 
        'السيد', 'السید', 'سید', 'سەید', 
        'دکتۆر', 'دكتور', 'د.', 'مامۆستا', 'الأستاذ', 'الاستاذ',
        'کاکە', 'کاک'
    ];

    // Sort titles by length descending
    usort($titles, function($a, $b) { return mb_strlen($b) - mb_strlen($a); });

    // Repeatedly strip all leading titles
    $hasSayyid = false;
    $changed = true;
    while ($changed) {
        $changed = false;
        foreach ($titles as $t) {
            if (mb_strpos($cleaned, $t . ' ') === 0 || $cleaned === $t) {
                if (in_array($t, ['السيد', 'السید', 'سید', 'سەید'])) {
                    $hasSayyid = true;
                }
                $cleaned = trim(mb_substr($cleaned, mb_strlen($t)));
                $changed = true;
                break;
            }
        }
    }

    $compoundPrefixes = [
        'عبد', 'عەبد', 'عەبدول', 'عبدال',
        'علاء', 'علأ', 'علا',
        'فخر', 'نصر', 'نجم', 'بهاء', 'صلاح', 'نور', 'جمال', 'کمال', 'محی', 'حسام', 'شمس', 'تاج', 'سراج', 'سيف',
        'کاکە', 'کاک', 'حەمە', 'حمه'
    ];
    $compoundSuffixes = [
        'الدین', 'الدين', 'ئەدین', 'ئەلدین', 'الاسلام', 'الله', 'اللە', 'الرحمن', 'الکریم', 'الرضا', 'الصادق', 'الباقر', 'الکاظم', 'الجواد', 'الهادی', 'العزیز', 'الخالق', 'السلام', 'القادر', 'اللطیف'
    ];
    $doubleNames = [
        'محمد امین', 'محمد أمين', 'محمد علی', 'محمد صالح', 'محمد باقر', 'محمد تقی', 'محمد جواد', 'محمد مهدی', 'محمد صادق', 'محمد حسین', 'محمد حسن', 'محمد رشید', 'محمد نوری', 'محمد رؤوف', 'محمد ڕؤوف', 'محمد صدیق',
        'علی رضا', 'علی اصغر', 'علی اکبر', 'علی حیدر', 'علی نقی',
        'کاکه حمه', 'کاکە حەمە', 'کاکه ڕەش', 'کاکە ڕەش', 'کاکه رش',
        'حمه امین', 'حەمە امین', 'حمه صالح', 'حەمە صالح', 'حمه رشید', 'حەمە رشید'
    ];

    // Check double names
    foreach ($doubleNames as $dn) {
        if (mb_strpos($cleaned, $dn . ' ') === 0 || $cleaned === $dn) {
            $core = $dn;
            $rem = trim(mb_substr($cleaned, mb_strlen($dn)));
            $father = !empty($rem) ? explode(' ', $rem)[0] : '';
            return [
                'single' => 'السید ' . $core,
                'core' => $core,
                'father' => $father
            ];
        }
    }

    $words = explode(' ', $cleaned);
    $w0 = $words[0] ?? '';
    $w1 = $words[1] ?? '';
    $w2 = $words[2] ?? '';

    // If compound prefix (e.g. عبد الله or علاء الدین)
    if (in_array($w0, $compoundPrefixes) && !empty($w1)) {
        $core = $w0 . ' ' . $w1;
        $father = $w2;
    } elseif (in_array($w1, $compoundSuffixes)) {
        $core = $w0 . ' ' . $w1;
        $father = $w2;
    } else {
        $core = $w0;
        $father = $w1;
    }

    // Strip branch suffix from core name if attached e.g. "علی صفاخانه"
    if ($core === 'علی' && $w1 === 'صفاخانه') {
        $father = $words[2] ?? '';
    }

    return [
        'single' => 'السید ' . $core,
        'core' => $core,
        'father' => $father
    ];
}
?>
