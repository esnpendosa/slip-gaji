<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = getDB();

// Cek notifikasi baru dalam 1 menit terakhir
$stmt = $db->query("SELECT COUNT(*) as count FROM notifikasi_log 
                   WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
$result = $stmt->fetch();

// Cek total notifikasi
$stmt = $db->query("SELECT COUNT(*) as total FROM notifikasi_log");
$total = $stmt->fetch();

echo json_encode([
    'new_count' => $result['count'],
    'total' => $total['total'],
    'updated' => $result['count'] > 0
]);
?>