<?php
require_once 'config.php';
requireLogin();

$db = getDB();

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $stmt = $db->prepare("SELECT * FROM karyawan WHERE id = ?");
    $stmt->execute([$id]);
    $karyawan = $stmt->fetch();
    
    if ($karyawan) {
        echo json_encode([
            'success' => true,
            'karyawan' => $karyawan
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Karyawan tidak ditemukan'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'ID tidak valid'
    ]);
}