<?php
require_once 'config.php';
requireLogin();

$type = $_GET['type'] ?? 'csv';
$periode_id = intval($_GET['periode'] ?? 0);

$db = getDB();

// Query data
$query = "SELECT gk.*, k.nama as nama_karyawan, k.dept, p.nama_periode 
          FROM gaji_karyawan gk 
          JOIN karyawan k ON gk.karyawan_id = k.id 
          JOIN periode_gaji p ON gk.periode_id = p.id";

if ($periode_id > 0) {
    $query .= " WHERE gk.periode_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$periode_id]);
} else {
    $stmt = $db->query($query);
}

$data = $stmt->fetchAll();

if ($type == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=slip_gaji_' . date('Ymd') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['NIK', 'Nama', 'Dept', 'Periode', 'Gaji Pokok', 'Lembur', 'Potongan', 'BPJS', 'Gaji Bersih', 'Status']);
    
    foreach ($data as $row) {
        fputcsv($output, [
            str_pad($row['karyawan_id'], 5, '0', STR_PAD_LEFT),
            $row['nama_karyawan'],
            $row['dept'],
            $row['nama_periode'],
            $row['gaji_pokok'],
            $row['total_lembur'],
            $row['total_potongan'],
            $row['total_bpjs'],
            $row['gaji_bersih'],
            $row['status']
        ]);
    }
    
    fclose($output);
    exit();
}

// Untuk Excel (simple CSV dengan tab)
if ($type == 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename=slip_gaji_' . date('Ymd') . '.xls');
    
    echo "NIK\tNama\tDept\tPeriode\tGaji Pokok\tLembur\tPotongan\tBPJS\tGaji Bersih\tStatus\n";
    
    foreach ($data as $row) {
        echo str_pad($row['karyawan_id'], 5, '0', STR_PAD_LEFT) . "\t";
        echo $row['nama_karyawan'] . "\t";
        echo $row['dept'] . "\t";
        echo $row['nama_periode'] . "\t";
        echo $row['gaji_pokok'] . "\t";
        echo $row['total_lembur'] . "\t";
        echo $row['total_potongan'] . "\t";
        echo $row['total_bpjs'] . "\t";
        echo $row['gaji_bersih'] . "\t";
        echo $row['status'] . "\n";
    }
    exit();
}
?>