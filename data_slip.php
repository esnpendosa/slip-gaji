<?php
require_once 'config.php';
requireLogin();

$db = getDB();
$message = '';
$error = '';

// Hapus slip
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Ambil filename dulu
    $stmt = $db->prepare("SELECT pdf_file FROM gaji_karyawan WHERE id = ?");
    $stmt->execute([$id]);
    $gaji = $stmt->fetch();
    
    if ($gaji && $gaji['pdf_file']) {
        $filepath = SLIP_PATH . '/' . $gaji['pdf_file'];
        if (file_exists($filepath)) {
            @unlink($filepath);
        }
    }
    
    // Hapus dari database
    $stmt = $db->prepare("DELETE FROM gaji_karyawan WHERE id = ?");
    $stmt->execute([$id]);
    
    $message = 'Slip berhasil dihapus!';
}

// Filter
$filter_periode = $_GET['periode'] ?? '';
$filter_dept = $_GET['dept'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Query dengan filter
$query = "SELECT gk.*, k.nama as nama_karyawan, k.dept, p.nama_periode 
          FROM gaji_karyawan gk 
          JOIN karyawan k ON gk.karyawan_id = k.id 
          JOIN periode_gaji p ON gk.periode_id = p.id 
          WHERE 1=1";

$params = [];

if ($filter_periode) {
    $query .= " AND gk.periode_id = ?";
    $params[] = $filter_periode;
}

if ($filter_dept) {
    $query .= " AND k.dept = ?";
    $params[] = $filter_dept;
}

if ($filter_status) {
    $query .= " AND gk.status = ?";
    $params[] = $filter_status;
}

$query .= " ORDER BY gk.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$slip_list = $stmt->fetchAll();

// Ambil data untuk filter
$stmt = $db->query("SELECT * FROM periode_gaji ORDER BY periode_start DESC");
$periode_list = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Slip Gaji</title>
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
        .table-hover tbody tr:hover {
            background-color: rgba(0,32,96,0.05);
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
                            <a class="nav-link active" href="data_slip.php">
                                <i class="bi bi-file-earmark-text"></i> Data Slip Gaji
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="generate_slip.php">
                                <i class="bi bi-plus-circle"></i> Generate Slip
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="send_notif.php">
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
                    <h2><i class="bi bi-file-earmark-text"></i> Data Slip Gaji</h2>
                    <a href="generate_slip.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Generate Baru
                    </a>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
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
                                <label class="form-label">Department</label>
                                <select name="dept" class="form-select">
                                    <option value="">Semua Dept</option>
                                    <option value="ADMIN" <?php echo ($filter_dept == 'ADMIN') ? 'selected' : ''; ?>>ADMIN</option>
                                    <option value="PRODUKSI" <?php echo ($filter_dept == 'PRODUKSI') ? 'selected' : ''; ?>>PRODUKSI</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">Semua Status</option>
                                    <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="generated" <?php echo ($filter_status == 'generated') ? 'selected' : ''; ?>>Generated</option>
                                    <option value="sent" <?php echo ($filter_status == 'sent') ? 'selected' : ''; ?>>Terkirim</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-funnel"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Data Slip -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Daftar Slip Gaji</h5>
                        <span class="badge bg-primary">Total: <?php echo count($slip_list); ?> slip</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($slip_list)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Belum ada slip gaji.
                                <a href="generate_slip.php" class="alert-link">Generate slip baru</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Nama Karyawan</th>
                                            <th>Dept</th>
                                            <th>Periode</th>
                                            <th>Gaji Bersih</th>
                                            <th>Status</th>
                                            <th>Tanggal</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($slip_list as $index => $slip): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($slip['nama_karyawan']); ?></strong><br>
                                                    <small class="text-muted">NIK: <?php echo str_pad($slip['karyawan_id'], 5, '0', STR_PAD_LEFT); ?></small>
                                                </td>
                                                <td><?php echo $slip['dept']; ?></td>
                                                <td><?php echo htmlspecialchars($slip['nama_periode']); ?></td>
                                                <td><?php echo formatRupiah($slip['gaji_bersih']); ?></td>
                                                <td>
                                                    <?php if ($slip['status'] == 'pending'): ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php elseif ($slip['status'] == 'generated'): ?>
                                                        <span class="badge bg-info">Generated</span>
                                                    <?php elseif ($slip['status'] == 'sent'): ?>
                                                        <span class="badge bg-success">Terkirim</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo date('d M Y', strtotime($slip['created_at'])); ?><br>
                                                    <small class="text-muted"><?php echo date('H:i', strtotime($slip['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="slip_view.php?id=<?php echo $slip['id']; ?>" 
                                                           class="btn btn-primary" target="_blank" title="View">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="generate_slip.php?regenerate=<?php echo $slip['id']; ?>" 
                                                           class="btn btn-warning" title="Regenerate">
                                                            <i class="bi bi-arrow-clockwise"></i>
                                                        </a>
                                                        <a href="data_slip.php?delete=<?php echo $slip['id']; ?>" 
                                                           class="btn btn-danger" 
                                                           onclick="return confirm('Hapus slip ini?')" title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Export Options -->
                            <div class="mt-4">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-success dropdown-toggle" 
                                            data-bs-toggle="dropdown">
                                        <i class="bi bi-download"></i> Export
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="ajax_export.php?type=csv&periode=<?php echo $filter_periode; ?>">
                                                <i class="bi bi-filetype-csv"></i> CSV
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="ajax_export.php?type=excel&periode=<?php echo $filter_periode; ?>">
                                                <i class="bi bi-filetype-xlsx"></i> Excel
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="window.print()">
                                                <i class="bi bi-printer"></i> Print
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>