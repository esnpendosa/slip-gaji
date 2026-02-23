<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = getDB();
$id = intval($_POST['id'] ?? 0);
$nama = trim($_POST['nama'] ?? '');
$dept = $_POST['dept'] ?? 'ADMIN';
$no_hp = trim($_POST['no_hp'] ?? '');

if (empty($nama) || empty($no_hp)) {
    echo json_encode(['success' => false, 'message' => 'Nama dan No HP harus diisi']);
    exit();
}

if ($id > 0) {
    // Update
    $stmt = $db->prepare("UPDATE karyawan SET nama = ?, dept = ?, no_hp = ? WHERE id = ?");
    $stmt->execute([$nama, $dept, $no_hp, $id]);
    $message = 'Data karyawan berhasil diupdate';
} else {
    // Insert
    $stmt = $db->prepare("INSERT INTO karyawan (nama, dept, no_hp) VALUES (?, ?, ?)");
    $stmt->execute([$nama, $dept, $no_hp]);
    $message = 'Karyawan baru berhasil ditambahkan';
}

echo json_encode(['success' => true, 'message' => $message]);
?>