<?php
require_once 'config.php';
requireLogin();

$db = getDB();

// Filter
$filter_periode = $_GET['periode'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_date = $_GET['date'] ?? '';

// Query dengan filter
$query = "SELECT nl.*, k.nama as nama_karyawan, k.dept, p.nama_periode, gk.gaji_bersih
          FROM notifikasi_log nl 
          JOIN gaji_karyawan gk ON nl.gaji_karyawan_id = gk.id 
          JOIN karyawan k ON gk.karyawan_id = k.id 
          JOIN periode_gaji p ON gk.periode_id = p.id 
          WHERE 1=1";

$params = [];

if ($filter_periode) {
    $query .= " AND gk.periode_id = ?";
    $params[] = $filter_periode;
}

if ($filter_status) {
    $query .= " AND nl.status = ?";
    $params[] = $filter_status;
}

if ($filter_date) {
    $query .= " AND DATE(nl.created_at) = ?";
    $params[] = $filter_date;
}

$query .= " ORDER BY nl.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Ambil data untuk filter
$stmt = $db->query("SELECT * FROM periode_gaji ORDER BY periode_start DESC");
$periode_list = $stmt->fetchAll();

// Statistik
$total_sent = $db->query("SELECT COUNT(*) as total FROM notifikasi_log WHERE status = 'sent'")->fetch()['total'];
$total_failed = $db->query("SELECT COUNT(*) as total FROM notifikasi_log WHERE status = 'failed'")->fetch()['total'];
$total_all = $db->query("SELECT COUNT(*) as total FROM notifikasi_log")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Notifikasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-chat-text"></i> Data Notifikasi WhatsApp</h2>
            <a href="send_notif.php" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>
        
        <!-- Statistik -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6>Total Notifikasi</h6>
                        <h3><?php echo $total_all; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6>Berhasil</h6>
                        <h3><?php echo $total_sent; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h6>Gagal</h6>
                        <h3><?php echo $total_failed; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6>Tanggal Terakhir</h6>
                        <h6><?php echo date('d M Y'); ?></h6>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Filter Data</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Periode</label>
                        <select name="periode" class="form-select">
                            <option value="">Semua Periode</option>
                            <?php foreach ($periode_list as $periode): ?>
                                <option value="<?php echo $periode['id']; ?>" 
                                    <?php echo ($filter_periode == $periode['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($periode['nama_periode']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="sent" <?php echo ($filter_status == 'sent') ? 'selected' : ''; ?>>Berhasil</option>
                            <option value="failed" <?php echo ($filter_status == 'failed') ? 'selected' : ''; ?>>Gagal</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="date" class="form-control" value="<?php echo $filter_date; ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Data Notifikasi -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Riwayat Notifikasi</h5>
            </div>
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Tidak ada data notifikasi.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tanggal</th>
                                    <th>Nama Karyawan</th>
                                    <th>Dept</th>
                                    <th>Periode</th>
                                    <th>No HP</th>
                                    <th>Status</th>
                                    <th>Response</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $index => $notif): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($notif['created_at'])); ?><br>
                                            <small><?php echo date('H:i:s', strtotime($notif['created_at'])); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($notif['nama_karyawan']); ?></td>
                                        <td><?php echo $notif['dept']; ?></td>
                                        <td><?php echo htmlspecialchars($notif['nama_periode']); ?></td>
                                        <td><?php echo $notif['no_hp']; ?></td>
                                        <td>
                                            <?php if ($notif['status'] == 'sent'): ?>
                                                <span class="badge bg-success">Berhasil</span>
                                            <?php elseif ($notif['status'] == 'failed'): ?>
                                                <span class="badge bg-danger">Gagal</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info" 
                                                    onclick="showResponse('<?php echo htmlspecialchars($notif['response']); ?>')">
                                                <i class="bi bi-eye"></i> Lihat
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
    
    <!-- Modal Response -->
    <div class="modal fade" id="responseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Response API</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <pre id="responseContent" class="bg-light p-3" style="max-height: 400px; overflow: auto;"></pre>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
    </script>
</body>
</html>