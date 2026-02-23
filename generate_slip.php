<?php
require_once 'config.php';
requireLogin();
require_once 'PDFService.php';

$db = getDB();
$pdfService = new PDFService();
$message = '';
$error = '';
$search_query = '';

// Proses pencarian jika ada
if (isset($_GET['search'])) {
    $search_query = $_GET['search'];
}

// Regenerate slip jika ada parameter
if (isset($_GET['regenerate'])) {
    $gaji_karyawan_id = intval($_GET['regenerate']);
    $result = $pdfService->generateSlipFromDatabase($gaji_karyawan_id);
    
    if ($result['success']) {
        $message = 'Slip berhasil digenerate ulang!';
    } else {
        $error = 'Gagal mengenerate slip: ' . ($result['message'] ?? 'Unknown error');
    }
}

// Hapus data slip gaji
if (isset($_GET['delete'])) {
    $gaji_id = intval($_GET['delete']);
    
    try {
        // Hapus data terkait terlebih dahulu
        $db->beginTransaction();
        
        // Hapus detail lembur
        $stmt = $db->prepare("DELETE FROM lembur_detail WHERE gaji_karyawan_id = ?");
        $stmt->execute([$gaji_id]);
        
        // Hapus detail potongan
        $stmt = $db->prepare("DELETE FROM potongan_detail WHERE gaji_karyawan_id = ?");
        $stmt->execute([$gaji_id]);
        
        // Hapus data produksi
        $stmt = $db->prepare("DELETE FROM produksi_data WHERE gaji_karyawan_id = ?");
        $stmt->execute([$gaji_id]);
        
        // Hapus notifikasi log
        $stmt = $db->prepare("DELETE FROM notifikasi_log WHERE gaji_karyawan_id = ?");
        $stmt->execute([$gaji_id]);
        
        // Hapus slip gaji
        $stmt = $db->prepare("DELETE FROM gaji_karyawan WHERE id = ?");
        $stmt->execute([$gaji_id]);
        
        $db->commit();
        
        $message = 'Data slip gaji berhasil dihapus!';
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Gagal menghapus data: ' . $e->getMessage();
    }
}

// Ambil data untuk form
$stmt = $db->query("SELECT * FROM karyawan ORDER BY nama");
$karyawan_list = $stmt->fetchAll();

$stmt = $db->query("SELECT * FROM periode_gaji WHERE status IN ('draft', 'final') ORDER BY periode_start DESC");
$periode_list = $stmt->fetchAll();

// Ambil jenis lembur dan potongan
$stmt = $db->query("SELECT * FROM lembur_jenis ORDER BY id");
$lembur_jenis = $stmt->fetchAll();

$stmt = $db->query("SELECT * FROM potongan_jenis ORDER BY id");
$potongan_jenis = $stmt->fetchAll();

// Ambil pengaturan gaji global
$stmt = $db->query("SELECT * FROM pengaturan_gaji LIMIT 1");
$pengaturan_gaji = $stmt->fetch();

$stmt = $db->query("SELECT * FROM pengaturan_sistem LIMIT 1");
$pengaturan_sistem = $stmt->fetch();

// Ambil gaji pokok global
$gaji_pokok_global = 0;
if ($pengaturan_gaji && !empty($pengaturan_gaji['gaji_pokok_global'])) {
    $gaji_pokok_global = $pengaturan_gaji['gaji_pokok_global'];
} elseif ($pengaturan_sistem && !empty($pengaturan_sistem['gaji_pokok_global'])) {
    $gaji_pokok_global = $pengaturan_sistem['gaji_pokok_global'];
}

// Format angka untuk display
function formatNumber($angka) {
    return number_format($angka, 0, ',', '.');
}

// Fungsi untuk menghitung total lembur berdasarkan detail lembur
function hitungTotalLembur($db, $gaji_karyawan_id) {
    $stmt = $db->prepare("SELECT SUM(ld.total) as total_lembur 
                         FROM lembur_detail ld 
                         JOIN lembur_jenis lj ON ld.jenis_lembur_id = lj.id 
                         WHERE ld.gaji_karyawan_id = ?");
    $stmt->execute([$gaji_karyawan_id]);
    $result = $stmt->fetch();
    return $result['total_lembur'] ?? 0;
}

// Fungsi untuk menghitung total potongan berdasarkan detail potongan
function hitungTotalPotongan($db, $gaji_karyawan_id) {
    $stmt = $db->prepare("SELECT SUM(pd.nominal) as total_potongan 
                         FROM potongan_detail pd 
                         JOIN potongan_jenis pj ON pd.jenis_potongan_id = pj.id 
                         WHERE pd.gaji_karyawan_id = ?");
    $stmt->execute([$gaji_karyawan_id]);
    $result = $stmt->fetch();
    return $result['total_potongan'] ?? 0;
}

// 1. GENERATE SLIP SINGLE KARYAWAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_single'])) {
    $karyawan_id = intval($_POST['karyawan_id']);
    $periode_id = intval($_POST['periode_id']);
    
    try {
        $db->beginTransaction();
        
        // Cek apakah sudah ada
        $stmt = $db->prepare("SELECT id FROM gaji_karyawan WHERE karyawan_id = ? AND periode_id = ?");
        $stmt->execute([$karyawan_id, $periode_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $gaji_karyawan_id = $existing['id'];
            $message = "Data slip sudah ada, melakukan update...";
        } else {
            $stmt = $db->prepare("INSERT INTO gaji_karyawan (karyawan_id, periode_id, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$karyawan_id, $periode_id]);
            $gaji_karyawan_id = $db->lastInsertId();
            $message = "Data slip baru dibuat...";
        }
        
        // Ambil data karyawan untuk cek department
        $stmt = $db->prepare("SELECT dept FROM karyawan WHERE id = ?");
        $stmt->execute([$karyawan_id]);
        $karyawan = $stmt->fetch();
        $is_produksi = ($karyawan['dept'] == 'PRODUKSI');
        
        // Hitung gaji pokok (bisa custom atau global)
        $gaji_pokok = floatval(str_replace(['.', ','], '', $_POST['gaji_pokok']));
        
        // Proses detail lembur jika ada
        $total_lembur = 0;
        if (isset($_POST['lembur'])) {
            // Hapus detail lembur lama
            $stmt = $db->prepare("DELETE FROM lembur_detail WHERE gaji_karyawan_id = ?");
            $stmt->execute([$gaji_karyawan_id]);
            
            // Simpan detail lembur baru
            foreach ($_POST['lembur'] as $jenis_lembur_id => $jam) {
                $jam = floatval(str_replace(',', '.', $jam));
                if ($jam > 0) {
                    // Ambil nilai per jam
                    $stmt = $db->prepare("SELECT nilai_per_jam FROM lembur_jenis WHERE id = ?");
                    $stmt->execute([$jenis_lembur_id]);
                    $lembur_data = $stmt->fetch();
                    
                    if ($lembur_data) {
                        $total_per_jenis = $jam * $lembur_data['nilai_per_jam'];
                        $total_lembur += $total_per_jenis;
                        
                        $stmt = $db->prepare("INSERT INTO lembur_detail (gaji_karyawan_id, jenis_lembur_id, jam, total) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$gaji_karyawan_id, $jenis_lembur_id, $jam, $total_per_jenis]);
                    }
                }
            }
        }
        
        // Proses detail potongan jika ada
        $total_potongan = 0;
        if (isset($_POST['potongan'])) {
            // Hapus detail potongan lama
            $stmt = $db->prepare("DELETE FROM potongan_detail WHERE gaji_karyawan_id = ?");
            $stmt->execute([$gaji_karyawan_id]);
            
            // Simpan detail potongan baru
            foreach ($_POST['potongan'] as $jenis_potongan_id => $nilai) {
                $nilai = floatval(str_replace(['.', ','], '', $nilai));
                if ($nilai > 0) {
                    // Cek apakah potongan berlaku untuk department ini
                    $stmt = $db->prepare("SELECT jenis, nilai, berlaku FROM potongan_jenis WHERE id = ?");
                    $stmt->execute([$jenis_potongan_id]);
                    $potongan_data = $stmt->fetch();
                    
                    $is_berlaku = true;
                    if ($potongan_data['berlaku'] != 'semua') {
                        if (($potongan_data['berlaku'] == 'produksi' && !$is_produksi) ||
                            ($potongan_data['berlaku'] == 'admin' && $is_produksi)) {
                            $is_berlaku = false;
                        }
                    }
                    
                    if ($is_berlaku) {
                        // Jika jenis persentase, hitung dari gaji pokok
                        $nominal_potongan = $nilai;
                        if ($potongan_data['jenis'] == 'percentage') {
                            $nominal_potongan = ($gaji_pokok * $nilai) / 100;
                        }
                        
                        $total_potongan += $nominal_potongan;
                        
                        $stmt = $db->prepare("INSERT INTO potongan_detail (gaji_karyawan_id, jenis_potongan_id, nominal) VALUES (?, ?, ?)");
                        $stmt->execute([$gaji_karyawan_id, $jenis_potongan_id, $nominal_potongan]);
                    }
                }
            }
        }
        
        // Hitung potongan BPJS otomatis (selalu dari gaji pokok)
        $bpjs_jht = $gaji_pokok * ($pengaturan_gaji['bpjs_jht_percent'] / 100);
        $bpjs_jkn = $gaji_pokok * ($pengaturan_gaji['bpjs_jkn_percent'] / 100);
        $total_bpjs = $bpjs_jht + $bpjs_jkn;
        
        // Tambah BPJS ke total potongan dan simpan sebagai potongan khusus
        $total_potongan += $total_bpjs;
        
        // Simpan BPJS ke tabel potongan_detail
        $stmt = $db->prepare("INSERT INTO potongan_detail (gaji_karyawan_id, jenis_potongan_id, nominal) VALUES (?, 3, ?)");
        $stmt->execute([$gaji_karyawan_id, $bpjs_jht]);
        
        $stmt = $db->prepare("INSERT INTO potongan_detail (gaji_karyawan_id, jenis_potongan_id, nominal) VALUES (?, 4, ?)");
        $stmt->execute([$gaji_karyawan_id, $bpjs_jkn]);
        
        // Hitung gaji bersih
        $gaji_bersih = $gaji_pokok + $total_lembur - $total_potongan;
        
        // Simpan data gaji utama
        $stmt = $db->prepare("UPDATE gaji_karyawan SET 
                             gaji_pokok = ?, 
                             total_lembur = ?, 
                             total_potongan = ?, 
                             total_bpjs = ?, 
                             gaji_bersih = ?,
                             status = 'generated'
                             WHERE id = ?");
        $stmt->execute([$gaji_pokok, $total_lembur, $total_potongan, $total_bpjs, $gaji_bersih, $gaji_karyawan_id]);
        
        $db->commit();
        
        // Generate slip
        $result = $pdfService->generateSlipFromDatabase($gaji_karyawan_id);
        
        if ($result['success']) {
            $message .= '<br>Slip gaji berhasil digenerate!';
        } else {
            $error = 'Gagal mengenerate slip: ' . ($result['message'] ?? 'Unknown error');
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Terjadi kesalahan: ' . $e->getMessage();
    }
}

// 2. GENERATE SEMUA KARYAWAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_all'])) {
    $periode_id = intval($_POST['periode_id_all']);
    $success_count = 0;
    $error_count = 0;
    
    foreach ($karyawan_list as $karyawan) {
        try {
            $karyawan_id = $karyawan['id'];
            $is_produksi = ($karyawan['dept'] == 'PRODUKSI');
            
            // Cek apakah sudah ada
            $stmt = $db->prepare("SELECT id FROM gaji_karyawan WHERE karyawan_id = ? AND periode_id = ?");
            $stmt->execute([$karyawan_id, $periode_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $gaji_karyawan_id = $existing['id'];
            } else {
                $stmt = $db->prepare("INSERT INTO gaji_karyawan (karyawan_id, periode_id, status) VALUES (?, ?, 'pending')");
                $stmt->execute([$karyawan_id, $periode_id]);
                $gaji_karyawan_id = $db->lastInsertId();
            }
            
            // Gunakan gaji pokok default dari pengaturan
            $gaji_pokok = $gaji_pokok_global;
            
            // Hitung potongan BPJS otomatis
            $bpjs_jht = $gaji_pokok * ($pengaturan_gaji['bpjs_jht_percent'] / 100);
            $bpjs_jkn = $gaji_pokok * ($pengaturan_gaji['bpjs_jkn_percent'] / 100);
            $total_bpjs = $bpjs_jht + $bpjs_jkn;
            
            // Hitung total potongan (hanya BPJS untuk generate semua)
            $total_potongan = $total_bpjs;
            
            // Hapus data lama jika ada
            $stmt = $db->prepare("DELETE FROM potongan_detail WHERE gaji_karyawan_id = ?");
            $stmt->execute([$gaji_karyawan_id]);
            
            // Simpan BPJS JHT
            $stmt = $db->prepare("INSERT INTO potongan_detail (gaji_karyawan_id, jenis_potongan_id, nominal) VALUES (?, 3, ?)");
            $stmt->execute([$gaji_karyawan_id, $bpjs_jht]);
            
            // Simpan BPJS JKN
            $stmt = $db->prepare("INSERT INTO potongan_detail (gaji_karyawan_id, jenis_potongan_id, nominal) VALUES (?, 4, ?)");
            $stmt->execute([$gaji_karyawan_id, $bpjs_jkn]);
            
            // Hitung gaji bersih
            $gaji_bersih = $gaji_pokok - $total_potongan;
            
            // Simpan data gaji
            $stmt = $db->prepare("UPDATE gaji_karyawan SET 
                                 gaji_pokok = ?, 
                                 total_lembur = 0, 
                                 total_potongan = ?, 
                                 total_bpjs = ?, 
                                 gaji_bersih = ?,
                                 status = 'generated'
                                 WHERE id = ?");
            if ($stmt->execute([$gaji_pokok, $total_potongan, $total_bpjs, $gaji_bersih, $gaji_karyawan_id])) {
                // Generate slip
                $result = $pdfService->generateSlipFromDatabase($gaji_karyawan_id);
                if ($result['success']) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } else {
                $error_count++;
            }
            
        } catch (Exception $e) {
            $error_count++;
            error_log("Error generate slip untuk karyawan {$karyawan['nama']}: " . $e->getMessage());
        }
    }
    
    if ($success_count > 0) {
        $message = "Berhasil mengenerate slip untuk {$success_count} karyawan!";
        if ($error_count > 0) {
            $message .= "<br>Gagal untuk {$error_count} karyawan.";
        }
    } else {
        $error = "Gagal mengenerate slip untuk semua karyawan!";
    }
}

// 3. UPDATE/EDIT GAJI KARYAWAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_gaji'])) {
    $gaji_id = intval($_POST['gaji_id']);
    
    try {
        $db->beginTransaction();
        
        // Ambil data gaji untuk mendapatkan karyawan_id
        $stmt = $db->prepare("SELECT karyawan_id FROM gaji_karyawan WHERE id = ?");
        $stmt->execute([$gaji_id]);
        $gaji_data = $stmt->fetch();
        
        if (!$gaji_data) {
            throw new Exception("Data gaji tidak ditemukan");
        }
        
        $karyawan_id = $gaji_data['karyawan_id'];
        
        // Ambil data karyawan untuk cek department
        $stmt = $db->prepare("SELECT dept FROM karyawan WHERE id = ?");
        $stmt->execute([$karyawan_id]);
        $karyawan = $stmt->fetch();
        $is_produksi = ($karyawan['dept'] == 'PRODUKSI');
        
        // Update data gaji pokok
        $gaji_pokok = floatval(str_replace(['.', ','], '', $_POST['edit_gaji_pokok']));
        
        // Proses detail lembur jika ada
        $total_lembur = 0;
        if (isset($_POST['edit_lembur'])) {
            // Hapus detail lembur lama
            $stmt = $db->prepare("DELETE FROM lembur_detail WHERE gaji_karyawan_id = ?");
            $stmt->execute([$gaji_id]);
            
            // Simpan detail lembur baru
            foreach ($_POST['edit_lembur'] as $jenis_lembur_id => $jam) {
                $jam = floatval(str_replace(',', '.', $jam));
                if ($jam > 0) {
                    // Ambil nilai per jam
                    $stmt = $db->prepare("SELECT nilai_per_jam FROM lembur_jenis WHERE id = ?");
                    $stmt->execute([$jenis_lembur_id]);
                    $lembur_data = $stmt->fetch();
                    
                    if ($lembur_data) {
                        $total_per_jenis = $jam * $lembur_data['nilai_per_jam'];
                        $total_lembur += $total_per_jenis;
                        
                        $stmt = $db->prepare("INSERT INTO lembur_detail (gaji_karyawan_id, jenis_lembur_id, jam, total) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$gaji_id, $jenis_lembur_id, $jam, $total_per_jenis]);
                    }
                }
            }
        }
        
        // Proses detail potongan jika ada
        $total_potongan = 0;
        if (isset($_POST['edit_potongan'])) {
            // Hapus detail potongan lama (kecuali BPJS)
            $stmt = $db->prepare("DELETE FROM potongan_detail WHERE gaji_karyawan_id = ? AND jenis_potongan_id NOT IN (3,4)");
            $stmt->execute([$gaji_id]);
            
            // Simpan detail potongan baru (kecuali BPJS)
            foreach ($_POST['edit_potongan'] as $jenis_potongan_id => $nilai) {
                if ($jenis_potongan_id != 3 && $jenis_potongan_id != 4) {
                    $nilai = floatval(str_replace(['.', ','], '', $nilai));
                    if ($nilai > 0) {
                        // Cek apakah potongan berlaku untuk department ini
                        $stmt = $db->prepare("SELECT jenis, nilai, berlaku FROM potongan_jenis WHERE id = ?");
                        $stmt->execute([$jenis_potongan_id]);
                        $potongan_data = $stmt->fetch();
                        
                        $is_berlaku = true;
                        if ($potongan_data['berlaku'] != 'semua') {
                            if (($potongan_data['berlaku'] == 'produksi' && !$is_produksi) ||
                                ($potongan_data['berlaku'] == 'admin' && $is_produksi)) {
                                $is_berlaku = false;
                            }
                        }
                        
                        if ($is_berlaku) {
                            // Jika jenis persentase, hitung dari gaji pokok
                            $nominal_potongan = $nilai;
                            if ($potongan_data['jenis'] == 'percentage') {
                                $nominal_potongan = ($gaji_pokok * $nilai) / 100;
                            }
                            
                            $total_potongan += $nominal_potongan;
                            
                            $stmt = $db->prepare("INSERT INTO potongan_detail (gaji_karyawan_id, jenis_potongan_id, nominal) VALUES (?, ?, ?)");
                            $stmt->execute([$gaji_id, $jenis_potongan_id, $nominal_potongan]);
                        }
                    }
                }
            }
        }
        
        // Hitung ulang BPJS berdasarkan gaji pokok baru
        $bpjs_jht = $gaji_pokok * ($pengaturan_gaji['bpjs_jht_percent'] / 100);
        $bpjs_jkn = $gaji_pokok * ($pengaturan_gaji['bpjs_jkn_percent'] / 100);
        $total_bpjs = $bpjs_jht + $bpjs_jkn;
        
        // Update BPJS di potongan_detail
        $stmt = $db->prepare("DELETE FROM potongan_detail WHERE gaji_karyawan_id = ? AND jenis_potongan_id IN (3,4)");
        $stmt->execute([$gaji_id]);
        
        $stmt = $db->prepare("INSERT INTO potongan_detail (gaji_karyawan_id, jenis_potongan_id, nominal) VALUES (?, 3, ?)");
        $stmt->execute([$gaji_id, $bpjs_jht]);
        
        $stmt = $db->prepare("INSERT INTO potongan_detail (gaji_karyawan_id, jenis_potongan_id, nominal) VALUES (?, 4, ?)");
        $stmt->execute([$gaji_id, $bpjs_jkn]);
        
        $total_potongan += $total_bpjs;
        
        // Hitung gaji bersih
        $gaji_bersih = $gaji_pokok + $total_lembur - $total_potongan;
        
        // Update data gaji utama
        $stmt = $db->prepare("UPDATE gaji_karyawan SET 
                             gaji_pokok = ?, 
                             total_lembur = ?, 
                             total_potongan = ?, 
                             total_bpjs = ?, 
                             gaji_bersih = ?,
                             status = 'generated'
                             WHERE id = ?");
        
        if ($stmt->execute([$gaji_pokok, $total_lembur, $total_potongan, $total_bpjs, $gaji_bersih, $gaji_id])) {
            $db->commit();
            
            // Regenerate slip setelah update
            $result = $pdfService->generateSlipFromDatabase($gaji_id);
            
            if ($result['success']) {
                $message = 'Data gaji berhasil diperbarui dan slip digenerate ulang!';
            } else {
                $message = 'Data gaji berhasil diperbarui tapi gagal generate slip!';
            }
        } else {
            throw new Exception("Gagal update data gaji");
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Gagal memperbarui data gaji: ' . $e->getMessage();
    }
}

// Ambil data slip gaji untuk ditampilkan (dengan filter pencarian)
$sql = "SELECT gk.*, k.nama as nama_karyawan, k.dept, k.no_hp, pg.nama_periode, 
               pg.periode_start, pg.periode_end 
        FROM gaji_karyawan gk 
        JOIN karyawan k ON gk.karyawan_id = k.id 
        JOIN periode_gaji pg ON gk.periode_id = pg.id 
        WHERE 1=1";

$params = [];
if (!empty($search_query)) {
    $sql .= " AND (k.nama LIKE ? OR k.dept LIKE ? OR pg.nama_periode LIKE ?)";
    $search_param = "%{$search_query}%";
    $params = [$search_param, $search_param, $search_param];
}

$sql .= " ORDER BY pg.periode_start DESC, k.nama ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$slip_list = $stmt->fetchAll();

// Untuk modal edit - ambil data gaji berdasarkan ID
$edit_data = null;
$edit_lembur_detail = [];
$edit_potongan_detail = [];

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    
    // Ambil data gaji utama
    $stmt = $db->prepare("SELECT gk.*, k.nama as nama_karyawan, k.dept 
                         FROM gaji_karyawan gk 
                         JOIN karyawan k ON gk.karyawan_id = k.id 
                         WHERE gk.id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch();
    
    if ($edit_data) {
        // Ambil detail lembur
        $stmt = $db->prepare("SELECT ld.jenis_lembur_id, ld.jam, ld.total, lj.nama as nama_lembur 
                             FROM lembur_detail ld 
                             JOIN lembur_jenis lj ON ld.jenis_lembur_id = lj.id 
                             WHERE ld.gaji_karyawan_id = ?");
        $stmt->execute([$edit_id]);
        $lembur_rows = $stmt->fetchAll();
        
        foreach ($lembur_rows as $row) {
            $edit_lembur_detail[$row['jenis_lembur_id']] = [
                'jam' => $row['jam'],
                'total' => $row['total'],
                'nama' => $row['nama_lembur']
            ];
        }
        
        // Ambil detail potongan
        $stmt = $db->prepare("SELECT pd.jenis_potongan_id, pd.nominal, pj.nama as nama_potongan, pj.jenis, pj.nilai, pj.berlaku 
                             FROM potongan_detail pd 
                             JOIN potongan_jenis pj ON pd.jenis_potongan_id = pj.id 
                             WHERE pd.gaji_karyawan_id = ?");
        $stmt->execute([$edit_id]);
        $potongan_rows = $stmt->fetchAll();
        
        foreach ($potongan_rows as $row) {
            $edit_potongan_detail[$row['jenis_potongan_id']] = [
                'nominal' => $row['nominal'],
                'nama' => $row['nama_potongan'],
                'jenis' => $row['jenis'],
                'nilai' => $row['nilai'],
                'berlaku' => $row['berlaku']
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Slip Gaji</title>
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
        .currency-input {
            text-align: right;
            font-family: monospace;
        }
        .table-actions {
            white-space: nowrap;
        }
        .info-badge {
            font-size: 0.8rem;
            padding: 2px 6px;
            margin-left: 5px;
        }
        .detail-section {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .detail-header {
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .detail-item {
            margin-bottom: 10px;
        }
        .modal-lg {
            max-width: 90%;
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
                            <a class="nav-link active" href="generate_slip.php">
                                <i class="bi bi-plus-circle"></i> Generate Slip
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="send_notif.php">
                                <i class="bi bi-whatsapp"></i> Kirim Notifikasi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="pengaturan.php">
                                <i class="bi bi-gear"></i> Pengaturan
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
                    <h2><i class="bi bi-plus-circle"></i> Generate Slip Gaji</h2>
                    <div>
                        <a href="data_slip.php" class="btn btn-outline-primary">
                            <i class="bi bi-list"></i> Lihat Semua Slip
                        </a>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#generateAllModal">
                            <i class="bi bi-lightning-charge"></i> Generate Semua
                        </button>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Informasi Gaji Global -->
                <div class="alert alert-info mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Gaji Pokok Global:</strong> 
                            <span class="badge bg-primary"><?php echo formatRupiah($gaji_pokok_global); ?></span>
                        </div>
                        <div class="col-md-4">
                            <strong>BPJS JHT:</strong> 
                            <span class="badge bg-info"><?php echo $pengaturan_gaji['bpjs_jht_percent']; ?>%</span>
                        </div>
                        <div class="col-md-4">
                            <strong>BPJS JKN:</strong> 
                            <span class="badge bg-info"><?php echo $pengaturan_gaji['bpjs_jkn_percent']; ?>%</span>
                        </div>
                    </div>
                </div>
                
                <!-- Form Generate Single -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Generate Slip Per Karyawan</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="generateForm">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Pilih Karyawan</label>
                                    <select name="karyawan_id" class="form-select" required 
                                            onchange="loadKaryawanData(this.value)">
                                        <option value="">-- Pilih Karyawan --</option>
                                        <?php foreach ($karyawan_list as $karyawan): ?>
                                            <option value="<?php echo $karyawan['id']; ?>">
                                                <?php echo htmlspecialchars($karyawan['nama']); ?> (<?php echo $karyawan['dept']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Pilih Periode</label>
                                    <select name="periode_id" class="form-select" required>
                                        <option value="">-- Pilih Periode --</option>
                                        <?php foreach ($periode_list as $periode): ?>
                                            <option value="<?php echo $periode['id']; ?>">
                                                <?php echo htmlspecialchars($periode['nama_periode']); ?> 
                                                (<?php echo date('d M Y', strtotime($periode['periode_start'])); ?> - 
                                                <?php echo date('d M Y', strtotime($periode['periode_end'])); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3" id="karyawanInfo" style="display: none;">
                                <div class="col-12">
                                    <div class="alert alert-secondary">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <strong>ID:</strong> <span id="nik_display"></span>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Department:</strong> <span id="dept_display"></span>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>No HP:</strong> <span id="nohp_display"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            <h5>Data Gaji</h5>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Gaji Pokok</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" name="gaji_pokok" class="form-control currency-input" 
                                               value="<?php echo formatNumber($gaji_pokok_global); ?>" 
                                               oninput="formatCurrencyMain(this)" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="useGlobalGaji()">
                                            <i class="bi bi-arrow-clockwise"></i> Reset Global
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section Detail Lembur -->
                            <?php if (!empty($lembur_jenis)): ?>
                            <div class="detail-section">
                                <h6 class="detail-header">
                                    <i class="bi bi-clock"></i> Detail Lembur
                                    <small class="text-muted">(Otomatis menghitung berdasarkan jam)</small>
                                </h6>
                                
                                <div class="row">
                                    <?php foreach ($lembur_jenis as $lembur): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="detail-item">
                                            <label class="form-label"><?php echo htmlspecialchars($lembur['nama']); ?></label>
                                            <div class="input-group">
                                                <input type="number" step="0.01" name="lembur[<?php echo $lembur['id']; ?>]" 
                                                       class="form-control lembur-jam" 
                                                       placeholder="Jam" 
                                                       oninput="hitungLembur(<?php echo $lembur['id']; ?>, <?php echo $lembur['nilai_per_jam']; ?>)">
                                                <span class="input-group-text">jam</span>
                                                <span class="input-group-text bg-light" style="min-width: 120px;">
                                                    Rp <span id="lembur_total_<?php echo $lembur['id']; ?>">0</span>
                                                </span>
                                            </div>
                                            <small class="text-muted">Rp <?php echo formatNumber($lembur['nilai_per_jam']); ?> per jam</small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="alert alert-warning">
                                            <strong>Total Lembur:</strong>
                                            <span class="float-end">Rp <span id="total_lembur_display">0</span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Section Detail Potongan -->
                            <?php if (!empty($potongan_jenis)): ?>
                            <div class="detail-section">
                                <h6 class="detail-header">
                                    <i class="bi bi-cash-coin"></i> Detail Potongan
                                    <small class="text-muted">(Selain BPJS yang sudah otomatis)</small>
                                </h6>
                                
                                <div class="row">
                                    <?php foreach ($potongan_jenis as $potongan): 
                                        if ($potongan['id'] == 3 || $potongan['id'] == 4) continue; // Skip BPJS karena sudah otomatis
                                    ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="detail-item">
                                            <label class="form-label">
                                                <?php echo htmlspecialchars($potongan['nama']); ?>
                                                <?php if ($potongan['berlaku'] != 'semua'): ?>
                                                    <span class="badge bg-info"><?php echo $potongan['berlaku']; ?></span>
                                                <?php endif; ?>
                                            </label>
                                            <div class="input-group">
                                                <?php if ($potongan['jenis'] == 'percentage'): ?>
                                                    <input type="number" step="0.01" name="potongan[<?php echo $potongan['id']; ?>]" 
                                                           class="form-control potongan-nilai" 
                                                           placeholder="%" 
                                                           oninput="hitungPotongan(<?php echo $potongan['id']; ?>, 'percentage')">
                                                    <span class="input-group-text">%</span>
                                                <?php else: ?>
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="text" name="potongan[<?php echo $potongan['id']; ?>]" 
                                                           class="form-control currency-input potongan-nilai" 
                                                           placeholder="Nominal" 
                                                           oninput="formatCurrencyMain(this); hitungPotongan(<?php echo $potongan['id']; ?>, 'fixed')">
                                                <?php endif; ?>
                                                <span class="input-group-text bg-light" style="min-width: 120px;">
                                                    Rp <span id="potongan_total_<?php echo $potongan['id']; ?>">0</span>
                                                </span>
                                            </div>
                                            <?php if ($potongan['jenis'] == 'fixed' && $potongan['nilai'] > 0): ?>
                                                <small class="text-muted">Default: Rp <?php echo formatNumber($potongan['nilai']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="alert alert-danger">
                                            <strong>Total Potongan (termasuk BPJS):</strong>
                                            <span class="float-end">Rp <span id="total_potongan_display">0</span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">BPJS (Otomatis)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" name="total_bpjs" class="form-control currency-input" 
                                               id="total_bpjs_preview" value="0" readonly>
                                    </div>
                                    <small class="text-muted">JHT: <?php echo $pengaturan_gaji['bpjs_jht_percent']; ?>% + JKN: <?php echo $pengaturan_gaji['bpjs_jkn_percent']; ?>% dari gaji pokok</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gaji Bersih (Otomatis)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" name="gaji_bersih" class="form-control currency-input" 
                                               id="gaji_bersih_preview" value="<?php echo formatNumber($gaji_pokok_global); ?>" readonly>
                                        <button type="button" class="btn btn-outline-success" onclick="hitungSemua()">
                                            <i class="bi bi-calculator-fill"></i> Hitung Ulang
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="generate_single" class="btn btn-primary btn-lg">
                                    <i class="bi bi-file-earmark-pdf"></i> Generate Slip Gaji
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabel Data Slip Gaji (dengan fitur edit) -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Data Slip Gaji yang Telah Digenerate</h5>
                        <form method="GET" class="d-flex" style="max-width: 300px;">
                            <input type="text" name="search" class="form-control me-2" 
                                   placeholder="Cari nama, departemen, periode..." 
                                   value="<?php echo htmlspecialchars($search_query); ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-search"></i>
                            </button>
                            <?php if (!empty($search_query)): ?>
                                <a href="generate_slip.php" class="btn btn-outline-secondary ms-2">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nama Karyawan</th>
                                        <th>Departemen</th>
                                        <th>Periode</th>
                                        <th>Gaji Pokok</th>
                                        <th>Lembur</th>
                                        <th>Potongan</th>
                                        <th>BPJS</th>
                                        <th>Gaji Bersih</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($slip_list)): ?>
                                        <tr>
                                            <td colspan="11" class="text-center">Belum ada data slip gaji</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($slip_list as $index => $slip): 
                                            // Hitung ulang detail untuk display yang akurat
                                            $total_lembur = hitungTotalLembur($db, $slip['id']);
                                            $total_potongan = hitungTotalPotongan($db, $slip['id']);
                                        ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($slip['nama_karyawan']); ?></strong>
                                                <?php if ($slip['gaji_pokok'] != $gaji_pokok_global): ?>
                                                    <span class="badge bg-warning text-dark info-badge" 
                                                          title="Gaji berbeda dari standar global">
                                                        <i class="bi bi-exclamation-triangle"></i> Custom
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $slip['dept']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($slip['nama_periode']); ?><br>
                                                <small class="text-muted">
                                                    <?php echo date('d M Y', strtotime($slip['periode_start'])); ?> - 
                                                    <?php echo date('d M Y', strtotime($slip['periode_end'])); ?>
                                                </small>
                                            </td>
                                            <td class="currency-input"><?php echo formatRupiah($slip['gaji_pokok']); ?></td>
                                            <td class="currency-input">
                                                <?php echo formatRupiah($total_lembur); ?>
                                                <?php if ($total_lembur > 0): ?>
                                                    <button class="btn btn-sm btn-outline-info" 
                                                            onclick="showDetail(<?php echo $slip['id']; ?>, 'lembur')">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td class="currency-input">
                                                <?php echo formatRupiah($total_potongan); ?>
                                                <?php if ($total_potongan > 0): ?>
                                                    <button class="btn btn-sm btn-outline-info" 
                                                            onclick="showDetail(<?php echo $slip['id']; ?>, 'potongan')">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td class="currency-input"><?php echo formatRupiah($slip['total_bpjs']); ?></td>
                                            <td class="currency-input">
                                                <strong><?php echo formatRupiah($slip['gaji_bersih']); ?></strong>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_class = [
                                                    'pending' => 'warning',
                                                    'generated' => 'info',
                                                    'sent' => 'success'
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $status_class[$slip['status']] ?? 'secondary'; ?>">
                                                    <?php echo ucfirst($slip['status']); ?>
                                                </span>
                                            </td>
                                            <td class="table-actions">
                                                <a href="generate_slip.php?edit=<?php echo $slip['id']; ?>" 
                                                   class="btn btn-sm btn-warning"
                                                   data-bs-toggle="modal" 
                                                   data-bs-target="#editModal"
                                                   onclick="event.preventDefault(); loadEditData(<?php echo $slip['id']; ?>)">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                                <?php if ($slip['pdf_file']): ?>
                                                <a href="slip_view.php?file=<?php echo urlencode($slip['pdf_file']); ?>" 
                                                   target="_blank" class="btn btn-sm btn-success">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                                <?php endif; ?>
                                                <a href="generate_slip.php?regenerate=<?php echo $slip['id']; ?>" 
                                                   class="btn btn-sm btn-secondary">
                                                    <i class="bi bi-arrow-clockwise"></i> Regenerate
                                                </a>
                                                <a href="generate_slip.php?delete=<?php echo $slip['id']; ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Yakin ingin menghapus slip gaji ini?')">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Generate All -->
    <div class="modal fade" id="generateAllModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generate Slip untuk Semua Karyawan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> 
                            <strong>Perhatian!</strong> Ini akan mengenerate slip untuk SEMUA karyawan yang aktif.
                            <ul class="mb-0 mt-2">
                                <li>Gaji pokok akan menggunakan nilai global: <strong><?php echo formatRupiah($gaji_pokok_global); ?></strong></li>
                                <li>Total lembur dan potongan default 0 (kecuali BPJS)</li>
                                <li>BPJS dihitung otomatis</li>
                                <li>Data yang sudah ada akan diupdate</li>
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Pilih Periode</label>
                            <select name="periode_id_all" class="form-select" required>
                                <option value="">-- Pilih Periode --</option>
                                <?php foreach ($periode_list as $periode): ?>
                                    <option value="<?php echo $periode['id']; ?>">
                                        <?php echo htmlspecialchars($periode['nama_periode']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Karyawan yang akan diproses (<?php echo count($karyawan_list); ?> orang):</label>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px; border-radius: 5px;">
                                <?php foreach ($karyawan_list as $karyawan): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               checked disabled>
                                        <label class="form-check-label">
                                            <?php echo htmlspecialchars($karyawan['nama']); ?> (<?php echo $karyawan['dept']; ?>)
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="generate_all" class="btn btn-success">
                            <i class="bi bi-lightning-charge"></i> Generate Semua
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit Gaji -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Data Gaji</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="editForm">
                    <input type="hidden" name="gaji_id" id="edit_gaji_id" value="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Karyawan</label>
                            <input type="text" class="form-control" id="edit_nama_karyawan" readonly>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Gaji Pokok</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" name="edit_gaji_pokok" id="edit_gaji_pokok" 
                                           class="form-control currency-input" oninput="formatCurrencyEdit(this)" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section Detail Lembur Edit -->
                        <?php if (!empty($lembur_jenis)): ?>
                        <div class="detail-section mb-3">
                            <h6 class="detail-header">
                                <i class="bi bi-clock"></i> Detail Lembur
                            </h6>
                            
                            <div class="row">
                                <?php foreach ($lembur_jenis as $lembur): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="detail-item">
                                        <label class="form-label"><?php echo htmlspecialchars($lembur['nama']); ?></label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" name="edit_lembur[<?php echo $lembur['id']; ?>]" 
                                                   class="form-control edit-lembur-jam" 
                                                   id="edit_lembur_<?php echo $lembur['id']; ?>"
                                                   placeholder="Jam" 
                                                   oninput="hitungEditLembur(<?php echo $lembur['id']; ?>, <?php echo $lembur['nilai_per_jam']; ?>)">
                                            <span class="input-group-text">jam</span>
                                            <span class="input-group-text bg-light" style="min-width: 120px;">
                                                Rp <span id="edit_lembur_total_<?php echo $lembur['id']; ?>">0</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="alert alert-warning">
                                        <strong>Total Lembur:</strong>
                                        <span class="float-end">Rp <span id="edit_total_lembur_display">0</span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Section Detail Potongan Edit -->
                        <?php if (!empty($potongan_jenis)): ?>
                        <div class="detail-section mb-3">
                            <h6 class="detail-header">
                                <i class="bi bi-cash-coin"></i> Detail Potongan
                            </h6>
                            
                            <div class="row">
                                <?php foreach ($potongan_jenis as $potongan): 
                                    if ($potongan['id'] == 3 || $potongan['id'] == 4) continue;
                                ?>
                                <div class="col-md-4 mb-2">
                                    <div class="detail-item">
                                        <label class="form-label">
                                            <?php echo htmlspecialchars($potongan['nama']); ?>
                                            <?php if ($potongan['berlaku'] != 'semua'): ?>
                                                <span class="badge bg-info"><?php echo $potongan['berlaku']; ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="input-group">
                                            <?php if ($potongan['jenis'] == 'percentage'): ?>
                                                <input type="number" step="0.01" name="edit_potongan[<?php echo $potongan['id']; ?>]" 
                                                       class="form-control edit-potongan-nilai" 
                                                       id="edit_potongan_<?php echo $potongan['id']; ?>"
                                                       placeholder="%" 
                                                       oninput="hitungEditPotongan(<?php echo $potongan['id']; ?>, 'percentage')">
                                                <span class="input-group-text">%</span>
                                            <?php else: ?>
                                                <span class="input-group-text">Rp</span>
                                                <input type="text" name="edit_potongan[<?php echo $potongan['id']; ?>]" 
                                                       class="form-control currency-input edit-potongan-nilai" 
                                                       id="edit_potongan_<?php echo $potongan['id']; ?>"
                                                       placeholder="Nominal" 
                                                       oninput="formatCurrencyEdit(this); hitungEditPotongan(<?php echo $potongan['id']; ?>, 'fixed')">
                                            <?php endif; ?>
                                            <span class="input-group-text bg-light" style="min-width: 120px;">
                                                Rp <span id="edit_potongan_total_<?php echo $potongan['id']; ?>">0</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="alert alert-danger">
                                        <strong>Total Potongan (termasuk BPJS):</strong>
                                        <span class="float-end">Rp <span id="edit_total_potongan_display">0</span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">BPJS (Otomatis)</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" id="edit_total_bpjs" class="form-control currency-input" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Gaji Bersih (Otomatis)</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" id="edit_gaji_bersih" class="form-control currency-input" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Setelah update, slip akan otomatis digenerate ulang
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_gaji" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Detail Lembur/Potongan -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailModalTitle">Detail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailModalContent">
                    Loading...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
  
   
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // ===== VARIABEL GLOBAL =====
    const gajiPokokGlobal = <?php echo $gaji_pokok_global; ?>;
    const bpjsJhtPercent = <?php echo $pengaturan_gaji['bpjs_jht_percent']; ?>;
    const bpjsJknPercent = <?php echo $pengaturan_gaji['bpjs_jkn_percent']; ?>;

    // ===== FUNGSI UTAMA =====
    
    // Format currency input untuk form utama
    function formatCurrencyMain(input) {
        let value = input.value.replace(/[^\d]/g, '');
        if (value === '') value = '0';
        
        const formatted = new Intl.NumberFormat('id-ID').format(parseInt(value));
        input.value = formatted;
        
        hitungSemua();
    }
    
    // Format currency input untuk form edit
    function formatCurrencyEdit(input) {
        let value = input.value.replace(/[^\d]/g, '');
        if (value === '') value = '0';
        
        const formatted = new Intl.NumberFormat('id-ID').format(parseInt(value));
        input.value = formatted;
        
        hitungEditSemua();
    }
    
    // Fungsi untuk load data karyawan
    function loadKaryawanData(karyawanId) {
        if (!karyawanId) {
            document.getElementById('karyawanInfo').style.display = 'none';
            return;
        }
        
        fetch('ajax_get_karyawan.php?id=' + karyawanId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('nik_display').textContent = karyawanId.toString().padStart(5, '0');
                    document.getElementById('dept_display').textContent = data.karyawan.dept;
                    document.getElementById('nohp_display').textContent = data.karyawan.no_hp || '-';
                    document.getElementById('karyawanInfo').style.display = 'block';
                }
            });
    }
    
    // Fungsi untuk reset ke gaji global
    function useGlobalGaji() {
        const gajiPokokInput = document.querySelector('input[name="gaji_pokok"]');
        if (gajiPokokInput) {
            gajiPokokInput.value = new Intl.NumberFormat('id-ID').format(gajiPokokGlobal);
            hitungSemua();
        }
    }
    
    // ===== FUNGSI UNTUK FORM UTAMA =====
    
    // Hitung lembur per jenis - form utama
    function hitungLembur(jenisId, nilaiPerJam) {
        const jamInput = document.querySelector(`input[name="lembur[${jenisId}]"]`);
        if (!jamInput) return;
        
        const jam = parseFloat(jamInput.value) || 0;
        const total = jam * nilaiPerJam;
        
        const totalElem = document.getElementById(`lembur_total_${jenisId}`);
        if (totalElem) {
            totalElem.textContent = new Intl.NumberFormat('id-ID').format(total);
        }
        
        hitungTotalLemburMain();
        hitungSemua();
    }
    
    // Hitung total lembur - form utama
    function hitungTotalLemburMain() {
        let total = 0;
        // Loop melalui semua jenis lembur dari PHP
        const lemburElements = document.querySelectorAll('[id^="lembur_total_"]');
        lemburElements.forEach(elem => {
            const value = parseFloat(elem.textContent.replace(/\./g, '')) || 0;
            total += value;
        });
        
        const displayElem = document.getElementById('total_lembur_display');
        if (displayElem) {
            displayElem.textContent = new Intl.NumberFormat('id-ID').format(total);
        }
        
        return total;
    }
    
    // Hitung potongan per jenis - form utama
    function hitungPotongan(jenisId, jenis) {
        const nilaiInput = document.querySelector(`input[name="potongan[${jenisId}]"]`);
        if (!nilaiInput) return;
        
        let nilai = 0;
        
        if (jenis === 'percentage') {
            nilai = parseFloat(nilaiInput.value) || 0;
        } else {
            nilai = parseFloat(nilaiInput.value.replace(/\./g, '')) || 0;
        }
        
        const totalElem = document.getElementById(`potongan_total_${jenisId}`);
        if (totalElem) {
            totalElem.textContent = new Intl.NumberFormat('id-ID').format(nilai);
        }
        
        hitungTotalPotonganMain();
        hitungSemua();
    }
    
    // Hitung total potongan - form utama
    function hitungTotalPotonganMain() {
        let total = 0;
        // Loop melalui semua jenis potongan dari PHP (kecuali BPJS)
        const potonganElements = document.querySelectorAll('[id^="potongan_total_"]');
        potonganElements.forEach(elem => {
            const value = parseFloat(elem.textContent.replace(/\./g, '')) || 0;
            total += value;
        });
        
        // Tambah BPJS
        const bpjsInput = document.getElementById('total_bpjs_preview');
        if (bpjsInput) {
            const bpjs = parseFloat(bpjsInput.value.replace(/\./g, '')) || 0;
            total += bpjs;
        }
        
        const displayElem = document.getElementById('total_potongan_display');
        if (displayElem) {
            displayElem.textContent = new Intl.NumberFormat('id-ID').format(total);
        }
        
        return total;
    }
    
    // Hitung BPJS berdasarkan gaji pokok - form utama
    function hitungBPJS(gajiPokok) {
        const bpjsJht = gajiPokok * (bpjsJhtPercent / 100);
        const bpjsJkn = gajiPokok * (bpjsJknPercent / 100);
        const totalBPJS = bpjsJht + bpjsJkn;
        
        const previewElem = document.getElementById('total_bpjs_preview');
        if (previewElem) {
            previewElem.value = new Intl.NumberFormat('id-ID').format(totalBPJS);
        }
        return totalBPJS;
    }
    
    // Hitung semua komponen gaji - form utama
    function hitungSemua() {
        const gajiPokokInput = document.querySelector('input[name="gaji_pokok"]');
        if (!gajiPokokInput) return;
        
        const gajiPokok = parseFloat(gajiPokokInput.value.replace(/\./g, '')) || 0;
        const totalLembur = hitungTotalLemburMain();
        const totalBPJS = hitungBPJS(gajiPokok);
        const totalPotongan = hitungTotalPotonganMain();
        
        const gajiBersih = gajiPokok + totalLembur - totalPotongan;
        const bersihElem = document.getElementById('gaji_bersih_preview');
        if (bersihElem) {
            bersihElem.value = new Intl.NumberFormat('id-ID').format(gajiBersih);
        }
    }
    
    // ===== FUNGSI UNTUK MODAL EDIT =====
    
    // Hitung lembur per jenis - modal edit
    function hitungEditLembur(jenisId, nilaiPerJam) {
        const jamInput = document.getElementById(`edit_lembur_${jenisId}`);
        if (!jamInput) return;
        
        const jam = parseFloat(jamInput.value) || 0;
        const total = jam * nilaiPerJam;
        
        const totalElem = document.getElementById(`edit_lembur_total_${jenisId}`);
        if (totalElem) {
            totalElem.textContent = new Intl.NumberFormat('id-ID').format(total);
        }
        
        hitungEditTotalLembur();
        hitungEditSemua();
    }
    
    // Hitung total lembur - modal edit
    function hitungEditTotalLembur() {
        let total = 0;
        const lemburElements = document.querySelectorAll('[id^="edit_lembur_total_"]');
        lemburElements.forEach(elem => {
            const value = parseFloat(elem.textContent.replace(/\./g, '')) || 0;
            total += value;
        });
        
        const displayElem = document.getElementById('edit_total_lembur_display');
        if (displayElem) {
            displayElem.textContent = new Intl.NumberFormat('id-ID').format(total);
        }
        
        return total;
    }
    
    // Hitung potongan per jenis - modal edit
    function hitungEditPotongan(jenisId, jenis) {
        const nilaiInput = document.getElementById(`edit_potongan_${jenisId}`);
        if (!nilaiInput) return;
        
        let nilai = 0;
        
        if (jenis === 'percentage') {
            nilai = parseFloat(nilaiInput.value) || 0;
        } else {
            nilai = parseFloat(nilaiInput.value.replace(/\./g, '')) || 0;
        }
        
        const totalElem = document.getElementById(`edit_potongan_total_${jenisId}`);
        if (totalElem) {
            totalElem.textContent = new Intl.NumberFormat('id-ID').format(nilai);
        }
        
        hitungEditTotalPotongan();
        hitungEditSemua();
    }
    
    // Hitung total potongan - modal edit
    function hitungEditTotalPotongan() {
        let total = 0;
        const potonganElements = document.querySelectorAll('[id^="edit_potongan_total_"]');
        potonganElements.forEach(elem => {
            const value = parseFloat(elem.textContent.replace(/\./g, '')) || 0;
            total += value;
        });
        
        // Tambah BPJS
        const bpjsInput = document.getElementById('edit_total_bpjs');
        if (bpjsInput) {
            const bpjs = parseFloat(bpjsInput.value.replace(/\./g, '')) || 0;
            total += bpjs;
        }
        
        const displayElem = document.getElementById('edit_total_potongan_display');
        if (displayElem) {
            displayElem.textContent = new Intl.NumberFormat('id-ID').format(total);
        }
        
        return total;
    }
    
    // Hitung BPJS - modal edit
    function hitungEditBPJS(gajiPokok) {
        const bpjsJht = gajiPokok * (bpjsJhtPercent / 100);
        const bpjsJkn = gajiPokok * (bpjsJknPercent / 100);
        const totalBPJS = bpjsJht + bpjsJkn;
        
        const bpjsElem = document.getElementById('edit_total_bpjs');
        if (bpjsElem) {
            bpjsElem.value = new Intl.NumberFormat('id-ID').format(totalBPJS);
        }
        return totalBPJS;
    }
    
    // Hitung semua - modal edit
    function hitungEditSemua() {
        const gajiPokokInput = document.getElementById('edit_gaji_pokok');
        if (!gajiPokokInput) return;
        
        const gajiPokok = parseFloat(gajiPokokInput.value.replace(/\./g, '')) || 0;
        const totalLembur = hitungEditTotalLembur();
        const totalBPJS = hitungEditBPJS(gajiPokok);
        const totalPotongan = hitungEditTotalPotongan();
        
        const gajiBersih = gajiPokok + totalLembur - totalPotongan;
        const bersihElem = document.getElementById('edit_gaji_bersih');
        if (bersihElem) {
            bersihElem.value = new Intl.NumberFormat('id-ID').format(gajiBersih);
        }
    }
    
    // Load data untuk modal edit
    function loadEditData(gajiId) {
        fetch('ajax_get_gaji.php?id=' + gajiId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_gaji_id').value = data.gaji.id;
                    document.getElementById('edit_nama_karyawan').value = data.gaji.nama_karyawan;
                    document.getElementById('edit_gaji_pokok').value = new Intl.NumberFormat('id-ID').format(data.gaji.gaji_pokok);
                    
                    // Isi detail lembur
                    if (data.lembur_detail) {
                        Object.keys(data.lembur_detail).forEach(jenisId => {
                            const input = document.getElementById(`edit_lembur_${jenisId}`);
                            if (input) {
                                input.value = data.lembur_detail[jenisId].jam;
                                const totalElem = document.getElementById(`edit_lembur_total_${jenisId}`);
                                if (totalElem) {
                                    totalElem.textContent = new Intl.NumberFormat('id-ID').format(data.lembur_detail[jenisId].total);
                                }
                            }
                        });
                    }
                    
                    // Isi detail potongan
                    if (data.potongan_detail) {
                        Object.keys(data.potongan_detail).forEach(jenisId => {
                            const input = document.getElementById(`edit_potongan_${jenisId}`);
                            if (input) {
                                const potongan = data.potongan_detail[jenisId];
                                if (potongan.jenis === 'percentage') {
                                    // Hitung persentase dari nominal
                                    const persentase = (potongan.nominal / data.gaji.gaji_pokok) * 100;
                                    input.value = persentase.toFixed(2);
                                } else {
                                    input.value = new Intl.NumberFormat('id-ID').format(potongan.nominal);
                                }
                                const totalElem = document.getElementById(`edit_potongan_total_${jenisId}`);
                                if (totalElem) {
                                    totalElem.textContent = new Intl.NumberFormat('id-ID').format(potongan.nominal);
                                }
                            }
                        });
                    }
                    
                    // Hitung ulang semua
                    hitungEditSemua();
                    
                    // Tampilkan modal
                    const editModal = new bootstrap.Modal(document.getElementById('editModal'));
                    editModal.show();
                }
            });
    }
    
    // Show detail lembur/potongan
    function showDetail(gajiId, type) {
        fetch('ajax_get_gaji_detail.php?id=' + gajiId + '&type=' + type)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('detailModalTitle').textContent = 
                        type === 'lembur' ? 'Detail Lembur' : 'Detail Potongan';
                    
                    let html = '<table class="table table-sm">';
                    html += '<thead><tr><th>Jenis</th><th>Jumlah</th><th>Total</th></tr></thead>';
                    html += '<tbody>';
                    
                    if (type === 'lembur') {
                        data.details.forEach(item => {
                            html += `<tr>
                                <td>${item.nama}</td>
                                <td>${item.jam} jam</td>
                                <td>Rp ${new Intl.NumberFormat('id-ID').format(item.total)}</td>
                            </tr>`;
                        });
                    } else {
                        data.details.forEach(item => {
                            html += `<tr>
                                <td>${item.nama}</td>
                                <td>${item.jenis === 'percentage' ? item.nilai + '%' : '-'}</td>
                                <td>Rp ${new Intl.NumberFormat('id-ID').format(item.nominal)}</td>
                            </tr>`;
                        });
                    }
                    
                    html += '</tbody></table>';
                    document.getElementById('detailModalContent').innerHTML = html;
                    
                    const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
                    detailModal.show();
                }
            });
    }
    
    // ===== INISIALISASI =====
    document.addEventListener('DOMContentLoaded', function() {
        // Hitung awal untuk form utama
        setTimeout(hitungSemua, 100);
        
        // Setup event listeners untuk form utama
        const gajiPokokInput = document.querySelector('input[name="gaji_pokok"]');
        if (gajiPokokInput) {
            gajiPokokInput.addEventListener('input', function() {
                setTimeout(hitungSemua, 100);
            });
        }
        
        // Setup untuk modal edit jika ada di URL
        <?php if (isset($_GET['edit']) && $edit_data): ?>
        setTimeout(function() {
            // Isi data langsung dari PHP
            document.getElementById('edit_gaji_id').value = <?php echo $edit_data['id']; ?>;
            document.getElementById('edit_nama_karyawan').value = '<?php echo htmlspecialchars($edit_data['nama_karyawan']); ?>';
            document.getElementById('edit_gaji_pokok').value = new Intl.NumberFormat('id-ID').format(<?php echo $edit_data['gaji_pokok']; ?>);
            
            // Isi detail lembur dari PHP
            <?php foreach ($edit_lembur_detail as $jenis_id => $detail): ?>
                const lemburInput = document.getElementById('edit_lembur_<?php echo $jenis_id; ?>');
                if (lemburInput) {
                    lemburInput.value = <?php echo $detail['jam']; ?>;
                    const totalElem = document.getElementById('edit_lembur_total_<?php echo $jenis_id; ?>');
                    if (totalElem) {
                        totalElem.textContent = new Intl.NumberFormat('id-ID').format(<?php echo $detail['total']; ?>);
                    }
                }
            <?php endforeach; ?>
            
            // Isi detail potongan dari PHP
            <?php foreach ($edit_potongan_detail as $jenis_id => $detail): 
                if ($jenis_id == 3 || $jenis_id == 4) continue;
            ?>
                const potonganInput = document.getElementById('edit_potongan_<?php echo $jenis_id; ?>');
                if (potonganInput) {
                    <?php if ($detail['jenis'] == 'percentage'): ?>
                        const persentase = (<?php echo $detail['nominal']; ?> / <?php echo $edit_data['gaji_pokok']; ?>) * 100;
                        potonganInput.value = persentase.toFixed(2);
                    <?php else: ?>
                        potonganInput.value = new Intl.NumberFormat('id-ID').format(<?php echo $detail['nominal']; ?>);
                    <?php endif; ?>
                    const totalElem = document.getElementById('edit_potongan_total_<?php echo $jenis_id; ?>');
                    if (totalElem) {
                        totalElem.textContent = new Intl.NumberFormat('id-ID').format(<?php echo $detail['nominal']; ?>);
                    }
                }
            <?php endforeach; ?>
            
            // Hitung semua
            hitungEditSemua();
            
            // Tampilkan modal
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }, 500);
        <?php endif; ?>
    });
    </script>

</body>
</html>