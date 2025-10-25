<?php
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['center_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Center ID is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$center_name = $input['center_id']; // This is actually the center name

// Log the received center name for debugging
error_log("Received center_name: '$center_name'");

try {
    // Get voted voters with all required information
    $voted_query = "SELECT full_name, voter_number, mobile as mobile_number, 
                           voting_center_name, scanned_at as scan_time, unit
                    FROM voters 
                    WHERE voting_center_name = ? AND status = 'voted'
                    ORDER BY scanned_at DESC";
    
    $voted_stmt = $pdo->prepare($voted_query);
    $voted_stmt->execute([$center_name]);
    $voted_voters = $voted_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Raw voted voters count: " . count($voted_voters));
    
    // Format scan_time for voted voters and clean unit info
    foreach ($voted_voters as &$voter) {
        if ($voter['scan_time']) {
            $voter['scan_time'] = date('H:i:s', strtotime($voter['scan_time']));
        }
        // Use actual unit from database or set default
        if (empty($voter['unit'])) {
            $voter['unit'] = 'نەزانراو';
        }
    }
    
    error_log("Processed voted voters count: " . count($voted_voters));
    
    // Get not voted voters with all required information
    $not_voted_query = "SELECT full_name, voter_number, mobile as mobile_number, 
                               voting_center_name, unit
                        FROM voters 
                        WHERE voting_center_name = ? AND (status = 'not_voted' OR status = '' OR status = 'not_arrived' OR status IS NULL)
                        ORDER BY full_name";
    
    $not_voted_stmt = $pdo->prepare($not_voted_query);
    $not_voted_stmt->execute([$center_name]);
    $not_voted_voters = $not_voted_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Raw not voted voters count: " . count($not_voted_voters));
    
    // Add empty scan_time and clean unit for not voted voters
    foreach ($not_voted_voters as &$voter) {
        $voter['scan_time'] = null;
        // Use actual unit from database or set default
        if (empty($voter['unit'])) {
            $voter['unit'] = 'نەزانراو';
        }
    }
    
    error_log("Processed not voted voters count: " . count($not_voted_voters));
    
    $result = [
        'voted' => $voted_voters,
        'notVoted' => $not_voted_voters
    ];
    
    // Log the result counts for debugging
    error_log("Result - voted: " . count($voted_voters) . ", notVoted: " . count($not_voted_voters));
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>