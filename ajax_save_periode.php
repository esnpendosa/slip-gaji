<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = getDB();
$id = intval($_POST['id'] ?? 0);
$nama_periode = trim($_POST['nama_periode'] ?? '');
$periode_start = $_POST['periode_start'] ?? '';
$periode_end = $_POST['periode_end'] ?? '';
$status = $_POST['status'] ?? 'draft';

if (empty($nama_periode) || empty($periode_start) || empty($periode_end)) {
    echo json_encode(['success' => false, 'message' => 'Semua field harus diisi']);
    exit();
}

if ($id > 0) {
    // Update
    $stmt = $db->prepare("UPDATE periode_gaji SET nama_periode = ?, periode_start = ?, periode_end = ?, status = ? WHERE id = ?");
    $stmt->execute([$nama_periode, $periode_start, $periode_end, $status, $id]);
    $message = 'Periode berhasil diupdate';
} else {
    // Insert
    $stmt = $db->prepare("INSERT INTO periode_gaji (nama_periode, periode_start, periode_end, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nama_periode, $periode_start, $periode_end, $status]);
    $message = 'Periode baru berhasil ditambahkan';
}

echo json_encode(['success' => true, 'message' => $message]);
?>