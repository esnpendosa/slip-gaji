<?php
require_once 'config.php';
requireLogin();

$db = getDB();

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $stmt = $db->prepare("SELECT gk.*, k.nama as nama_karyawan, k.dept 
                         FROM gaji_karyawan gk 
                         JOIN karyawan k ON gk.karyawan_id = k.id 
                         WHERE gk.id = ?");
    $stmt->execute([$id]);
    $gaji = $stmt->fetch();
    
    if ($gaji) {
        // Ambil detail lembur
        $stmt = $db->prepare("SELECT * FROM lembur_detail WHERE gaji_karyawan_id = ?");
        $stmt->execute([$id]);
        $lembur_details = $stmt->fetchAll();
        
        // Ambil detail potongan
        $stmt = $db->prepare("SELECT * FROM potongan_detail WHERE gaji_karyawan_id = ?");
        $stmt->execute([$id]);
        $potongan_details = $stmt->fetchAll();
        
        // Ambil data produksi jika ada
        $stmt = $db->prepare("SELECT * FROM produksi_data WHERE gaji_karyawan_id = ?");
        $stmt->execute([$id]);
        $produksi_data = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'gaji' => $gaji,
            'lembur_details' => $lembur_details,
            'potongan_details' => $potongan_details,
            'produksi_data' => $produksi_data
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Data gaji tidak ditemukan'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'ID tidak valid'
    ]);
}