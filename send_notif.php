<?php
require_once 'config.php';
requireLogin();
require_once 'fonnte_api.php'; // Gunakan yang simple

$db = getDB();
$fonnte = new FonnteAPISimple();
$message = '';
$error = '';
$warning = '';

// Test koneksi jika diminta
if (isset($_GET['test'])) {
    $result = $fonnte->testConnection();
    if ($result['success']) {
        $message = '✅ <strong>Test koneksi berhasil!</strong> API Fonnte dapat diakses.';
    } else {
        $error = '❌ <strong>Test koneksi gagal:</strong> ' . $result['message'];
    }
}

// Cek jika form dikirim
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Tombol Kirim Notifikasi
    if (isset($_POST['send_notif'])) {
        $periode_id = intval($_POST['periode_id']);
        $delay = intval($_POST['delay']);
        $force_resend = isset($_POST['force_resend']) ? true : false;
        
        // Validasi periode
        if ($periode_id <= 0) {
            $error = 'Pilih periode terlebih dahulu!';
        } else {
            // Ambil data slip gaji untuk periode tersebut
            $query = "SELECT gk.*, k.nama, k.no_hp, p.nama_periode
                     FROM gaji_karyawan gk 
                     JOIN karyawan k ON gk.karyawan_id = k.id 
                     JOIN periode_gaji p ON gk.periode_id = p.id 
                     WHERE gk.periode_id = ? 
                     AND gk.pdf_file IS NOT NULL 
                     AND k.no_hp IS NOT NULL 
                     AND k.no_hp != ''";
            
            // Jika tidak force resend, hanya ambil yang belum dikirim
            if (!$force_resend) {
                $query .= " AND (gk.status IS NULL OR gk.status != 'sent')";
            }
            
            $query .= " ORDER BY k.nama";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$periode_id]);
            $slip_data = $stmt->fetchAll();
            
            if (empty($slip_data)) {
                $warning = '⚠️ Tidak ada slip gaji yang memenuhi kriteria pengiriman untuk periode ini.';
            } else {
                $results = [];
                $success_count = 0;
                $fail_count = 0;
                $skip_count = 0;
                $total_count = count($slip_data);
                
                // Hitung yang sudah pernah dikirim
                $already_sent = 0;
                foreach ($slip_data as $slip) {
                    if ($slip['status'] == 'sent') {
                        $already_sent++;
                    }
                }
                
                // Konfirmasi jika ada yang sudah pernah dikirim
                if ($already_sent > 0 && !$force_resend) {
                    $warning = "⚠️ Ada $already_sent slip yang sudah pernah dikirim. ";
                    $warning .= "Centang 'Kirim Ulang' untuk mengirim kembali.";
                } else {
                    // Proses pengiriman satu per satu
                    foreach ($slip_data as $index => $slip) {
                        // Skip jika sudah dikirim dan tidak force resend
                        if ($slip['status'] == 'sent' && !$force_resend) {
                            $skip_count++;
                            $results[] = [
                                'nama' => $slip['nama'],
                                'status' => 'skipped',
                                'message' => 'Sudah pernah dikirim'
                            ];
                            continue;
                        }
                        
                        // Generate link slip
                        $link_slip = BASE_URL . '/slip_view.php?id=' . $slip['id'];
                        
                        // Log sebelum kirim
                        error_log("Mengirim ke: " . $slip['nama'] . " - " . $slip['no_hp']);
                        
                        // Kirim via Fonnte
                        $result = $fonnte->sendSlipGaji(
                            $slip['no_hp'],
                            $slip['nama'],
                            $slip['nama_periode'],
                            $link_slip
                        );
                        
                        // Log hasil
                        error_log("Hasil: " . ($result['success'] ? 'Berhasil' : 'Gagal') . " - " . $result['message']);
                        
                        // Log ke database
                        $log_stmt = $db->prepare("INSERT INTO notifikasi_log 
                                                 (gaji_karyawan_id, no_hp, pesan, status, response, sent_at) 
                                                 VALUES (?, ?, ?, ?, ?, NOW())");
                        $log_stmt->execute([
                            $slip['id'],
                            $slip['no_hp'],
                            'Slip gaji periode ' . $slip['nama_periode'],
                            $result['success'] ? 'sent' : 'failed',
                            json_encode($result)
                        ]);
                        
                        // Update status slip jika berhasil
                        if ($result['success']) {
                            $update_stmt = $db->prepare("UPDATE gaji_karyawan 
                                                         SET status = 'sent', 
                                                         last_notified_at = NOW()
                                                         WHERE id = ?");
                            $update_stmt->execute([$slip['id']]);
                            $success_count++;
                        } else {
                            $fail_count++;
                        }
                        
                        $results[] = [
                            'nama' => $slip['nama'],
                            'status' => $result['success'] ? 'success' : 'failed',
                            'message' => $result['message']
                        ];
                        
                        // Delay antara pengiriman
                        if ($delay > 0) {
                            usleep($delay * 1000);
                        }
                    }
                    
                    // Buat pesan hasil
                    $message = "📊 <strong>HASIL PENGIRIMAN NOTIFIKASI</strong><br>";
                    $message .= "✅ <strong>Berhasil dikirim:</strong> $success_count<br>";
                    $message .= "❌ <strong>Gagal dikirim:</strong> $fail_count<br>";
                    
                    if ($skip_count > 0) {
                        $message .= "⏭️ <strong>Dilewati (sudah dikirim):</strong> $skip_count<br>";
                    }
                    
                    $message .= "📋 <strong>Total data:</strong> $total_count";
                    
                    // Tampilkan detail jika ada
                    if (!empty($results)) {
                        $message .= "<br><br><strong>Detail:</strong><br>";
                        foreach ($results as $result) {
                            $icon = $result['status'] == 'success' ? '✅' : 
                                   ($result['status'] == 'failed' ? '❌' : '⏭️');
                            $message .= "$icon " . $result['nama'] . ": " . $result['message'] . "<br>";
                        }
                    }
                }
            }
        }
    }
}

// Ambil data periode dengan info jumlah slip
$stmt = $db->query("SELECT p.*, 
                   (SELECT COUNT(*) FROM gaji_karyawan gk 
                    WHERE gk.periode_id = p.id AND gk.pdf_file IS NOT NULL) as total_slip,
                   (SELECT COUNT(*) FROM gaji_karyawan gk 
                    WHERE gk.periode_id = p.id AND gk.status = 'sent') as sent_count
                   FROM periode_gaji p 
                   ORDER BY p.periode_start DESC");
$periode_list = $stmt->fetchAll();

// Ambil log notifikasi terbaru
$stmt = $db->query("SELECT nl.*, k.nama as nama_karyawan, p.nama_periode
                   FROM notifikasi_log nl 
                   JOIN gaji_karyawan gk ON nl.gaji_karyawan_id = gk.id 
                   JOIN karyawan k ON gk.karyawan_id = k.id 
                   JOIN periode_gaji p ON gk.periode_id = p.id 
                   ORDER BY nl.created_at DESC LIMIT 10");
$log_notifikasi = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirim Notifikasi WhatsApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            background: #002060;
            color: white;
            min-height: 100vh;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 2px 0;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .debug-info {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
            font-size: 12px;
        }
        .test-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0 sidebar">
                <div class="p-3">
                    <h4 class="text-center mb-4">
                        <i class="bi bi-cash-stack"></i> Penggajian
                    </h4>
                    <hr class="bg-light">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="data_slip.php">
                                <i class="bi bi-file-earmark-text"></i> Data Slip Gaji
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="generate_slip.php">
                                <i class="bi bi-plus-circle"></i> Generate Slip
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="send_notif.php">
                                <i class="bi bi-whatsapp"></i> Kirim Notifikasi
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-whatsapp"></i> Kirim Notifikasi WhatsApp</h2>
                    <a href="send_notif.php?test=1" class="btn btn-info btn-sm">
                        <i class="bi bi-wifi"></i> Test Koneksi
                    </a>
                </div>
                
                <!-- Pesan Status -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($warning): ?>
                    <div class="alert alert-warning alert-dismissible fade show">
                        <i class="bi bi-exclamation-circle"></i> <?php echo $warning; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Debug Info -->
                <div class="debug-info">
                    <strong>Informasi Debug:</strong><br>
                    • API Key: <?php echo defined('FONNTE_API_KEY') ? '✅ Ada' : '❌ Tidak ada'; ?><br>
                    • Base URL: <?php echo BASE_URL; ?><br>
                    • Server: <?php echo $_SERVER['HTTP_HOST']; ?><br>
                    • PHP Version: <?php echo phpversion(); ?><br>
                    • CURL: <?php echo function_exists('curl_init') ? '✅ Aktif' : '❌ Tidak aktif'; ?>
                </div>
                
                <!-- Form Kirim Notifikasi -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-send"></i> Kirim Notifikasi Slip Gaji</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="sendForm">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Pilih Periode</label>
                                    <select name="periode_id" class="form-select" required id="periodeSelect">
                                        <option value="">-- Pilih Periode --</option>
                                        <?php foreach ($periode_list as $periode): 
                                            $total = $periode['total_slip'] ?? 0;
                                            $sent = $periode['sent_count'] ?? 0;
                                            $pending = $total - $sent;
                                        ?>
                                            <option value="<?php echo $periode['id']; ?>" 
                                                    data-total="<?php echo $total; ?>"
                                                    data-sent="<?php echo $sent; ?>"
                                                    data-pending="<?php echo $pending; ?>">
                                                <?php echo htmlspecialchars($periode['nama_periode']); ?> 
                                                - Total: <?php echo $total; ?> slip
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text" id="periodeInfo">
                                        Pilih periode untuk melihat detail
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Delay Antar Pesan (milidetik)</label>
                                    <input type="number" name="delay" class="form-control" value="2000" min="0" max="10000" step="500">
                                    <small class="text-muted">Rekomendasi: 2000ms (2 detik) untuk menghindari rate limit</small>
                                </div>
                            </div>
                            
                            <!-- Opsi Tambahan -->
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="force_resend" id="forceResend">
                                        <label class="form-check-label" for="forceResend">
                                            <i class="bi bi-arrow-repeat"></i> Kirim Ulang (untuk slip yang sudah pernah dikirim)
                                        </label>
                                        <div class="form-text text-danger">
                                            <i class="bi bi-exclamation-triangle"></i> HAKI: Notifikasi hanya akan dikirim ulang jika dicentang.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Cara Kerja:</strong> 
                                Sistem akan otomatis mendeteksi slip yang sudah pernah dikirim (status = 'sent').
                                Secara default, slip yang sudah dikirim akan dilewati.
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="send_notif" class="btn btn-success btn-lg">
                                    <i class="bi bi-whatsapp"></i> Kirim Notifikasi Sekarang
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Log Notifikasi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Riwayat Notifikasi Terakhir</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($log_notifikasi)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Belum ada riwayat notifikasi.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Waktu</th>
                                            <th>Nama</th>
                                            <th>Periode</th>
                                            <th>No HP</th>
                                            <th>Status</th>
                                            <th>Response</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($log_notifikasi as $log): ?>
                                            <tr>
                                                <td>
                                                    <?php echo date('d/m H:i', strtotime($log['created_at'])); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($log['nama_karyawan']); ?></td>
                                                <td><?php echo htmlspecialchars($log['nama_periode']); ?></td>
                                                <td><?php echo $log['no_hp']; ?></td>
                                                <td>
                                                    <?php if ($log['status'] == 'sent'): ?>
                                                        <span class="badge bg-success">Berhasil</span>
                                                    <?php elseif ($log['status'] == 'failed'): ?>
                                                        <span class="badge bg-danger">Gagal</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-info" 
                                                            onclick="showResponse('<?php echo htmlspecialchars($log['response']); ?>')">
                                                        <i class="bi bi-eye"></i> Detail
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Response -->
    <div class="modal fade" id="responseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Response API</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <pre id="responseContent" class="bg-light p-3" style="max-height: 400px; overflow: auto;"></pre>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tombol Test Floating -->
    <a href="send_notif.php?test=1" class="btn btn-info test-btn" title="Test Koneksi API">
        <i class="bi bi-wifi"></i> Test API
    </a>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Fungsi untuk menampilkan info periode
    document.getElementById('periodeSelect').addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const infoDiv = document.getElementById('periodeInfo');
        
        if (selected.value) {
            const total = selected.getAttribute('data-total') || 0;
            const sent = selected.getAttribute('data-sent') || 0;
            const pending = selected.getAttribute('data-pending') || 0;
            
            infoDiv.innerHTML = `
                <div class="mt-1">
                    <span class="badge bg-info">Total: ${total} slip</span>
                    <span class="badge bg-success">Sudah dikirim: ${sent}</span>
                    <span class="badge bg-warning">Belum dikirim: ${pending}</span>
                </div>
            `;
        }
    });
    
    // Fungsi untuk menampilkan response
    function showResponse(response) {
        try {
            const parsed = JSON.parse(response);
            document.getElementById('responseContent').textContent = JSON.stringify(parsed, null, 2);
        } catch (e) {
            document.getElementById('responseContent').textContent = response;
        }
        
        const modal = new bootstrap.Modal(document.getElementById('responseModal'));
        modal.show();
    }
    
    // Konfirmasi sebelum submit
    document.getElementById('sendForm').addEventListener('submit', function(e) {
        const periodeSelect = document.getElementById('periodeSelect');
        const forceResend = document.getElementById('forceResend').checked;
        
        if (!periodeSelect.value) {
            alert('Pilih periode terlebih dahulu!');
            e.preventDefault();
            return;
        }
        
        const selected = periodeSelect.options[periodeSelect.selectedIndex];
        const total = selected.getAttribute('data-total') || 0;
        const sent = selected.getAttribute('data-sent') || 0;
        const pending = selected.getAttribute('data-pending') || 0;
        
        let message = `Anda akan mengirim notifikasi untuk:\n`;
        message += `Periode: ${selected.text}\n`;
        message += `Total slip: ${total}\n`;
        message += `Sudah dikirim: ${sent}\n`;
        message += `Akan dikirim: ${pending}`;
        
        if (forceResend && sent > 0) {
            message += `\n\n⚠️ PERINGATAN: ${sent} slip akan dikirim ulang!`;
        } else if (sent > 0) {
            message += `\n\nℹ️ ${sent} slip sudah dikirim dan akan dilewati.`;
        }
        
        if (!confirm(message)) {
            e.preventDefault();
        }
    });
    </script>
</body>
</html>