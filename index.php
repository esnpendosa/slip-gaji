<?php
require_once 'config.php';
requireLogin();

// Ambil statistik
$db = getDB();

// Total karyawan
$stmt = $db->query("SELECT COUNT(*) as total FROM karyawan");
$total_karyawan = $stmt->fetch()['total'];

// Total periode
$stmt = $db->query("SELECT COUNT(*) as total FROM periode_gaji");
$total_periode = $stmt->fetch()['total'];

// Total slip yang sudah digenerate
$stmt = $db->query("SELECT COUNT(*) as total FROM gaji_karyawan WHERE pdf_file IS NOT NULL");
$total_slip = $stmt->fetch()['total'];

// Slip terbaru
$stmt = $db->query("SELECT gk.*, k.nama as nama_karyawan, p.nama_periode 
                    FROM gaji_karyawan gk 
                    JOIN karyawan k ON gk.karyawan_id = k.id 
                    JOIN periode_gaji p ON gk.periode_id = p.id 
                    WHERE gk.pdf_file IS NOT NULL 
                    ORDER BY gk.created_at DESC LIMIT 5");
$slip_terbaru = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Penggajian</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            background: #002060;
            color: white;
            min-height: 100vh;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 2px 0;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        .stat-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .bg-green-100 { background-color: #d1fae5 !important; }
        .text-green-600 { color: #059669 !important; }
        .bg-blue-100 { background-color: #dbeafe !important; }
        .text-blue-600 { color: #2563eb !important; }
        .bg-yellow-100 { background-color: #fef3c7 !important; }
        .text-yellow-600 { color: #d97706 !important; }
        .bg-red-100 { background-color: #fee2e2 !important; }
        .text-red-600 { color: #dc2626 !important; }
        .bg-purple { background-color: #8b5cf6 !important; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0 sidebar">
                <div class="p-3">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-cash-register"></i> Penggajian
                    </h4>
                    <hr class="bg-light">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="data_slip.php">
                                <i class="fas fa-file-invoice-dollar"></i> Data Slip Gaji
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="generate_slip.php">
                                <i class="fas fa-plus-circle"></i> Generate Slip
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="send_notif.php">
                                <i class="fab fa-whatsapp"></i> Kirim Notifikasi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#karyawanModal" data-bs-toggle="modal">
                                <i class="fas fa-users"></i> Data Karyawan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#periodeModal" data-bs-toggle="modal">
                                <i class="fas fa-calendar-alt"></i> Periode Gaji
                            </a>
                        </li>
                        <!-- Menu Pengaturan Ditambahkan di sini -->
                        <li class="nav-item">
                            <a class="nav-link" href="pengaturan.php">
                                <i class="fas fa-cogs"></i> Pengaturan
                            </a>
                        </li>
                        <!-- Akhir Menu Pengaturan -->
                        <li class="nav-item mt-4">
                            <a class="nav-link text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Dashboard Admin</h2>
                    <div class="text-muted">
                        <i class="bi bi-person-circle"></i> <?php echo $_SESSION['nama']; ?>
                    </div>
                </div>
                
                <!-- Statistik Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card stat-card border-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title text-primary">Total Karyawan</h5>
                                        <h2 class="mb-0"><?php echo $total_karyawan; ?></h2>
                                    </div>
                                    <div class="text-primary stat-icon">
                                        <i class="bi bi-people-fill"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card border-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title text-success">Total Periode</h5>
                                        <h2 class="mb-0"><?php echo $total_periode; ?></h2>
                                    </div>
                                    <div class="text-success stat-icon">
                                        <i class="bi bi-calendar-check-fill"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card border-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title text-warning">Slip Digenerate</h5>
                                        <h2 class="mb-0"><?php echo $total_slip; ?></h2>
                                    </div>
                                    <div class="text-warning stat-icon">
                                        <i class="bi bi-file-earmark-pdf-fill"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Slip Terbaru -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Slip Gaji Terbaru</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($slip_terbaru)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Belum ada slip gaji yang digenerate.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Nama Karyawan</th>
                                            <th>Periode</th>
                                            <th>Gaji Bersih</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($slip_terbaru as $index => $slip): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($slip['nama_karyawan']); ?></td>
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
                                                    <a href="slip_view.php?id=<?php echo $slip['id']; ?>" 
                                                       class="btn btn-sm btn-primary" target="_blank">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                    <a href="generate_slip.php?regenerate=<?php echo $slip['id']; ?>" 
                                                       class="btn btn-sm btn-warning">
                                                        <i class="bi bi-arrow-clockwise"></i> Regenerate
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-lightning-charge"></i> Aksi Cepat</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="generate_slip.php" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Generate Slip Baru
                                    </a>
                                    <a href="send_notif.php" class="btn btn-success">
                                        <i class="bi bi-whatsapp"></i> Kirim Notifikasi WhatsApp
                                    </a>
                                    <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#karyawanModal">
                                        <i class="bi bi-person-plus"></i> Tambah Karyawan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-info-circle"></i> Panduan Singkat</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle text-success"></i>
                                        <small>Pastikan data karyawan sudah lengkap</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle text-success"></i>
                                        <small>Buat periode gaji terlebih dahulu</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle text-success"></i>
                                        <small>Generate slip gaji per karyawan</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle text-success"></i>
                                        <small>Kirim notifikasi via WhatsApp</small>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Karyawan -->
    <div class="modal fade" id="karyawanModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Data Karyawan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php
                    $stmt = $db->query("SELECT * FROM karyawan ORDER BY nama");
                    $karyawan_list = $stmt->fetchAll();
                    ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>NIK</th>
                                    <th>Nama</th>
                                    <th>Dept</th>
                                    <th>No HP</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($karyawan_list as $k): ?>
                                    <tr>
                                        <td><?php echo str_pad($k['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($k['nama']); ?></td>
                                        <td><?php echo $k['dept']; ?></td>
                                        <td><?php echo $k['no_hp']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary edit-karyawan" 
                                                    data-id="<?php echo $k['id']; ?>"
                                                    data-nama="<?php echo htmlspecialchars($k['nama']); ?>"
                                                    data-dept="<?php echo $k['dept']; ?>"
                                                    data-nohp="<?php echo $k['no_hp']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <hr>
                    <h6>Tambah Karyawan Baru</h6>
                    <form id="formKaryawan" action="ajax_save_karyawan.php" method="POST">
                        <input type="hidden" name="id" id="karyawan_id" value="">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <input type="text" name="nama" class="form-control" placeholder="Nama Lengkap" required>
                            </div>
                            <div class="col-md-3 mb-2">
                                <select name="dept" class="form-control" required>
                                    <option value="ADMIN">ADMIN</option>
                                    <option value="PRODUKSI">PRODUKSI</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <input type="text" name="no_hp" class="form-control" placeholder="No HP (62...)" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Periode -->
    <div class="modal fade" id="periodeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Periode Gaji</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php
                    $stmt = $db->query("SELECT * FROM periode_gaji ORDER BY periode_start DESC");
                    $periode_list = $stmt->fetchAll();
                    ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Periode</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($periode_list as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['nama_periode']); ?></td>
                                        <td>
                                            <?php echo date('d M Y', strtotime($p['periode_start'])); ?> - 
                                            <?php echo date('d M Y', strtotime($p['periode_end'])); ?>
                                        </td>
                                        <td>
                                            <?php if ($p['status'] == 'draft'): ?>
                                                <span class="badge bg-secondary">Draft</span>
                                            <?php elseif ($p['status'] == 'final'): ?>
                                                <span class="badge bg-primary">Final</span>
                                            <?php elseif ($p['status'] == 'paid'): ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary edit-periode"
                                                    data-id="<?php echo $p['id']; ?>"
                                                    data-nama="<?php echo htmlspecialchars($p['nama_periode']); ?>"
                                                    data-start="<?php echo $p['periode_start']; ?>"
                                                    data-end="<?php echo $p['periode_end']; ?>"
                                                    data-status="<?php echo $p['status']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <hr>
                    <h6>Tambah Periode Baru</h6>
                    <form id="formPeriode" action="ajax_save_periode.php" method="POST">
                        <input type="hidden" name="id" id="periode_id" value="">
                        <div class="mb-2">
                            <input type="text" name="nama_periode" class="form-control" placeholder="Nama Periode (ex: Januari 2024)" required>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <input type="date" name="periode_start" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <input type="date" name="periode_end" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-2">
                            <select name="status" class="form-control">
                                <option value="draft">Draft</option>
                                <option value="final">Final</option>
                                <option value="paid">Paid</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Edit karyawan
    document.querySelectorAll('.edit-karyawan').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('karyawan_id').value = this.dataset.id;
            document.querySelector('input[name="nama"]').value = this.dataset.nama;
            document.querySelector('select[name="dept"]').value = this.dataset.dept;
            document.querySelector('input[name="no_hp"]').value = this.dataset.nohp;
        });
    });
    
    // Edit periode
    document.querySelectorAll('.edit-periode').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('periode_id').value = this.dataset.id;
            document.querySelector('input[name="nama_periode"]').value = this.dataset.nama;
            document.querySelector('input[name="periode_start"]').value = this.dataset.start;
            document.querySelector('input[name="periode_end"]').value = this.dataset.end;
            document.querySelector('select[name="status"]').value = this.dataset.status;
        });
    });
    
    // Form submission dengan AJAX
    document.getElementById('formKaryawan').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch(this.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat menyimpan data');
        });
    });
    
    document.getElementById('formPeriode').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch(this.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat menyimpan data');
        });
    });
    </script>
</body>
</html>