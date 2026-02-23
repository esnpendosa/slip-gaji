<?php
require_once 'config.php';
require_once 'PDFService.php';

// Jika akses langsung dari URL, cek login
if (!isset($_GET['id']) && !isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$db = getDB();
$pdfService = new PDFService(); // Langsung instantiate

// View berdasarkan ID gaji_karyawan
if (isset($_GET['id'])) {
    $gaji_karyawan_id = intval($_GET['id']);
    
    // Cek apakah slip sudah ada
    $stmt = $db->prepare("SELECT pdf_file FROM gaji_karyawan WHERE id = ?");
    $stmt->execute([$gaji_karyawan_id]);
    $gaji = $stmt->fetch();
    
    if ($gaji && $gaji['pdf_file']) {
        // Tampilkan slip yang sudah ada
        $filepath = SLIP_PATH . '/' . $gaji['pdf_file'];
        if (file_exists($filepath)) {
            $html = file_get_contents($filepath);
        } else {
            // Regenerate jika file tidak ditemukan
            $result = $pdfService->generateSlipFromDatabase($gaji_karyawan_id);
            if ($result['success']) {
                $html = $result['html'];
            } else {
                $html = '<h2>Slip Gaji tidak ditemukan</h2><p>Silakan hubungi admin.</p>';
            }
        }
    } else {
        // Generate baru
        $result = $pdfService->generateSlipFromDatabase($gaji_karyawan_id);
        if ($result['success']) {
            $html = $result['html'];
        } else {
            $html = '<h2>Gagal generate slip gaji</h2><p>' . ($result['message'] ?? 'Unknown error') . '</p>';
        }
    }
    
    echo $html;
    exit();
}

// View berdasarkan filename
if (isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $filepath = SLIP_PATH . '/' . $filename;
    
    if (file_exists($filepath)) {
        $html = file_get_contents($filepath);
        echo $html;
    } else {
        echo '<h2>File tidak ditemukan</h2>';
    }
    exit();
}

// Default: redirect ke dashboard jika admin
if (isLoggedIn()) {
    header('Location: index.php');
} else {
    header('Location: login.php');
}
exit();
?>