<?php
session_start();

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Koneksi database
$host = 'localhost';
$dbname = 'sistem_penggajian';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Inisialisasi variabel
$error = '';
$success = '';
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'gaji';

// Fungsi untuk mendapatkan pengaturan berdasarkan tab
function getSettings($pdo, $table) {
    try {
        $stmt = $pdo->query("SELECT * FROM $table LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

// Fungsi untuk mengupdate pengaturan
function updateSettings($pdo, $table, $data, $id = 1) {
    try {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
            $values[":$key"] = $value;
        }
        
        $sql = "UPDATE $table SET " . implode(', ', $fields) . " WHERE id = :id";
        $values[':id'] = $id;
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($values);
    } catch (PDOException $e) {
        return false;
    }
}

// Proses form pengaturan gaji
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_gaji'])) {
    $data = [
        'nama_perusahaan' => $_POST['nama_perusahaan'],
        'alamat' => $_POST['alamat'],
        'telepon' => $_POST['telepon'],
        'gaji_pokok_global' => str_replace('.', '', $_POST['gaji_pokok_global']),
        'bpjs_jht_percent' => $_POST['bpjs_jht_percent'],
        'bpjs_jkn_percent' => $_POST['bpjs_jkn_percent'],
        'uang_makan' => str_replace('.', '', $_POST['uang_makan']),
        'tunjangan_lain' => str_replace('.', '', $_POST['tunjangan_lain'])
    ];
    
    if (updateSettings($pdo, 'pengaturan_gaji', $data)) {
        $success = "Pengaturan gaji berhasil diperbarui!";
    } else {
        $error = "Gagal menyimpan pengaturan gaji!";
    }
}

// Proses form pengaturan sistem
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_sistem'])) {
    $data = [
        'gaji_pokok_global' => str_replace('.', '', $_POST['gaji_pokok_global']),
        'bpjs_jht_percent' => $_POST['bpjs_jht_percent'],
        'bpjs_jkn_percent' => $_POST['bpjs_jkn_percent']
    ];
    
    if (updateSettings($pdo, 'pengaturan_sistem', $data)) {
        $success = "Pengaturan sistem berhasil diperbarui!";
    } else {
        $error = "Gagal menyimpan pengaturan sistem!";
    }
}

// Proses form pengaturan TTD
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_ttd'])) {
    $data = [
        'nama_direktur' => $_POST['nama_direktur'],
        'jabatan' => $_POST['jabatan']
    ];
    
    // Handle upload file tanda tangan
    if (isset($_FILES['file_ttd']) && $_FILES['file_ttd']['error'] == 0) {
        $allowed = ['png', 'jpg', 'jpeg', 'gif'];
        $filename = $_FILES['file_ttd']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = 'ttd_direktur_' . time() . '.' . $ext;
            $upload_path = 'uploads/ttd/' . $new_filename;
            
            if (move_uploaded_file($_FILES['file_ttd']['tmp_name'], $upload_path)) {
                // Hapus file lama jika ada
                $old_ttd = getSettings($pdo, 'pengaturan_ttd');
                if (!empty($old_ttd['file_ttd']) && file_exists('uploads/ttd/' . $old_ttd['file_ttd'])) {
                    unlink('uploads/ttd/' . $old_ttd['file_ttd']);
                }
                
                $data['file_ttd'] = $new_filename;
            }
        }
    }
    
    if (updateSettings($pdo, 'pengaturan_ttd', $data)) {
        $success = "Pengaturan tanda tangan berhasil diperbarui!";
    } else {
        $error = "Gagal menyimpan pengaturan tanda tangan!";
    }
}

// Proses form jenis lembur
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_lembur'])) {
    if ($_POST['lembur_id'] == 0) {
        // Tambah baru
        $sql = "INSERT INTO lembur_jenis (nama, nilai_per_jam, deskripsi) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $_POST['nama_lembur'],
            str_replace('.', '', $_POST['nilai_per_jam']),
            $_POST['deskripsi_lembur']
        ]);
    } else {
        // Update
        $sql = "UPDATE lembur_jenis SET nama = ?, nilai_per_jam = ?, deskripsi = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $_POST['nama_lembur'],
            str_replace('.', '', $_POST['nilai_per_jam']),
            $_POST['deskripsi_lembur'],
            $_POST['lembur_id']
        ]);
    }
    
    if ($success) {
        $success = "Data jenis lembur berhasil disimpan!";
    } else {
        $error = "Gagal menyimpan data jenis lembur!";
    }
}

// Proses hapus jenis lembur
if (isset($_GET['delete_lembur'])) {
    $id = $_GET['delete_lembur'];
    $stmt = $pdo->prepare("DELETE FROM lembur_jenis WHERE id = ?");
    if ($stmt->execute([$id])) {
        $success = "Jenis lembur berhasil dihapus!";
    } else {
        $error = "Gagal menghapus jenis lembur!";
    }
}

// Proses form jenis potongan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_potongan'])) {
    $data = [
        'nama' => $_POST['nama_potongan'],
        'jenis' => $_POST['jenis_potongan'],
        'nilai' => str_replace('.', '', $_POST['nilai_potongan']),
        'berlaku' => $_POST['berlaku_potongan']
    ];
    
    if ($_POST['potongan_id'] == 0) {
        // Tambah baru
        $sql = "INSERT INTO potongan_jenis (nama, jenis, nilai, berlaku) VALUES (:nama, :jenis, :nilai, :berlaku)";
    } else {
        // Update
        $sql = "UPDATE potongan_jenis SET nama = :nama, jenis = :jenis, nilai = :nilai, berlaku = :berlaku WHERE id = :id";
        $data['id'] = $_POST['potongan_id'];
    }
    
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($data)) {
        $success = "Data jenis potongan berhasil disimpan!";
    } else {
        $error = "Gagal menyimpan data jenis potongan!";
    }
}

// Proses hapus jenis potongan
if (isset($_GET['delete_potongan'])) {
    $id = $_GET['delete_potongan'];
    $stmt = $pdo->prepare("DELETE FROM potongan_jenis WHERE id = ?");
    if ($stmt->execute([$id])) {
        $success = "Jenis potongan berhasil dihapus!";
    } else {
        $error = "Gagal menghapus jenis potongan!";
    }
}

// Ambil data pengaturan
$pengaturan_gaji = getSettings($pdo, 'pengaturan_gaji');
$pengaturan_sistem = getSettings($pdo, 'pengaturan_sistem');
$pengaturan_ttd = getSettings($pdo, 'pengaturan_ttd');
$lembur_jenis = $pdo->query("SELECT * FROM lembur_jenis ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$potongan_jenis = $pdo->query("SELECT * FROM potongan_jenis ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// Format angka untuk display
function formatRupiah($angka) {
    return number_format($angka, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Penggajian - Pengaturan</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .nav {
            background-color: white;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .nav-tabs {
            display: flex;
            flex-wrap: wrap;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .nav-tab {
            padding: 15px 25px;
            cursor: pointer;
            background: none;
            border: none;
            font-size: 16px;
            color: #666;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        
        .nav-tab:hover {
            background-color: #f8f9fa;
            color: #667eea;
        }
        
        .nav-tab.active {
            color: #667eea;
            border-bottom: 3px solid #667eea;
            background-color: #f8f9fa;
        }
        
        .tab-content {
            display: none;
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.5s;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            animation: fadeIn 0.5s;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .btn-primary {
            background-color: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #5a6fd8;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 14px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #444;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .table-actions {
            display: flex;
            gap: 5px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
        }
        
        .close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .close:hover {
            color: #333;
        }
        
        .card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #444;
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .file-preview {
            max-width: 200px;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 5px;
        }
        
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .back-btn:hover {
            background-color: #5a6268;
        }
        
        @media (max-width: 768px) {
            .nav-tabs {
                flex-direction: column;
            }
            
            .nav-tab {
                width: 100%;
                text-align: left;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ Pengaturan Sistem</h1>
            <p>Kelola pengaturan sistem penggajian</p>
        </div>
        
        <a href="dashboard.php" class="back-btn">← Kembali ke Dashboard</a>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="nav">
            <div class="nav-tabs">
                <button class="nav-tab <?php echo $current_tab == 'gaji' ? 'active' : ''; ?>" 
                        onclick="switchTab('gaji')">🏢 Pengaturan Gaji</button>
                <button class="nav-tab <?php echo $current_tab == 'sistem' ? 'active' : ''; ?>" 
                        onclick="switchTab('sistem')">⚡ Pengaturan Sistem</button>
                <button class="nav-tab <?php echo $current_tab == 'ttd' ? 'active' : ''; ?>" 
                        onclick="switchTab('ttd')">✍️ Tanda Tangan</button>
                <button class="nav-tab <?php echo $current_tab == 'lembur' ? 'active' : ''; ?>" 
                        onclick="switchTab('lembur')">⏰ Jenis Lembur</button>
                <button class="nav-tab <?php echo $current_tab == 'potongan' ? 'active' : ''; ?>" 
                        onclick="switchTab('potongan')">💰 Jenis Potongan</button>
            </div>
        </div>
        
        <!-- Tab: Pengaturan Gaji -->
        <div id="tab-gaji" class="tab-content <?php echo $current_tab == 'gaji' ? 'active' : ''; ?>">
            <h2 style="margin-bottom: 20px;">🏢 Pengaturan Gaji Perusahaan</h2>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nama_perusahaan">Nama Perusahaan</label>
                        <input type="text" id="nama_perusahaan" name="nama_perusahaan" 
                               class="form-control" value="<?php echo htmlspecialchars($pengaturan_gaji['nama_perusahaan'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="telepon">Telepon</label>
                        <input type="text" id="telepon" name="telepon" 
                               class="form-control" value="<?php echo htmlspecialchars($pengaturan_gaji['telepon'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="alamat">Alamat</label>
                    <textarea id="alamat" name="alamat" class="form-control" rows="3"><?php echo htmlspecialchars($pengaturan_gaji['alamat'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="gaji_pokok_global">Gaji Pokok Global</label>
                        <input type="text" id="gaji_pokok_global" name="gaji_pokok_global" 
                               class="form-control" value="<?php echo formatRupiah($pengaturan_gaji['gaji_pokok_global'] ?? 0); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="uang_makan">Uang Makan</label>
                        <input type="text" id="uang_makan" name="uang_makan" 
                               class="form-control" value="<?php echo formatRupiah($pengaturan_gaji['uang_makan'] ?? 0); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="tunjangan_lain">Tunjangan Lain</label>
                        <input type="text" id="tunjangan_lain" name="tunjangan_lain" 
                               class="form-control" value="<?php echo formatRupiah($pengaturan_gaji['tunjangan_lain'] ?? 0); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="bpjs_jht_percent">BPJS JHT (%)</label>
                        <input type="number" step="0.01" id="bpjs_jht_percent" name="bpjs_jht_percent" 
                               class="form-control" value="<?php echo htmlspecialchars($pengaturan_gaji['bpjs_jht_percent'] ?? '3.00'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="bpjs_jkn_percent">BPJS JKN (%)</label>
                        <input type="number" step="0.01" id="bpjs_jkn_percent" name="bpjs_jkn_percent" 
                               class="form-control" value="<?php echo htmlspecialchars($pengaturan_gaji['bpjs_jkn_percent'] ?? '1.00'); ?>">
                    </div>
                </div>
                
                <button type="submit" name="save_gaji" class="btn btn-primary">💾 Simpan Pengaturan</button>
            </form>
        </div>
        
        <!-- Tab: Pengaturan Sistem -->
        <div id="tab-sistem" class="tab-content <?php echo $current_tab == 'sistem' ? 'active' : ''; ?>">
            <h2 style="margin-bottom: 20px;">⚡ Pengaturan Sistem</h2>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="sys_gaji_pokok">Gaji Pokok Global</label>
                        <input type="text" id="sys_gaji_pokok" name="gaji_pokok_global" 
                               class="form-control" value="<?php echo formatRupiah($pengaturan_sistem['gaji_pokok_global'] ?? 0); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="sys_bpjs_jht">BPJS JHT (%)</label>
                        <input type="number" step="0.01" id="sys_bpjs_jht" name="bpjs_jht_percent" 
                               class="form-control" value="<?php echo htmlspecialchars($pengaturan_sistem['bpjs_jht_percent'] ?? '3.00'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="sys_bpjs_jkn">BPJS JKN (%)</label>
                        <input type="number" step="0.01" id="sys_bpjs_jkn" name="bpjs_jkn_percent" 
                               class="form-control" value="<?php echo htmlspecialchars($pengaturan_sistem['bpjs_jkn_percent'] ?? '1.00'); ?>">
                    </div>
                </div>
                
                <button type="submit" name="save_sistem" class="btn btn-primary">💾 Simpan Pengaturan</button>
            </form>
        </div>
        
        <!-- Tab: Tanda Tangan -->
        <div id="tab-ttd" class="tab-content <?php echo $current_tab == 'ttd' ? 'active' : ''; ?>">
            <h2 style="margin-bottom: 20px;">✍️ Pengaturan Tanda Tangan</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nama_direktur">Nama Direktur</label>
                        <input type="text" id="nama_direktur" name="nama_direktur" 
                               class="form-control" value="<?php echo htmlspecialchars($pengaturan_ttd['nama_direktur'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="jabatan">Jabatan</label>
                        <input type="text" id="jabatan" name="jabatan" 
                               class="form-control" value="<?php echo htmlspecialchars($pengaturan_ttd['jabatan'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="file_ttd">File Tanda Tangan</label>
                    <input type="file" id="file_ttd" name="file_ttd" class="form-control" accept="image/*">
                    
                    <?php if (!empty($pengaturan_ttd['file_ttd']) && file_exists('uploads/ttd/' . $pengaturan_ttd['file_ttd'])): ?>
                    <div class="file-info">
                        <span>File saat ini:</span>
                        <a href="uploads/ttd/<?php echo htmlspecialchars($pengaturan_ttd['file_ttd']); ?>" target="_blank">
                            <?php echo htmlspecialchars($pengaturan_ttd['file_ttd']); ?>
                        </a>
                        <img src="uploads/ttd/<?php echo htmlspecialchars($pengaturan_ttd['file_ttd']); ?>" 
                             alt="Tanda Tangan" class="file-preview">
                    </div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" name="save_ttd" class="btn btn-primary">💾 Simpan Pengaturan</button>
            </form>
        </div>
        
        <!-- Tab: Jenis Lembur -->
        <div id="tab-lembur" class="tab-content <?php echo $current_tab == 'lembur' ? 'active' : ''; ?>">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>⏰ Jenis Lembur</h2>
                <button type="button" class="btn btn-primary" onclick="openLemburModal(0)">➕ Tambah Jenis Lembur</button>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Jenis</th>
                        <th>Nilai per Jam</th>
                        <th>Deskripsi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lembur_jenis)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">Belum ada data jenis lembur</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lembur_jenis as $index => $lembur): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($lembur['nama']); ?></td>
                            <td>Rp <?php echo formatRupiah($lembur['nilai_per_jam']); ?></td>
                            <td><?php echo htmlspecialchars($lembur['deskripsi'] ?? '-'); ?></td>
                            <td class="table-actions">
                                <button class="btn btn-secondary btn-sm" 
                                        onclick="openLemburModal(<?php echo $lembur['id']; ?>)">✏️ Edit</button>
                                <a href="?tab=lembur&delete_lembur=<?php echo $lembur['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Yakin ingin menghapus?')">🗑️ Hapus</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Tab: Jenis Potongan -->
        <div id="tab-potongan" class="tab-content <?php echo $current_tab == 'potongan' ? 'active' : ''; ?>">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>💰 Jenis Potongan</h2>
                <button type="button" class="btn btn-primary" onclick="openPotonganModal(0)">➕ Tambah Jenis Potongan</button>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Potongan</th>
                        <th>Jenis</th>
                        <th>Nilai</th>
                        <th>Berlaku Untuk</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($potongan_jenis)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">Belum ada data jenis potongan</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($potongan_jenis as $index => $potongan): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($potongan['nama']); ?></td>
                            <td>
                                <span style="padding: 3px 8px; border-radius: 3px; font-size: 12px; 
                                      background-color: <?php echo $potongan['jenis'] == 'fixed' ? '#28a745' : '#007bff'; ?>; 
                                      color: white;">
                                    <?php echo $potongan['jenis'] == 'fixed' ? 'Nominal Tetap' : 'Persentase'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($potongan['jenis'] == 'fixed'): ?>
                                    Rp <?php echo formatRupiah($potongan['nilai']); ?>
                                <?php else: ?>
                                    <?php echo number_format($potongan['nilai'], 2); ?>%
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="padding: 3px 8px; border-radius: 3px; font-size: 12px; 
                                      background-color: <?php 
                                        echo $potongan['berlaku'] == 'semua' ? '#6c757d' : 
                                             ($potongan['berlaku'] == 'produksi' ? '#17a2b8' : '#ffc107'); ?>; 
                                      color: white;">
                                    <?php echo ucfirst($potongan['berlaku']); ?>
                                </span>
                            </td>
                            <td class="table-actions">
                                <button class="btn btn-secondary btn-sm" 
                                        onclick="openPotonganModal(<?php echo $potongan['id']; ?>)">✏️ Edit</button>
                                <a href="?tab=potongan&delete_potongan=<?php echo $potongan['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Yakin ingin menghapus?')">🗑️ Hapus</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal Lembur -->
    <div id="modalLembur" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="modalLemburTitle">Tambah Jenis Lembur</div>
                <button class="close" onclick="closeModal('modalLembur')">&times;</button>
            </div>
            <form method="POST" action="" id="formLembur">
                <input type="hidden" id="lembur_id" name="lembur_id" value="0">
                <div class="form-group">
                    <label for="nama_lembur">Nama Jenis Lembur</label>
                    <input type="text" id="nama_lembur" name="nama_lembur" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="nilai_per_jam">Nilai per Jam</label>
                    <input type="text" id="nilai_per_jam" name="nilai_per_jam" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="deskripsi_lembur">Deskripsi (Opsional)</label>
                    <textarea id="deskripsi_lembur" name="deskripsi_lembur" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" name="save_lembur" class="btn btn-primary">💾 Simpan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalLembur')">Batal</button>
            </form>
        </div>
    </div>
    
    <!-- Modal Potongan -->
    <div id="modalPotongan" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="modalPotonganTitle">Tambah Jenis Potongan</div>
                <button class="close" onclick="closeModal('modalPotongan')">&times;</button>
            </div>
            <form method="POST" action="" id="formPotongan">
                <input type="hidden" id="potongan_id" name="potongan_id" value="0">
                <div class="form-group">
                    <label for="nama_potongan">Nama Potongan</label>
                    <input type="text" id="nama_potongan" name="nama_potongan" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="jenis_potongan">Jenis</label>
                    <select id="jenis_potongan" name="jenis_potongan" class="form-control" required>
                        <option value="fixed">Nominal Tetap</option>
                        <option value="percentage">Persentase</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="nilai_potongan">Nilai</label>
                    <input type="text" id="nilai_potongan" name="nilai_potongan" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="berlaku_potongan">Berlaku Untuk</label>
                    <select id="berlaku_potongan" name="berlaku_potongan" class="form-control" required>
                        <option value="semua">Semua Karyawan</option>
                        <option value="produksi">Produksi Saja</option>
                        <option value="admin">Admin Saja</option>
                    </select>
                </div>
                <button type="submit" name="save_potongan" class="btn btn-primary">💾 Simpan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalPotongan')">Batal</button>
            </form>
        </div>
    </div>
    
    <script>
        // Fungsi untuk switch tab
        function switchTab(tabName) {
            // Update URL tanpa reload halaman
            history.pushState(null, null, '?tab=' + tabName);
            
            // Update tab aktif
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            event.target.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }
        
        // Fungsi untuk format Rupiah
        function formatRupiahInput(input) {
            let value = input.value.replace(/[^0-9]/g, '');
            if (value) {
                input.value = new Intl.NumberFormat('id-ID').format(value);
            }
        }
        
        // Fungsi untuk parse Rupiah ke angka
        function parseRupiah(value) {
            return parseInt(value.replace(/[^0-9]/g, ''));
        }
        
        // Format input Rupiah saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            // Format input Rupiah
            const rupiahInputs = ['gaji_pokok_global', 'uang_makan', 'tunjangan_lain', 
                                 'sys_gaji_pokok', 'nilai_per_jam', 'nilai_potongan'];
            
            rupiahInputs.forEach(id => {
                const input = document.getElementById(id);
                if (input) {
                    // Format nilai awal
                    if (input.value) {
                        formatRupiahInput(input);
                    }
                    
                    // Format saat input berubah
                    input.addEventListener('keyup', function() {
                        formatRupiahInput(this);
                    });
                }
            });
        });
        
        // Fungsi untuk modal lembur
        function openLemburModal(id) {
            if (id > 0) {
                // Ambil data dari server (dalam contoh ini, kita gunakan data dari tabel)
                document.getElementById('modalLemburTitle').textContent = 'Edit Jenis Lembur';
                document.getElementById('lembur_id').value = id;
                
                // Cari data di tabel
                const table = document.querySelector('#tab-lembur .table tbody');
                const rows = table.querySelectorAll('tr');
                rows.forEach(row => {
                    const editBtn = row.querySelector('.btn-secondary');
                    if (editBtn && editBtn.onclick.toString().includes(id)) {
                        const cells = row.querySelectorAll('td');
                        if (cells.length >= 4) {
                            const nama = cells[1].textContent;
                            const nilai = parseRupiah(cells[2].textContent.replace('Rp ', ''));
                            const deskripsi = cells[3].textContent;
                            
                            document.getElementById('nama_lembur').value = nama;
                            document.getElementById('nilai_per_jam').value = nilai.toLocaleString('id-ID');
                            document.getElementById('deskripsi_lembur').value = deskripsi !== '-' ? deskripsi : '';
                        }
                    }
                });
            } else {
                document.getElementById('modalLemburTitle').textContent = 'Tambah Jenis Lembur';
                document.getElementById('lembur_id').value = 0;
                document.getElementById('formLembur').reset();
            }
            
            document.getElementById('modalLembur').classList.add('active');
        }
        
        // Fungsi untuk modal potongan
        function openPotonganModal(id) {
            if (id > 0) {
                document.getElementById('modalPotonganTitle').textContent = 'Edit Jenis Potongan';
                document.getElementById('potongan_id').value = id;
                
                // Cari data di tabel
                const table = document.querySelector('#tab-potongan .table tbody');
                const rows = table.querySelectorAll('tr');
                rows.forEach(row => {
                    const editBtn = row.querySelector('.btn-secondary');
                    if (editBtn && editBtn.onclick.toString().includes(id)) {
                        const cells = row.querySelectorAll('td');
                        if (cells.length >= 6) {
                            const nama = cells[1].textContent;
                            const jenis = cells[2].querySelector('span').textContent.toLowerCase().includes('tetap') ? 'fixed' : 'percentage';
                            const nilaiText = cells[3].textContent.trim();
                            const berlaku = cells[4].querySelector('span').textContent.toLowerCase();
                            
                            document.getElementById('nama_potongan').value = nama;
                            document.getElementById('jenis_potongan').value = jenis;
                            
                            if (jenis === 'fixed') {
                                const nilai = parseRupiah(nilaiText.replace('Rp ', ''));
                                document.getElementById('nilai_potongan').value = nilai.toLocaleString('id-ID');
                            } else {
                                const nilai = parseFloat(nilaiText.replace('%', ''));
                                document.getElementById('nilai_potongan').value = nilai.toFixed(2);
                            }
                            
                            document.getElementById('berlaku_potongan').value = berlaku;
                        }
                    }
                });
            } else {
                document.getElementById('modalPotonganTitle').textContent = 'Tambah Jenis Potongan';
                document.getElementById('potongan_id').value = 0;
                document.getElementById('formPotongan').reset();
            }
            
            document.getElementById('modalPotongan').classList.add('active');
        }
        
        // Fungsi untuk menutup modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Handle submit form di modal
        document.getElementById('formLembur').addEventListener('submit', function(e) {
            const input = document.getElementById('nilai_per_jam');
            if (input) {
                input.value = parseRupiah(input.value);
            }
        });
        
        document.getElementById('formPotongan').addEventListener('submit', function(e) {
            const input = document.getElementById('nilai_potongan');
            const jenis = document.getElementById('jenis_potongan').value;
            
            if (jenis === 'fixed') {
                input.value = parseRupiah(input.value);
            }
        });
        
        // Close modal dengan ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal('modalLembur');
                closeModal('modalPotongan');
            }
        });
        
        // Close modal dengan klik di luar
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });
    </script>
</body>
</html>