<?php

class PDFService {
    
    // Koneksi database
    private $db;
    
    public function __construct() {
        // Inisialisasi koneksi database
        try {
            $this->initDatabase();
        } catch (Exception $e) {
            error_log("PDFService Constructor Error: " . $e->getMessage());
        }
    }
    
    /**
     * Inisialisasi koneksi database
     */
    private function initDatabase() {
        try {
            // Gunakan koneksi dari config.php jika sudah ada
            if (function_exists('getDB')) {
                $this->db = getDB();
                return;
            }
            
            // Fallback jika tidak ada fungsi getDB
            $host = 'localhost';
            $dbname = 'sistem_penggajian';
            $username = 'root';
            $password = '';
            
            $this->db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Generate slip gaji berdasarkan ID gaji_karyawan
     */
    public function generateSlipFromDatabase($gaji_karyawan_id) {
        try {
            // Ambil data lengkap dari database
            $gaji = $this->getGajiKaryawan($gaji_karyawan_id);
            if (!$gaji) {
                throw new \Exception("Data gaji tidak ditemukan untuk ID: " . $gaji_karyawan_id);
            }
            
            $karyawan = $this->getKaryawan($gaji['karyawan_id']);
            if (!$karyawan) {
                throw new \Exception("Data karyawan tidak ditemukan untuk ID: " . $gaji['karyawan_id']);
            }
            
            $periode = $this->getPeriode($gaji['periode_id']);
            if (!$periode) {
                throw new \Exception("Data periode tidak ditemukan untuk ID: " . $gaji['periode_id']);
            }
            
            // Ambil data pendukung
            $lembur_details = $this->getLemburDetails($gaji_karyawan_id);
            $potongan_details = $this->getPotonganDetails($gaji_karyawan_id);
            $pengaturan_gaji = $this->getPengaturanGaji();
            $pengaturan_ttd = $this->getPengaturanTTD();
            
            // Ambil data dari tabel produksi_data jika ada untuk PRODUKSI
            $data_borongan = $this->getDataBorongan($gaji_karyawan_id);
            
            // Generate HTML berdasarkan departemen
            if ($karyawan['dept'] == 'PRODUKSI') {
                $html = $this->generateHTMLProduksiDesign(
                    $gaji, 
                    $karyawan, 
                    $periode, 
                    $lembur_details, 
                    $potongan_details,
                    $pengaturan_gaji,
                    $pengaturan_ttd,
                    $data_borongan
                );
            } else {
                $html = $this->generateHTMLAdminDesign(
                    $gaji, 
                    $karyawan, 
                    $periode, 
                    $lembur_details, 
                    $potongan_details,
                    $pengaturan_gaji,
                    $pengaturan_ttd
                );
            }
            
            $filename = 'slip_gaji_' . $karyawan['id'] . '_' . $periode['id'] . '_' . date('Ymd_His') . '.html';
            $filepath = $this->saveHTMLFile($html, $filename);
            
            // Update file PDF di database
            $this->updatePDFFile($gaji_karyawan_id, $filename);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'html' => $html
            ];
            
        } catch (\Exception $e) {
            error_log("PDF Generation Error: " . $e->getMessage());
            
            return $this->generateMinimalSlipFromData([
                'gaji' => $gaji ?? [],
                'karyawan' => $karyawan ?? [],
                'periode' => $periode ?? []
            ]);
        }
    }
    
    /**
     * Ambil data gaji_karyawan dari database
     */
    private function getGajiKaryawan($id) {
        $query = "SELECT gk.*, pg.periode_start, pg.periode_end 
                  FROM gaji_karyawan gk
                  JOIN periode_gaji pg ON gk.periode_id = pg.id
                  WHERE gk.id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Ambil data karyawan dari database
     */
    private function getKaryawan($id) {
        $query = "SELECT * FROM karyawan WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Ambil data periode dari database
     */
    private function getPeriode($id) {
        $query = "SELECT * FROM periode_gaji WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Ambil data lembur detail dari database
     */
    private function getLemburDetails($gaji_karyawan_id) {
        $query = "SELECT ld.*, lj.nama, lj.nilai_per_jam 
                  FROM lembur_detail ld 
                  JOIN lembur_jenis lj ON ld.jenis_lembur_id = lj.id 
                  WHERE ld.gaji_karyawan_id = :gaji_karyawan_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':gaji_karyawan_id' => $gaji_karyawan_id]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Ambil data potongan detail dari database
     */
    private function getPotonganDetails($gaji_karyawan_id) {
        $query = "SELECT pd.*, pj.nama, pj.jenis as potongan_jenis, pj.nilai, pj.berlaku 
                  FROM potongan_detail pd 
                  JOIN potongan_jenis pj ON pd.jenis_potongan_id = pj.id 
                  WHERE pd.gaji_karyawan_id = :gaji_karyawan_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':gaji_karyawan_id' => $gaji_karyawan_id]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Ambil data borongan dari database (jika ada tabel produksi_data)
     */
    private function getDataBorongan($gaji_karyawan_id) {
        $checkTable = "SHOW TABLES LIKE 'produksi_data'";
        $stmt = $this->db->query($checkTable);
        
        if ($stmt->rowCount() > 0) {
            $query = "SELECT * FROM produksi_data WHERE gaji_karyawan_id = :gaji_karyawan_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':gaji_karyawan_id' => $gaji_karyawan_id]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        return null;
    }
    
    /**
     * Ambil pengaturan gaji dari database
     */
    private function getPengaturanGaji() {
        $query = "SELECT * FROM pengaturan_gaji LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Ambil pengaturan tanda tangan dari database
     */
    private function getPengaturanTTD() {
        $query = "SELECT * FROM pengaturan_ttd LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Update nama file PDF di database
     */
    private function updatePDFFile($gaji_karyawan_id, $filename) {
        $query = "UPDATE gaji_karyawan SET pdf_file = :pdf_file, status = 'generated' WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':pdf_file' => $filename, ':id' => $gaji_karyawan_id]);
    }
    
    /**
     * Generate HTML dengan desain Excel-like untuk ADMIN
     */
    private function generateHTMLAdminDesign($gaji, $karyawan, $periode, $lembur_details, $potongan_details, $pengaturan_gaji, $ttd) {
        
        $formatRupiah = function($angka, $showErrorIfZero = false) {
            $angka = floatval($angka);
            if ($showErrorIfZero && $angka == 0) {
                return '<span class="error">#N/A</span>';
            }
            return 'Rp' . number_format($angka, 0, ',', '.') . ',00';
        };
        
        $formatRupiahNegative = function($angka) {
            $angka = floatval($angka);
            if ($angka == 0) {
                return 'Rp- ,00';
            }
            return '-Rp' . number_format($angka, 0, ',', '.') . ',00';
        };
        
        $formatDefault = function($angka) {
            $angka = floatval($angka);
            return $angka == 0 ? 'Rp- ,00' : 'Rp' . number_format($angka, 0, ',', '.') . ',00';
        };
        
        // GUNAKAN DATA DARI DATABASE, JANGAN HITUNG ULANG
        $gaji_pokok = floatval($gaji['gaji_pokok'] ?? 0);
        $total_lembur = floatval($gaji['total_lembur'] ?? 0);
        $total_potongan = floatval($gaji['total_potongan'] ?? 0);
        $total_bpjs = floatval($gaji['total_bpjs'] ?? 0);
        $gaji_bersih = floatval($gaji['gaji_bersih'] ?? 0);
        
        $periode_text = '';
        if ($periode) {
            $start = date('d F Y', strtotime($periode['periode_start'] ?? 'now'));
            $end = date('d F Y', strtotime($periode['periode_end'] ?? 'now'));
            $periode_text = "Slip Gaji $start - $end";
        }
        
        $nama_karyawan = htmlspecialchars($karyawan['nama'] ?? '');
        $nik_karyawan = str_pad($karyawan['id'] ?? '0', 5, '0', STR_PAD_LEFT);
        $dept_karyawan = $karyawan['dept'] ?? 'ADMIN';
        
        $nama_display = empty($nama_karyawan) ? '<span class="error">#N/A</span>' : $nama_karyawan;
        $nik_display = ($karyawan['id'] ?? 0) == 0 ? '<span class="error">#N/A</span>' : $nik_karyawan;
        
        $nama_ttd = htmlspecialchars($ttd['nama_direktur'] ?? 'Sumiati');
        $jabatan_ttd = htmlspecialchars($ttd['jabatan'] ?? 'DIREKTUR');
        $file_ttd = $ttd['file_ttd'] ?? 'ttd_direktur.png';
        
        $ttd_image_html = $this->getTTDImageHTML($file_ttd);
        
        // HITUNG DATA BERDASARKAN DETAIL DARI DATABASE
        // Total lembur dari detail lembur
        $total_lembur_calculated = 0;
        $lembur_summary = [];
        foreach ($lembur_details as $lembur) {
            $lembur_total = floatval($lembur['total'] ?? 0);
            $lembur_name = $lembur['nama'] ?? 'Lembur';
            $total_lembur_calculated += $lembur_total;
            $lembur_summary[] = [
                'nama' => $lembur_name,
                'total' => $lembur_total
            ];
        }
        
        // Jika total_lembur dari gaji_karyawan berbeda dengan perhitungan detail,
        // gunakan yang dari gaji_karyawan (data master)
        if ($total_lembur > 0 && $total_lembur != $total_lembur_calculated) {
            // Adjust lembur summary jika perlu
            if (!empty($lembur_summary)) {
                $lembur_summary[0]['total'] += ($total_lembur - $total_lembur_calculated);
            }
        }
        
        // Total potongan dari detail potongan
        $total_potongan_calculated = 0;
        $potongan_ijin = 0;
        $potongan_dt = 0;
        $bpjs_jht = 0;
        $bpjs_jkn = 0;
        $koreksi = 0;
        $lain_lain = 0;
        
        foreach ($potongan_details as $potongan) {
            $nama_potongan = strtolower($potongan['nama'] ?? '');
            $nominal = floatval($potongan['nominal'] ?? 0);
            $total_potongan_calculated += $nominal;
            
            if (strpos($nama_potongan, 'ijin') !== false || strpos($nama_potongan, 'alpa') !== false || 
                strpos($nama_potongan, 'alpha') !== false) {
                $potongan_ijin += $nominal;
            } elseif (strpos($nama_potongan, 'dt') !== false || strpos($nama_potongan, 'pc') !== false) {
                $potongan_dt += $nominal;
            } elseif (strpos($nama_potongan, 'bpjs jht') !== false || strpos($nama_potongan, 'jht') !== false || 
                     strpos($nama_potongan, 'jht jp') !== false) {
                $bpjs_jht += $nominal;
            } elseif (strpos($nama_potongan, 'bpjs jkn') !== false || strpos($nama_potongan, 'jkn') !== false) {
                $bpjs_jkn += $nominal;
            } elseif (strpos($nama_potongan, 'koreksi') !== false) {
                $koreksi += $nominal;
            } elseif (strpos($nama_potongan, 'lain') !== false || strpos($nama_potongan, 'other') !== false) {
                $lain_lain += $nominal;
            }
        }
        
        // Jika total_potongan dari gaji_karyawan berbeda dengan perhitungan detail,
        // distribusikan selisihnya ke potongan lain-lain
        if ($total_potongan > 0 && $total_potongan != $total_potongan_calculated) {
            $selisih = $total_potongan - $total_potongan_calculated;
            $lain_lain += $selisih;
        }
        
        // Total BPJS dari database
        $total_bpjs_calculated = $bpjs_jht + $bpjs_jkn;
        if ($total_bpjs > 0 && $total_bpjs != $total_bpjs_calculated) {
            // Jika ada selisih, distribusikan ke BPJS JHT (3%) dan JKN (1%)
            $bpjs_jht = ($total_bpjs * 0.75); // 3% dari total 4%
            $bpjs_jkn = ($total_bpjs * 0.25); // 1% dari total 4%
        }
        
        // Total upah (gaji pokok + lembur)
        $total_upah = $gaji_pokok + $total_lembur;
        
        // Gaji bersih dari database
        $gaji_bersih_display = $gaji_bersih > 0 ? $gaji_bersih : ($total_upah - $total_potongan);
        
        // Generate lembur rows
        $lembur_rows = '';
        $lembur_types = [
            'Biasa 1' => 'Biasa 1',
            'Biasa 2' => 'Biasa 2', 
            'Libur 1' => 'Libur 1',
            'Libur 2' => 'Libur 2',
            'Libur 3' => 'Libur 3'
        ];
        
        foreach ($lembur_types as $type_key => $type_name) {
            $found = false;
            $lembur_total = 0;
            
            foreach ($lembur_summary as $lembur) {
                if (strpos(strtolower($lembur['nama']), strtolower($type_key)) !== false) {
                    $lembur_total = floatval($lembur['total'] ?? 0);
                    $found = true;
                    break;
                }
            }
            
            // Jika tidak ditemukan, coba cari berdasarkan nama yang mirip
            if (!$found && !empty($lembur_summary)) {
                foreach ($lembur_summary as $lembur) {
                    if (strpos(strtolower($type_key), strtolower($lembur['nama'])) !== false) {
                        $lembur_total = floatval($lembur['total'] ?? 0);
                        $found = true;
                        break;
                    }
                }
            }
            
            $lembur_display = $found ? $formatDefault($lembur_total) : 'Rp- ,00';
            
            $lembur_rows .= '
                <div class="table-row">
                    <div class="cell col-desc">' . $type_name . '</div>
                    <div class="cell col-total">' . $lembur_display . '</div>
                </div>';
        }
        
        // Format nilai untuk display
        $gaji_pokok_formatted = $gaji_pokok == 0 ? 'Rp- ,00' : $formatRupiah($gaji_pokok);
        $total_upah_formatted = $total_upah == 0 ? 'Rp- ,00' : $formatRupiah($total_upah);
        $potongan_ijin_formatted = $potongan_ijin == 0 ? 'Rp- ,00' : $formatDefault($potongan_ijin);
        $potongan_dt_formatted = $potongan_dt == 0 ? 'Rp- ,00' : $formatDefault($potongan_dt);
        $bpjs_jht_formatted = $bpjs_jht == 0 ? 'Rp- ,00' : $formatRupiahNegative($bpjs_jht);
        $bpjs_jkn_formatted = $bpjs_jkn == 0 ? 'Rp- ,00' : $formatRupiahNegative($bpjs_jkn);
        $koreksi_formatted = $koreksi == 0 ? 'Rp- ,00' : $formatDefault($koreksi);
        $lain_lain_formatted = $lain_lain == 0 ? 'Rp- ,00' : $formatDefault($lain_lain);
        
        $total_potongan_formatted = $total_potongan == 0 ? 'Rp- ,00' : $formatDefault($total_potongan);
        $gaji_bersih_formatted = $gaji_bersih_display == 0 ? '<span class="error">#N/A</span>' : $formatRupiah($gaji_bersih_display);
        
        // Responsive CSS additions
        $responsive_css = '
        @media screen and (max-width: 900px) {
            .excel-container {
                width: 95% !important;
                margin: 10px auto;
            }
            
            .tables-wrapper {
                flex-direction: column;
            }
            
            .table-spacer {
                display: none;
            }
            
            .col-desc, .col-total, .col-qty {
                font-size: 11px !important;
            }
            
            .cell {
                padding: 0 6px !important;
                font-size: 11px !important;
            }
            
            .label {
                width: 120px !important;
            }
            
            .value {
                width: calc(100% - 120px) !important;
            }
            
            .signature-block {
                right: 60px !important;
                width: 250px !important;
            }
        }
        
        @media screen and (max-width: 600px) {
            .excel-container {
                width: 100% !important;
                border: none !important;
            }
            
            .cell {
                padding: 0 4px !important;
                font-size: 10px !important;
            }
            
            .label {
                width: 100px !important;
                font-size: 10px !important;
            }
            
            .value {
                width: calc(100% - 100px) !important;
                font-size: 10px !important;
            }
            
            .col-total {
                width: 120px !important;
            }
            
            .signature-block {
                right: 20px !important;
                width: 200px !important;
            }
            
            .sig-space {
                width: 180px !important;
            }
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .excel-container {
                width: 100% !important;
                box-shadow: none !important;
                border: 1px solid #000 !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            @page {
                margin: 0.5cm;
                size: A4 portrait;
            }
        }';
        
        // HTML untuk ADMIN
        $html = '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLIP GAJI KARYAWAN - PT. SEKAR PUTRA DAERAH</title>
    <style>
        :root {
            --excel-navy: #002060;
            --excel-blue-bg: #dce6f1;
            --excel-border: #969696;
            --excel-text: #000;
            --excel-error: #c00000;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            background-color: #e6e6e6;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .excel-container {
            width: 850px;
            background-color: #fff;
            border: 2px solid #000;
            position: relative;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .header {
            height: 180px;
            overflow: hidden;
            border-bottom: 3px solid var(--excel-navy);
        }

        .logo-full-container {
            width: 100%;
            height: 100%;
        }

        .logo-full {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .info-section {
            padding: 0;
            border-bottom: 1px solid #000;
        }

        .row {
            display: flex;
            height: 32px;
            border-bottom: 1px solid #d4d4d4;
        }

        .row:last-child {
            border-bottom: none;
        }

        .cell {
            border: none;
            padding: 0 12px;
            display: flex;
            align-items: center;
            font-size: 14px;
            min-width: 80px;
            font-family: Arial, sans-serif;
        }

        .label {
            width: 140px;
            font-weight: bold;
            border-right: 1px solid #000;
            background-color: #f0f0f0;
        }

        .value {
            width: 320px;
            border-right: 1px solid #d4d4d4;
        }

        .wide {
            width: 380px;
        }

        .error {
            color: var(--excel-error);
            font-weight: bold;
            font-style: italic;
            font-family: Courier New, monospace;
        }

        .empty-row {
            height: 20px;
            background-color: #f5f5f5;
        }

        .tables-wrapper {
            display: flex;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            min-height: 320px;
        }

        .table-column {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .table-spacer {
            width: 40px;
            background-color: #fff;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            display: flex;
            flex-direction: column;
        }

        .spacer-cell {
            height: 28px;
            border-bottom: 1px solid #d4d4d4;
            flex: 1;
        }

        .spacer-cell:last-child {
            border-bottom: none;
        }

        .table-row {
            display: flex;
            height: 28px;
            min-height: 28px;
            border-bottom: 1px solid #d4d4d4;
        }

        .table-row:last-child {
            border-bottom: none;
        }

        .table-row.head {
            background-color: var(--excel-navy);
            color: white;
            font-weight: bold;
            height: 30px;
            font-size: 13px;
        }

        .table-row.head.alt {
            background-color: #1a3a7a;
        }

        .table-row.subhead {
            background-color: var(--excel-navy);
            color: white;
            justify-content: center;
            font-weight: bold;
            font-size: 13px;
        }

        .table-row.total-row {
            background-color: var(--excel-navy);
            color: white;
            font-weight: bold;
            margin-top: auto;
            font-size: 13px;
        }

        .bg-blue {
            background-color: var(--excel-blue-bg);
        }

        .col-desc {
            flex: 1;
            padding-left: 12px;
            border-right: 1px solid #000;
            display: flex;
            align-items: center;
            font-size: 13px;
        }

        .col-total {
            width: 160px;
            justify-content: flex-end;
            padding-right: 12px;
            display: flex;
            align-items: center;
            font-family: Courier New, monospace;
            font-weight: bold;
            font-size: 13px;
            border-left: 1px solid #000;
        }

        .col-qty {
            width: 80px;
            justify-content: center;
            border-right: 1px solid #000;
            display: flex;
            align-items: center;
            font-size: 13px;
        }

        .full-width {
            width: 100%;
            justify-content: center;
            padding: 0;
        }

        .net-total-row {
            display: flex;
            height: 40px;
            background-color: var(--excel-blue-bg);
            border-bottom: 2px solid #000;
        }

        .label-net {
            flex: 1;
            padding-left: 20px;
            font-weight: bold;
            border-right: 1px solid #000;
            display: flex;
            align-items: center;
            font-size: 15px;
            font-family: Arial, sans-serif;
        }

        .value-net {
            width: 200px;
            justify-content: flex-end;
            padding-right: 20px;
            display: flex;
            align-items: center;
            font-family: Courier New, monospace;
            font-weight: bold;
            font-size: 15px;
            color: var(--excel-error);
        }

        .footer-container {
            height: 240px;
            position: relative;
            background-color: #fff;
            border-top: 1px solid #000;
            padding: 0;
            margin: 0;
        }

        .grid-overlay {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(to right, #d4d4d4 0.5px, transparent 0.5px),
                linear-gradient(to bottom, #d4d4d4 0.5px, transparent 0.5px);
            background-size: 80px 22px;
            opacity: 0.5;
            z-index: 1;
        }

        .triangles {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 300px;
            height: 200px;
            z-index: 2;
        }

        .triangle-dark {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--excel-navy);
            clip-path: polygon(0 20%, 0 100%, 85% 100%);
        }

        .triangle-light {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 90%;
            height: 70%;
            background: #2b4c7e;
            clip-path: polygon(0 0, 0 100%, 100% 100%);
            opacity: 0.8;
        }

        /* SIGNATURE SECTION YANG DISESUAIKAN DENGAN CONTOH */
        .signature-block {
            position: absolute;
            right: 120px;
            top: 30px;
            text-align: center;
            z-index: 10;
            width: 280px;
        }

        .sig-date {
            font-weight: bold;
            font-size: 14px;
            color: #000;
            font-family: Arial, sans-serif;
            margin-bottom: 5px;
            text-align: center;
            width: 100%;
        }

        .sig-company {
            font-weight: bold;
            font-size: 16px;
            color: #002060;
            font-family: Arial, sans-serif;
            margin-bottom: 20px;
            text-align: center;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.2;
        }

        .sig-space {
            height: 100px;
            margin: 0 auto;
            width: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .sig-space img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
            height: auto;
            width: auto;
        }

        .sig-name {
            font-weight: bold;
            font-size: 18px;
            font-style: italic;
            font-family: "Times New Roman", serif;
            color: #000;
            text-align: center;
            margin-top: 8px;
            text-decoration: underline;
            text-underline-offset: 2px;
            width: 100%;
            line-height: 1.2;
        }

        .sig-title {
            font-weight: bold;
            font-size: 12px;
            color: #000;
            text-transform: uppercase;
            font-family: Arial, sans-serif;
            text-align: center;
            margin-top: 2px;
            letter-spacing: 0.5px;
            width: 100%;
            line-height: 1.2;
        }
        
        ' . $responsive_css . '
    </style>
</head>
<body>
    <div class="excel-container">
        <div class="header">
            <div class="logo-full-container">
                <img src="https://blogger.googleusercontent.com/img/a/AVvXsEjJafTYCWbzVzl5eK5LQh1Yg3aHqeV2BwvtqPeO58UxFAcc4M3l0kdwtjPkIjS1yq8Io9Vd1rL1enRYwSk_TEojxLWYwuAUCwZ_Z3u3SDycE6FXzIie-7fA8N7_JP-qo7a_qKG9Htv4AfKCQZqQ8Dd6-lG-wBGqHol8PEIcet6AaqDbfVBFJxCdUiTUmAQ" 
                     alt="Logo PT Sekar Putra Daerah" 
                     class="logo-full">
            </div>
        </div>

        <div class="info-section">
            <div class="row">
                <div class="cell label">Periode Gaji</div>
                <div class="cell value wide" style="font-weight: bold;">' . $periode_text . '</div>
            </div>
            <div class="row">
                <div class="cell label">Nama</div>
                <div class="cell value">' . $nama_display . '</div>
            </div>
            <div class="row">
                <div class="cell label">NIK</div>
                <div class="cell value">' . $nik_display . '</div>
            </div>
            <div class="row">
                <div class="cell label">Dept</div>
                <div class="cell value">' . htmlspecialchars($dept_karyawan) . '</div>
            </div>
            <div class="row empty-row"></div>
        </div>

        <div class="tables-wrapper">
            <div class="table-column">
                <div class="table-row head">
                    <div class="cell col-desc">Penghasilan (+)</div>
                    <div class="cell col-total">Total</div>
                </div>
                <div class="table-row">
                    <div class="cell col-desc">Gaji Pokok</div>
                    <div class="cell col-total">' . $gaji_pokok_formatted . '</div>
                </div>
                <div class="table-row subhead">
                    <div class="cell full-width">Lembur</div>
                </div>
                ' . $lembur_rows . '
                <div class="table-row total-row">
                    <div class="cell col-desc">Grand Total Upah</div>
                    <div class="cell col-total">' . $total_upah_formatted . '</div>
                </div>
            </div>

            <div class="table-spacer">
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
            </div>

            <div class="table-column">
                <div class="table-row head">
                    <div class="cell col-desc">Potongan (-)</div>
                    <div class="cell col-total">Total</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">Potongan Ijin/Alpha</div>
                    <div class="cell col-total">' . $potongan_ijin_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">Potongan DT/PC</div>
                    <div class="cell col-total">' . $potongan_dt_formatted . '</div>
                </div>
                <div class="table-row head alt">
                    <div class="cell col-desc">Potongan (-)</div>
                    <div class="cell col-qty">Jumlah</div>
                    <div class="cell col-total">TOTAL</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">BPJS (JHT, JP)</div>
                    <div class="cell col-qty">3%</div>
                    <div class="cell col-total">' . $bpjs_jht_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">BPJS JKN</div>
                    <div class="cell col-qty">1%</div>
                    <div class="cell col-total">' . $bpjs_jkn_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">Koreksi</div>
                    <div class="cell col-qty"></div>
                    <div class="cell col-total">' . $koreksi_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">Lain-lain</div>
                    <div class="cell col-qty"></div>
                    <div class="cell col-total">' . $lain_lain_formatted . '</div>
                </div>
                <div class="table-row total-row">
                    <div class="cell col-desc">Grand Total Potongan</div>
                    <div class="cell col-total">' . $total_potongan_formatted . '</div>
                </div>
            </div>
        </div>

        <div class="net-total-row">
            <div class="cell label-net">Upah Bersih Diterima</div>
            <div class="cell value-net">' . $gaji_bersih_formatted . '</div>
        </div>

        <div class="footer-container">
            <div class="grid-overlay"></div>
            <div class="triangles">
                <div class="triangle-dark"></div>
                <div class="triangle-light"></div>
            </div>
            
            <div class="signature-block">
                <div class="sig-date">Gresik, ' . date('d F Y') . '</div>
                <div class="sig-company">PT. SEKAR PUTRA DAERAH</div>
                <div class="sig-space">' . $ttd_image_html . '</div>
                <div class="sig-name">' . $nama_ttd . '</div>
                <div class="sig-title">' . $jabatan_ttd . '</div>
            </div>
        </div>
    </div>

    <!-- Print and Save Controls -->
    <div class="no-print" style="position: fixed; bottom: 20px; right: 20px; z-index: 1000;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #002060; color: white; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;">
            🖨️ Cetak
        </button>
        <button onclick="saveAsPDF()" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">
            💾 Save as PDF
        </button>
    </div>

    <script>
        // Print Function
        document.addEventListener("keydown", function(e) {
            if (e.ctrlKey && e.key === "p") {
                e.preventDefault();
                window.print();
            }
        });
        
        // Save as PDF Function (using html2pdf library)
        function saveAsPDF() {
            const element = document.querySelector(".excel-container");
            const opt = {
                margin:       0.5,
                filename:     "slip_gaji_' . $nama_karyawan . '_' . date('Y-m-d') . '.pdf",
                image:        { type: "jpeg", quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: "in", format: "a4", orientation: "portrait" }
            };
            
            // Load html2pdf library if not already loaded
            if (typeof html2pdf !== "undefined") {
                html2pdf().set(opt).from(element).save();
            } else {
                // Dynamically load html2pdf library
                const script = document.createElement("script");
                script.src = "https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js";
                script.onload = function() {
                    html2pdf().set(opt).from(element).save();
                };
                document.head.appendChild(script);
                
                // Fallback to print if library fails to load
                setTimeout(function() {
                    if (typeof html2pdf === "undefined") {
                        alert("Gagal memuat library PDF. Menggunakan fitur print sebagai alternatif.");
                        window.print();
                    }
                }, 5000);
            }
        }
        
        // Responsive adjustments
        window.addEventListener("resize", function() {
            const container = document.querySelector(".excel-container");
            if (window.innerWidth < 900) {
                container.style.width = "95%";
            } else {
                container.style.width = "850px";
            }
        });
    </script>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Generate HTML dengan desain Excel-like untuk PRODUKSI
     */
    private function generateHTMLProduksiDesign($gaji, $karyawan, $periode, $lembur_details, $potongan_details, $pengaturan_gaji, $ttd, $data_borongan = null) {
        
        $formatRupiah = function($angka, $showErrorIfZero = false) {
            $angka = floatval($angka);
            if ($showErrorIfZero && $angka == 0) {
                return '<span class="error">#N/A</span>';
            }
            return 'Rp' . number_format($angka, 0, ',', '.') . ',00';
        };
        
        $formatRupiahNegative = function($angka) {
            $angka = floatval($angka);
            if ($angka == 0) {
                return 'Rp- ,00';
            }
            return '-Rp' . number_format($angka, 0, ',', '.') . ',00';
        };
        
        $formatDefault = function($angka) {
            $angka = floatval($angka);
            return $angka == 0 ? 'Rp- ,00' : 'Rp' . number_format($angka, 0, ',', '.') . ',00';
        };
        
        $formatJumlah = function($angka) {
            $angka = floatval($angka);
            return $angka == 0 ? '-' : number_format($angka, 0, ',', '.');
        };
        
        // GUNAKAN DATA DARI DATABASE, JANGAN HITUNG ULANG
        $gaji_pokok = floatval($gaji['gaji_pokok'] ?? 0);
        $total_lembur = floatval($gaji['total_lembur'] ?? 0);
        $total_potongan = floatval($gaji['total_potongan'] ?? 0);
        $total_bpjs = floatval($gaji['total_bpjs'] ?? 0);
        $gaji_bersih = floatval($gaji['gaji_bersih'] ?? 0);
        
        $periode_text = '';
        if ($periode) {
            $start = date('d F Y', strtotime($periode['periode_start'] ?? 'now'));
            $end = date('d F Y', strtotime($periode['periode_end'] ?? 'now'));
            $periode_text = "Slip Gaji $start - $end";
        }
        
        $nama_karyawan = htmlspecialchars($karyawan['nama'] ?? '');
        $nik_karyawan = str_pad($karyawan['id'] ?? '0', 5, '0', STR_PAD_LEFT);
        $dept_karyawan = $karyawan['dept'] ?? 'PRODUKSI';
        
        $nama_display = empty($nama_karyawan) ? '<span class="error">#N/A</span>' : $nama_karyawan;
        $nik_display = ($karyawan['id'] ?? 0) == 0 ? '<span class="error">#N/A</span>' : $nik_karyawan;
        
        $nama_ttd = htmlspecialchars($ttd['nama_direktur'] ?? 'Sumiati');
        $jabatan_ttd = htmlspecialchars($ttd['jabatan'] ?? 'DIREKTUR');
        $file_ttd = $ttd['file_ttd'] ?? 'ttd_direktur.png';
        
        $ttd_image_html = $this->getTTDImageHTML($file_ttd);
        
        // HITUNG DATA BERDASARKAN DATABASE
        // Total lembur dari detail
        $total_lembur_calculated = 0;
        foreach ($lembur_details as $lembur) {
            $total_lembur_calculated += floatval($lembur['total'] ?? 0);
        }
        
        // Gunakan data dari gaji_karyawan jika tersedia
        if ($total_lembur > 0) {
            $final_total_lembur = $total_lembur;
        } else {
            $final_total_lembur = $total_lembur_calculated;
        }
        
        // Data borongan
        $total_upah_borongan_per_bulan = $gaji_pokok;
        
        // Jika ada data borongan khusus, gunakan itu
        if ($data_borongan) {
            $jumlah_borongan_2026 = floatval($data_borongan['jumlah_2026'] ?? 0);
            $upah_borongan_perjam_2026 = floatval($data_borongan['upah_perjam_2026'] ?? 0);
            $upah_borongan_2026 = $jumlah_borongan_2026 * $upah_borongan_perjam_2026;
            $total_upah_borongan_2026 = $upah_borongan_2026;
            
            $jumlah_borongan_2025 = floatval($data_borongan['jumlah_2025'] ?? 0);
            $upah_borongan_perjam_2025 = floatval($data_borongan['upah_perjam_2025'] ?? 0);
            $upah_borongan_2025 = $jumlah_borongan_2025 * $upah_borongan_perjam_2025;
            $total_upah_borongan_2025 = $upah_borongan_2025;
            
            // Jika gaji pokok 0, gunakan total borongan
            if ($gaji_pokok == 0) {
                $total_upah_borongan_per_bulan = $total_upah_borongan_2026 + $total_upah_borongan_2025;
            }
        } else {
            // Jika tidak ada data borongan, gunakan gaji pokok
            $jumlah_borongan_2026 = 0;
            $upah_borongan_perjam_2026 = 0;
            $upah_borongan_2026 = 0;
            $total_upah_borongan_2026 = 0;
            
            $jumlah_borongan_2025 = 0;
            $upah_borongan_perjam_2025 = 0;
            $upah_borongan_2025 = 0;
            $total_upah_borongan_2025 = 0;
        }
        
        // Hitung potongan dari detail
        $potongan_dt_borongan_2026 = 0;
        $potongan_pc_borongan_2026 = 0;
        $potongan_dt_borongan_2025 = 0;
        $potongan_pc_borongan_2025 = 0;
        
        $bpjs_jht = 0;
        $bpjs_jkn = 0;
        $potongan_pph_21_2026 = 0;
        $potongan_pph_21_2025 = 0;
        $potongan_management_fee = 0;
        $koreksi = 0;
        $potongan_lain_lain = 0;
        
        $total_potongan_calculated = 0;
        foreach ($potongan_details as $potongan) {
            $nama_potongan = strtolower($potongan['nama'] ?? '');
            $nominal = floatval($potongan['nominal'] ?? 0);
            $total_potongan_calculated += $nominal;
            
            if (strpos($nama_potongan, 'bpjs jht') !== false || strpos($nama_potongan, 'jht') !== false || 
                strpos($nama_potongan, 'jht jp') !== false) {
                $bpjs_jht += $nominal;
            } elseif (strpos($nama_potongan, 'bpjs jkn') !== false || strpos($nama_potongan, 'jkn') !== false) {
                $bpjs_jkn += $nominal;
            } elseif (strpos($nama_potongan, 'pph 21 2026') !== false || 
                     (strpos($nama_potongan, 'pph') !== false && strpos($nama_potongan, '2026') !== false)) {
                $potongan_pph_21_2026 += $nominal;
            } elseif (strpos($nama_potongan, 'pph 21 2025') !== false ||
                     (strpos($nama_potongan, 'pph') !== false && strpos($nama_potongan, '2025') !== false)) {
                $potongan_pph_21_2025 += $nominal;
            } elseif (strpos($nama_potongan, 'management') !== false || strpos($nama_potongan, 'fee') !== false ||
                     strpos($nama_potongan, 'management fee') !== false) {
                $potongan_management_fee += $nominal;
            } elseif (strpos($nama_potongan, 'dt 2026') !== false || 
                     (strpos($nama_potongan, 'dt') !== false && strpos($nama_potongan, '2026') !== false)) {
                $potongan_dt_borongan_2026 += $nominal;
            } elseif (strpos($nama_potongan, 'pc 2026') !== false ||
                     (strpos($nama_potongan, 'pc') !== false && strpos($nama_potongan, '2026') !== false)) {
                $potongan_pc_borongan_2026 += $nominal;
            } elseif (strpos($nama_potongan, 'dt 2025') !== false ||
                     (strpos($nama_potongan, 'dt') !== false && strpos($nama_potongan, '2025') !== false)) {
                $potongan_dt_borongan_2025 += $nominal;
            } elseif (strpos($nama_potongan, 'pc 2025') !== false ||
                     (strpos($nama_potongan, 'pc') !== false && strpos($nama_potongan, '2025') !== false)) {
                $potongan_pc_borongan_2025 += $nominal;
            } elseif (strpos($nama_potongan, 'koreksi') !== false) {
                $koreksi += $nominal;
            } elseif (strpos($nama_potongan, 'lain') !== false || strpos($nama_potongan, 'other') !== false) {
                $potongan_lain_lain += $nominal;
            }
        }
        
        // Gunakan data dari gaji_karyawan untuk total potongan
        $final_total_potongan = $total_potongan > 0 ? $total_potongan : $total_potongan_calculated;
        
        // Gunakan data dari gaji_karyawan untuk total BPJS
        if ($total_bpjs > 0) {
            // Distribusikan total_bpjs sesuai persentase default jika detail tidak sesuai
            if ($bpjs_jht + $bpjs_jkn != $total_bpjs) {
                $bpjs_jht = ($total_bpjs * 0.75); // 3% dari total 4%
                $bpjs_jkn = ($total_bpjs * 0.25); // 1% dari total 4%
            }
        }
        
        // Hitung management fee jika belum ada
        if ($potongan_management_fee == 0 && $total_upah_borongan_per_bulan > 0) {
            $potongan_management_fee = ($total_upah_borongan_per_bulan * 5) / 100;
        }
        
        // Hitung iuran perusahaan (contoh perhitungan)
        $bpjs_tk = $bpjs_jht + ($total_upah_borongan_per_bulan * 0.24) / 100;
        $bpjs_ks = $bpjs_jkn;
        $total_iuran_perusahaan = $bpjs_tk + $bpjs_ks;
        
        // Grand total potongan
        $grand_total_potongan = $final_total_potongan;
        
        // Upah bersih dari database
        $upah_bersih = $gaji_bersih > 0 ? $gaji_bersih : ($total_upah_borongan_per_bulan - $grand_total_potongan);
        
        // Format nilai untuk ditampilkan
        $upah_borongan_2026_formatted = $formatDefault($upah_borongan_2026);
        $total_upah_borongan_2026_formatted = $formatDefault($total_upah_borongan_2026);
        $upah_borongan_2025_formatted = $formatDefault($upah_borongan_2025);
        $total_upah_borongan_2025_formatted = $formatDefault($total_upah_borongan_2025);
        $total_upah_borongan_per_bulan_formatted = $formatDefault($total_upah_borongan_per_bulan);
        $upah_borongan_perjam_2026_formatted = $formatDefault($upah_borongan_perjam_2026);
        $upah_borongan_perjam_2025_formatted = $formatDefault($upah_borongan_perjam_2025);
        
        $bpjs_tk_formatted = $formatDefault($bpjs_tk);
        $bpjs_ks_formatted = $formatDefault($bpjs_ks);
        $total_iuran_formatted = $formatDefault($total_iuran_perusahaan);
        $koreksi_gaji_formatted = $formatDefault($koreksi);
        $grand_total_upah_formatted = $formatDefault($total_upah_borongan_per_bulan);
        
        $potongan_dt_2026_formatted = $formatDefault($potongan_dt_borongan_2026);
        $potongan_pc_2026_formatted = $formatDefault($potongan_pc_borongan_2026);
        $potongan_dt_2025_formatted = $formatDefault($potongan_dt_borongan_2025);
        $potongan_pc_2025_formatted = $formatDefault($potongan_pc_borongan_2025);
        $potongan_pph_21_2026_formatted = $formatDefault($potongan_pph_21_2026);
        $potongan_pph_21_2025_formatted = $formatDefault($potongan_pph_21_2025);
        $bpjs_jht_formatted = $bpjs_jht == 0 ? 'Rp- ,00' : $formatRupiahNegative($bpjs_jht);
        $bpjs_jkn_formatted = $bpjs_jkn == 0 ? 'Rp- ,00' : $formatRupiahNegative($bpjs_jkn);
        $potongan_management_formatted = $potongan_management_fee == 0 ? 'Rp- ,00' : $formatRupiahNegative($potongan_management_fee);
        $koreksi_formatted = $koreksi == 0 ? 'Rp- ,00' : $formatDefault($koreksi);
        $potongan_lain_formatted = $potongan_lain_lain == 0 ? 'Rp- ,00' : $formatDefault($potongan_lain_lain);
        $grand_total_potongan_formatted = $grand_total_potongan == 0 ? 'Rp- ,00' : $formatDefault($grand_total_potongan);
        
        $upah_bersih_formatted = $upah_bersih == 0 ? '<span class="error">#N/A</span>' : $formatDefault($upah_bersih);
        
        // Responsive CSS untuk PRODUKSI
        $responsive_css = '
        @media screen and (max-width: 1000px) {
            .excel-container {
                width: 95% !important;
                margin: 10px auto;
            }
            
            .tables-wrapper {
                flex-direction: column;
            }
            
            .table-spacer {
                display: none;
            }
            
            .col-desc, .col-jumlah, .col-upah, .col-total {
                font-size: 9px !important;
            }
            
            .cell {
                padding: 0 4px !important;
                font-size: 9px !important;
            }
            
            .label {
                width: 110px !important;
            }
            
            .value {
                width: calc(100% - 110px) !important;
            }
            
            .signature-block {
                right: 40px !important;
                width: 220px !important;
            }
        }
        
        @media screen and (max-width: 700px) {
            .excel-container {
                width: 100% !important;
                border: none !important;
            }
            
            .cell {
                padding: 0 2px !important;
                font-size: 8px !important;
            }
            
            .label {
                width: 90px !important;
                font-size: 8px !important;
            }
            
            .value {
                width: calc(100% - 90px) !important;
                font-size: 8px !important;
            }
            
            .col-total {
                width: 90px !important;
            }
            
            .col-jumlah, .col-upah {
                width: 50px !important;
            }
            
            .signature-block {
                right: 10px !important;
                width: 180px !important;
                top: 15px !important;
            }
            
            .sig-space {
                width: 150px !important;
                height: 80px !important;
            }
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .excel-container {
                width: 100% !important;
                box-shadow: none !important;
                border: 1px solid #000 !important;
                font-size: 10px !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            @page {
                margin: 0.5cm;
                size: A4 landscape;
            }
            
            .col-desc, .col-jumlah, .col-upah, .col-total {
                font-size: 8px !important;
            }
        }';
        
        // HTML untuk PRODUKSI
        $html = '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLIP GAJI PRODUKSI - PT. SEKAR PUTRA DAERAH</title>
    <style>
        :root {
            --excel-navy: #002060;
            --excel-blue-bg: #dce6f1;
            --excel-border: #969696;
            --excel-text: #000;
            --excel-error: #c00000;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            background-color: #e6e6e6;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .excel-container {
            width: 920px;
            background-color: #fff;
            border: 2px solid #000;
            position: relative;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .header {
            height: 180px;
            overflow: hidden;
            border-bottom: 3px solid var(--excel-navy);
        }

        .logo-full-container {
            width: 100%;
            height: 100%;
        }

        .logo-full {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .info-section {
            padding: 0;
            border-bottom: 1px solid #000;
        }

        .row {
            display: flex;
            height: 32px;
            border-bottom: 1px solid #d4d4d4;
        }

        .row:last-child {
            border-bottom: none;
        }

        .cell {
            border: none;
            padding: 0 8px;
            display: flex;
            align-items: center;
            font-size: 12px;
            min-width: 60px;
            font-family: Arial, sans-serif;
        }

        .label {
            width: 130px;
            font-weight: bold;
            border-right: 1px solid #000;
            background-color: #f0f0f0;
            font-size: 11px;
        }

        .value {
            width: 350px;
            border-right: 1px solid #d4d4d4;
            font-size: 11px;
        }

        .wide {
            width: 420px;
        }

        .error {
            color: var(--excel-error);
            font-weight: bold;
            font-style: italic;
            font-family: Courier New, monospace;
            font-size: 10px;
        }

        .empty-row {
            height: 15px;
            background-color: #f5f5f5;
        }

        .tables-wrapper {
            display: flex;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            min-height: 380px;
        }

        .table-column {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .table-spacer {
            width: 10px;
            background-color: #fff;
            border-left: 1px solid #d4d4d4;
            border-right: 1px solid #d4d4d4;
            display: flex;
            flex-direction: column;
        }

        .spacer-cell {
            height: 24px;
            border-bottom: 1px solid #d4d4d4;
            flex: 1;
        }

        .spacer-cell:last-child {
            border-bottom: none;
        }

        .table-row {
            display: flex;
            height: 24px;
            min-height: 24px;
            border-bottom: 1px solid #d4d4d4;
        }

        .table-row:last-child {
            border-bottom: none;
        }

        .table-row.head {
            background-color: var(--excel-navy);
            color: white;
            font-weight: bold;
            height: 26px;
            font-size: 10px;
        }

        .table-row.subhead {
            background-color: var(--excel-navy);
            color: white;
            justify-content: center;
            font-weight: bold;
            font-size: 10px;
        }

        .table-row.total-row {
            background-color: var(--excel-navy);
            color: white;
            font-weight: bold;
            margin-top: auto;
            font-size: 10px;
        }

        .bg-blue {
            background-color: var(--excel-blue-bg);
        }

        .col-desc {
            flex: 1.5;
            padding-left: 8px;
            border-right: 1px solid #d4d4d4;
            display: flex;
            align-items: center;
            font-size: 9px;
            line-height: 1.2;
        }

        .col-jumlah {
            width: 60px;
            justify-content: center;
            border-right: 1px solid #d4d4d4;
            display: flex;
            align-items: center;
            font-size: 9px;
            font-family: Courier New, monospace;
        }

        .col-upah {
            width: 90px;
            justify-content: flex-end;
            padding-right: 8px;
            border-right: 1px solid #d4d4d4;
            display: flex;
            align-items: center;
            font-size: 9px;
            font-family: Courier New, monospace;
            font-weight: bold;
        }

        .col-total {
            width: 100px;
            justify-content: flex-end;
            padding-right: 8px;
            display: flex;
            align-items: center;
            font-family: Courier New, monospace;
            font-weight: bold;
            font-size: 9px;
            border-left: 1px solid #d4d4d4;
        }

        .col-qty {
            width: 45px;
            justify-content: center;
            border-right: 1px solid #d4d4d4;
            display: flex;
            align-items: center;
            font-size: 9px;
        }

        .full-width {
            width: 100%;
            justify-content: center;
            padding: 0;
        }

        .net-total-row {
            display: flex;
            height: 35px;
            background-color: var(--excel-blue-bg);
            border-bottom: 2px solid #000;
        }

        .label-net {
            flex: 1;
            padding-left: 15px;
            font-weight: bold;
            border-right: 1px solid #000;
            display: flex;
            align-items: center;
            font-size: 12px;
            font-family: Arial, sans-serif;
        }

        .value-net {
            width: 180px;
            justify-content: flex-end;
            padding-right: 15px;
            display: flex;
            align-items: center;
            font-family: Courier New, monospace;
            font-weight: bold;
            font-size: 12px;
            color: var(--excel-error);
        }

        .footer-container {
            height: 240px;
            position: relative;
            background-color: #fff;
            border-top: 1px solid #000;
            padding: 0;
            margin: 0;
        }

        .grid-overlay {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(to right, #d4d4d4 0.5px, transparent 0.5px),
                linear-gradient(to bottom, #d4d4d4 0.5px, transparent 0.5px);
            background-size: 80px 22px;
            opacity: 0.5;
            z-index: 1;
        }

        .triangles {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 300px;
            height: 200px;
            z-index: 2;
        }

        .triangle-dark {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--excel-navy);
            clip-path: polygon(0 20%, 0 100%, 85% 100%);
        }

        .triangle-light {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 90%;
            height: 70%;
            background: #2b4c7e;
            clip-path: polygon(0 0, 0 100%, 100% 100%);
            opacity: 0.8;
        }

        /* SIGNATURE SECTION */
        .signature-block {
            position: absolute;
            right: 120px;
            top: 30px;
            text-align: center;
            z-index: 10;
            width: 280px;
        }

        .sig-date {
            font-weight: bold;
            font-size: 14px;
            color: #000;
            font-family: Arial, sans-serif;
            margin-bottom: 5px;
            text-align: center;
            width: 100%;
        }

        .sig-company {
            font-weight: bold;
            font-size: 16px;
            color: #002060;
            font-family: Arial, sans-serif;
            margin-bottom: 20px;
            text-align: center;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.2;
        }

        .sig-space {
            height: 100px;
            margin: 0 auto;
            width: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .sig-space img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
            height: auto;
            width: auto;
        }

        .sig-name {
            font-weight: bold;
            font-size: 18px;
            font-style: italic;
            font-family: "Times New Roman", serif;
            color: #000;
            text-align: center;
            margin-top: 8px;
            text-decoration: underline;
            text-underline-offset: 2px;
            width: 100%;
            line-height: 1.2;
        }

        .sig-title {
            font-weight: bold;
            font-size: 12px;
            color: #000;
            text-transform: uppercase;
            font-family: Arial, sans-serif;
            text-align: center;
            margin-top: 2px;
            letter-spacing: 0.5px;
            width: 100%;
            line-height: 1.2;
        }
        
        ' . $responsive_css . '
    </style>
</head>
<body>
    <div class="excel-container">
        <div class="header">
            <div class="logo-full-container">
                <img src="https://blogger.googleusercontent.com/img/a/AVvXsEjJafTYCWbzVzl5eK5LQh1Yg3aHqeV2BwvtqPeO58UxFAcc4M3l0kdwtjPkIjS1yq8Io9Vd1rL1enRYwSk_TEojxLWYwuAUCwZ_Z3u3SDycE6FXzIie-7fA8N7_JP-qo7a_qKG9Htv4AfKCQZqQ8Dd6-lG-wBGqHol8PEIcet6AaqDbfVBFJxCdUiTUmAQ" 
                     alt="Logo PT Sekar Putra Daerah" 
                     class="logo-full">
            </div>
        </div>

        <div class="info-section">
            <div class="row">
                <div class="cell label">Periode Gaji</div>
                <div class="cell value wide" style="font-weight: bold;">' . $periode_text . '</div>
            </div>
            <div class="row">
                <div class="cell label">Nama</div>
                <div class="cell value">' . $nama_display . '</div>
            </div>
            <div class="row">
                <div class="cell label">NIK</div>
                <div class="cell value">' . $nik_display . '</div>
            </div>
            <div class="row">
                <div class="cell label">Dept</div>
                <div class="cell value">' . htmlspecialchars($dept_karyawan) . '</div>
            </div>
            <div class="row empty-row"></div>
        </div>

        <div class="tables-wrapper">
            <div class="table-column">
                <div class="table-row head">
                    <div class="cell col-desc">Penghasilan (+)</div>
                    <div class="cell col-jumlah">Jumlah</div>
                    <div class="cell col-upah">Upah Perjam</div>
                    <div class="cell col-total">TOTAL</div>
                </div>
                
                <div class="table-row bg-blue">
                    <div class="cell col-desc">Jml Borongan 2026</div>
                    <div class="cell col-jumlah">' . $formatJumlah($jumlah_borongan_2026) . '</div>
                    <div class="cell col-upah">' . $upah_borongan_perjam_2026_formatted . '</div>
                    <div class="cell col-total">' . $upah_borongan_2026_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">Upah Borongan 2026</div>
                    <div class="cell col-jumlah"></div>
                    <div class="cell col-upah"></div>
                    <div class="cell col-total">' . $total_upah_borongan_2026_formatted . '</div>
                </div>
                
                <div style="height: 8px;"></div>
                
                <div class="table-row bg-blue">
                    <div class="cell col-desc">Jml Borongan 2025</div>
                    <div class="cell col-jumlah">' . $formatJumlah($jumlah_borongan_2025) . '</div>
                    <div class="cell col-upah">' . $upah_borongan_perjam_2025_formatted . '</div>
                    <div class="cell col-total">' . $upah_borongan_2025_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">Upah Borongan 2025</div>
                    <div class="cell col-jumlah"></div>
                    <div class="cell col-upah"></div>
                    <div class="cell col-total">' . $total_upah_borongan_2025_formatted . '</div>
                </div>
                
                <div class="table-row total-row">
                    <div class="cell col-desc">Total Upah Per Bulan</div>
                    <div class="cell col-jumlah"></div>
                    <div class="cell col-upah"></div>
                    <div class="cell col-total">' . $total_upah_borongan_per_bulan_formatted . '</div>
                </div>
                
                <div style="height: 8px;"></div>
                
                <div class="table-row bg-blue">
                    <div class="cell col-desc">BPJS TK (JKK, JKM, JHT, JP)</div>
                    <div class="cell col-jumlah"></div>
                    <div class="cell col-upah"></div>
                    <div class="cell col-total">' . $bpjs_tk_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">BPJS KS</div>
                    <div class="cell col-jumlah"></div>
                    <div class="cell col-upah"></div>
                    <div class="cell col-total">' . $bpjs_ks_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">Total Iuran Perusahaan</div>
                    <div class="cell col-jumlah"></div>
                    <div class="cell col-upah"></div>
                    <div class="cell col-total">' . $total_iuran_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">Koreksi Gaji</div>
                    <div class="cell col-jumlah"></div>
                    <div class="cell col-upah"></div>
                    <div class="cell col-total">' . $koreksi_gaji_formatted . '</div>
                </div>
                
                <div class="table-row total-row">
                    <div class="cell col-desc">Grand Total Upah</div>
                    <div class="cell col-jumlah"></div>
                    <div class="cell col-upah"></div>
                    <div class="cell col-total">' . $grand_total_upah_formatted . '</div>
                </div>
            </div>

            <div class="table-spacer">
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
                <div class="spacer-cell"></div>
            </div>

            <div class="table-column">
                <div class="table-row head">
                    <div class="cell col-desc">Potongan (-)</div>
                    <div class="cell col-jumlah">Jumlah</div>
                    <div class="cell col-upah">Upah Perjam</div>
                    <div class="cell col-total">TOTAL</div>
                </div>
                
                <div class="table-row bg-blue">
                    <div class="cell col-desc">DT Borongan 2026</div>
                    <div class="cell col-jumlah">' . $formatJumlah($potongan_dt_borongan_2026) . '</div>
                    <div class="cell col-upah">' . $upah_borongan_perjam_2026_formatted . '</div>
                    <div class="cell col-total">' . $potongan_dt_2026_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">PC Borongan 2026</div>
                    <div class="cell col-jumlah">' . $formatJumlah($potongan_pc_borongan_2026) . '</div>
                    <div class="cell col-upah">' . $upah_borongan_perjam_2026_formatted . '</div>
                    <div class="cell col-total">' . $potongan_pc_2026_formatted . '</div>
                </div>
                
                <div style="height: 8px;"></div>
                
                <div class="table-row bg-blue">
                    <div class="cell col-desc">DT Borongan 2025</div>
                    <div class="cell col-jumlah">' . $formatJumlah($potongan_dt_borongan_2025) . '</div>
                    <div class="cell col-upah">' . $upah_borongan_perjam_2025_formatted . '</div>
                    <div class="cell col-total">' . $potongan_dt_2025_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">PC Borongan 2025</div>
                    <div class="cell col-jumlah">' . $formatJumlah($potongan_pc_borongan_2025) . '</div>
                    <div class="cell col-upah">' . $upah_borongan_perjam_2025_formatted . '</div>
                    <div class="cell col-total">' . $potongan_pc_2025_formatted . '</div>
                </div>
                
                <div style="height: 15px;"></div>
                
                <div class="table-row head">
                    <div class="cell col-desc">Potongan Lain</div>
                    <div class="cell col-jumlah">%</div>
                    <div class="cell col-upah">Ket</div>
                    <div class="cell col-total">TOTAL</div>
                </div>
                
                <div class="table-row bg-blue">
                    <div class="cell col-desc">PPh 21 2026</div>
                    <div class="cell col-jumlah"></div>
                    <div class="cell col-upah"></div>
                    <div class="cell col-total">' . $potongan_pph_21_2026_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">PPh 21 2025</div>
                    <div class="cell col-jumlah"></div>
                    <div class="cell col-upah"></div>
                    <div class="cell col-total">' . $potongan_pph_21_2025_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">BPJS (JHT, JP)</div>
                    <div class="cell col-jumlah">3%</div>
                    <div class="cell col-upah"></div>
                    <div class="cell col-total">' . $bpjs_jht_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">BPJS JKN</div>
                    <div class="cell col-jumlah">1%</div>
                    <div class="cell col-upah"></div>
                    <div class="cell col-total">' . $bpjs_jkn_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">Management Fee</div>
                    <div class="cell col-jumlah">5%</div>
                    <div class="cell col-upah">TUBB X 5%</div>
                    <div class="cell col-total">' . $potongan_management_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">Koreksi</div>
                    <div class="cell col-jumlah"></div>
                    <div class="cell col-upah"></div>
                    <div class="cell col-total">' . $koreksi_formatted . '</div>
                </div>
                <div class="table-row bg-blue">
                    <div class="cell col-desc">Lain-lain</div>
                    <div class="cell col-jumlah"></div>
                    <div class="cell col-upah"></div>
                    <div class="cell col-total">' . $potongan_lain_formatted . '</div>
                </div>
                
                <div class="table-row total-row">
                    <div class="cell col-desc">Grand Total Potongan</div>
                    <div class="cell col-jumlah"></div>
                    <div class="cell col-upah"></div>
                    <div class="cell col-total">' . $grand_total_potongan_formatted . '</div>
                </div>
            </div>
        </div>

        <div class="net-total-row">
            <div class="cell label-net">Upah Bersih Diterima</div>
            <div class="cell value-net">' . $upah_bersih_formatted . '</div>
        </div>

        <div class="footer-container">
            <div class="grid-overlay"></div>
            <div class="triangles">
                <div class="triangle-dark"></div>
                <div class="triangle-light"></div>
            </div>
            
            <div class="signature-block">
                <div class="sig-date">Gresik, ' . date('d F Y') . '</div>
                <div class="sig-company">PT. SEKAR PUTRA DAERAH</div>
                <div class="sig-space">' . $ttd_image_html . '</div>
                <div class="sig-name">' . $nama_ttd . '</div>
                <div class="sig-title">' . $jabatan_ttd . '</div>
            </div>
        </div>
    </div>

    <!-- Print and Save Controls -->
    <div class="no-print" style="position: fixed; bottom: 20px; right: 20px; z-index: 1000;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #002060; color: white; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;">
            🖨️ Cetak
        </button>
        <button onclick="saveAsPDF()" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">
            💾 Save as PDF
        </button>
    </div>

    <script>
        // Print Function
        document.addEventListener("keydown", function(e) {
            if (e.ctrlKey && e.key === "p") {
                e.preventDefault();
                window.print();
            }
        });
        
        // Save as PDF Function
        function saveAsPDF() {
            const element = document.querySelector(".excel-container");
            const opt = {
                margin:       0.5,
                filename:     "slip_gaji_produksi_' . $nama_karyawan . '_' . date('Y-m-d') . '.pdf",
                image:        { type: "jpeg", quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: "in", format: "a4", orientation: "landscape" }
            };
            
            if (typeof html2pdf !== "undefined") {
                html2pdf().set(opt).from(element).save();
            } else {
                const script = document.createElement("script");
                script.src = "https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js";
                script.onload = function() {
                    html2pdf().set(opt).from(element).save();
                };
                document.head.appendChild(script);
                
                setTimeout(function() {
                    if (typeof html2pdf === "undefined") {
                        alert("Gagal memuat library PDF. Menggunakan fitur print sebagai alternatif.");
                        window.print();
                    }
                }, 5000);
            }
        }
        
        // Responsive adjustments
        window.addEventListener("resize", function() {
            const container = document.querySelector(".excel-container");
            if (window.innerWidth < 1000) {
                container.style.width = "95%";
            } else {
                container.style.width = "920px";
            }
        });
    </script>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Get TTD Image HTML dengan ukuran yang lebih besar
     */
    private function getTTDImageHTML($filename) {
        $ttd_image_path = $this->getTTDImagePath($filename);
        
        if (file_exists($ttd_image_path)) {
            $image_data = file_get_contents($ttd_image_path);
            $base64_image = base64_encode($image_data);
            $image_info = getimagesize($ttd_image_path);
            $mime_type = $image_info['mime'];
            
            return '<img src="data:' . $mime_type . ';base64,' . $base64_image . '" 
                    alt="Tanda Tangan" 
                    style="height: auto; width: auto; max-height: 100px; max-width: 250px; object-fit: contain; display: block; margin: 0 auto;">';
        } else {
            return '<div style="border-top: 3px solid #000; width: 200px; height: 1px; margin: 20px auto 0 auto; padding-top: 10px;"></div>';
        }
    }
    
    /**
     * Get TTD Image Path
     */
    private function getTTDImagePath($filename) {
        $possible_paths = [
            dirname(__DIR__, 2) . '/public/uploads/ttd/' . $filename,
            dirname(__DIR__, 2) . '/uploads/ttd/' . $filename,
            dirname(__DIR__, 2) . '/ttd/' . $filename,
            'uploads/ttd/' . $filename,
            'public/uploads/ttd/' . $filename,
            __DIR__ . '/../../public/uploads/ttd/' . $filename,
            __DIR__ . '/../../uploads/ttd/' . $filename,
        ];
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return dirname(__DIR__, 2) . '/public/uploads/ttd/default.png';
    }
    
    /**
     * Simpan file HTML
     */
    private function saveHTMLFile($html, $filename) {
        if (defined('SLIP_PATH')) {
            $slip_path = SLIP_PATH;
        } else {
            $slip_path = dirname(__DIR__, 2) . '/public/uploads/slip';
        }
        
        if (!file_exists($slip_path)) {
            mkdir($slip_path, 0777, true);
        }
        
        $filepath = $slip_path . '/' . $filename;
        $compressed_html = $this->compressHTML($html);
        file_put_contents($filepath, $compressed_html);
        
        return $filepath;
    }
    
    /**
     * Kompres HTML untuk ukuran file lebih kecil
     */
    private function compressHTML($html) {
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/<!--(.|\s)*?-->/', '', $html);
        return $html;
    }
    
    /**
     * Fallback minimal jika semua gagal
     */
    private function generateMinimalSlipFromData($data) {
        $gaji = $data['gaji'] ?? [];
        $karyawan = $data['karyawan'] ?? [];
        $periode = $data['periode'] ?? [];
        
        $html = '<!DOCTYPE html>
        <html>
        <head><title>Slip Gaji ' . htmlspecialchars($karyawan['nama'] ?? '') . '</title></head>
        <body>
        <h2>SLIP GAJI</h2>
        <p>Periode: ' . date('d F Y', strtotime($periode['periode_start'] ?? 'now')) . ' - ' . date('d F Y', strtotime($periode['periode_end'] ?? 'now')) . '</p>
        <p>Nama: ' . htmlspecialchars($karyawan['nama'] ?? '') . '</p>
        <p>NIK: ' . ($karyawan['id'] ?? '') . '</p>
        <p>Dept: ' . htmlspecialchars($karyawan['dept'] ?? '') . '</p>
        <p>Gaji Pokok: Rp ' . number_format($gaji['gaji_pokok'] ?? 0, 0, ',', '.') . '</p>
        <p>Gaji Bersih: Rp ' . number_format($gaji['gaji_bersih'] ?? 0, 0, ',', '.') . '</p>
        <p><em>Untuk slip lengkap, hubungi HRD</em></p>
        </body>
        </html>';
        
        if (isset($karyawan['id']) && isset($periode['id'])) {
            $filename = 'slip_minimal_' . $karyawan['id'] . '_' . $periode['id'] . '_' . date('Ymd_His') . '.html';
        } else {
            $filename = 'slip_minimal_' . date('Ymd_His') . '.html';
        }
        
        $filepath = $this->saveHTMLFile($html, $filename);
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'html' => $html
        ];
    }
    
    /**
     * Metode publik untuk generate slip dari data yang sudah ada
     */
    public function generateSlip($data) {
        if (isset($data['gaji']['id'])) {
            return $this->generateSlipFromDatabase($data['gaji']['id']);
        }
        
        $karyawan = $data['karyawan'] ?? [];
        $pengaturan_gaji = $this->getPengaturanGaji();
        $pengaturan_ttd = $this->getPengaturanTTD();
        
        if ($karyawan['dept'] == 'PRODUKSI') {
            $html = $this->generateHTMLProduksiDesign(
                $data['gaji'] ?? [],
                $data['karyawan'] ?? [],
                $data['periode'] ?? [],
                $data['lembur_details'] ?? [],
                $data['potongan_details'] ?? [],
                $pengaturan_gaji,
                $pengaturan_ttd,
                $data['data_borongan'] ?? null
            );
        } else {
            $html = $this->generateHTMLAdminDesign(
                $data['gaji'] ?? [],
                $data['karyawan'] ?? [],
                $data['periode'] ?? [],
                $data['lembur_details'] ?? [],
                $data['potongan_details'] ?? [],
                $pengaturan_gaji,
                $pengaturan_ttd
            );
        }
        
        $filename = 'slip_gaji_' . ($karyawan['id'] ?? 'unknown') . '_' . date('Ymd_His') . '.html';
        $filepath = $this->saveHTMLFile($html, $filename);
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'html' => $html
        ];
    }
    
    /**
     * Validasi koneksi database
     */
    public function testConnection() {
        try {
            $this->initDatabase();
            $stmt = $this->db->query("SELECT 1");
            return $stmt !== false;
        } catch (\Exception $e) {
            error_log("Database Test Connection Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Dapatkan slip gaji yang sudah digenerate
     */
    public function getGeneratedSlip($gaji_karyawan_id) {
        $query = "SELECT pdf_file FROM gaji_karyawan WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $gaji_karyawan_id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($result && $result['pdf_file']) {
            if (defined('SLIP_PATH')) {
                $slip_path = SLIP_PATH;
            } else {
                $slip_path = dirname(__DIR__, 2) . '/public/uploads/slip';
            }
            
            $filepath = $slip_path . '/' . $result['pdf_file'];
            if (file_exists($filepath)) {
                return [
                    'filename' => $result['pdf_file'],
                    'filepath' => $filepath,
                    'content' => file_get_contents($filepath)
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Re-generate slip gaji untuk semua data yang sudah ada
     */
    public function regenerateAllSlips($periode_id = null) {
        try {
            if ($periode_id) {
                $query = "SELECT id FROM gaji_karyawan WHERE periode_id = :periode_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute([':periode_id' => $periode_id]);
            } else {
                $query = "SELECT id FROM gaji_karyawan";
                $stmt = $this->db->query($query);
            }
            
            $gaji_ids = $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
            
            $results = [
                'total' => count($gaji_ids),
                'success' => 0,
                'failed' => 0,
                'details' => []
            ];
            
            foreach ($gaji_ids as $gaji_id) {
                try {
                    $result = $this->generateSlipFromDatabase($gaji_id);
                    if ($result['success']) {
                        $results['success']++;
                        $results['details'][] = "ID {$gaji_id}: Berhasil digenerate";
                    } else {
                        $results['failed']++;
                        $results['details'][] = "ID {$gaji_id}: Gagal generate";
                    }
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = "ID {$gaji_id}: Error - " . $e->getMessage();
                }
            }
            
            return $results;
            
        } catch (\Exception $e) {
            error_log("Regenerate All Slips Error: " . $e->getMessage());
            return [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'details' => ["Error: " . $e->getMessage()]
            ];
        }
    }
    
    /**
     * Generate slip gaji berdasarkan data yang sudah ada di database
     * dengan sinkronisasi data yang tepat
     */
    public function generateSynchronizedSlip($gaji_karyawan_id) {
        try {
            // Ambil semua data yang diperlukan
            $gaji = $this->getGajiKaryawan($gaji_karyawan_id);
            if (!$gaji) {
                throw new \Exception("Data gaji tidak ditemukan");
            }
            
            // Verifikasi data konsisten
            $this->verifyDataConsistency($gaji);
            
            // Generate slip seperti biasa
            return $this->generateSlipFromDatabase($gaji_karyawan_id);
            
        } catch (\Exception $e) {
            error_log("Synchronized Slip Generation Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Verifikasi konsistensi data
     */
    private function verifyDataConsistency($gaji) {
        // Ambil detail lembur dan potongan
        $lembur_details = $this->getLemburDetails($gaji['id']);
        $potongan_details = $this->getPotonganDetails($gaji['id']);
        
        // Hitung total dari detail
        $total_lembur_detail = 0;
        foreach ($lembur_details as $lembur) {
            $total_lembur_detail += floatval($lembur['total'] ?? 0);
        }
        
        $total_potongan_detail = 0;
        foreach ($potongan_details as $potongan) {
            $total_potongan_detail += floatval($potongan['nominal'] ?? 0);
        }
        
        // Verifikasi konsistensi dengan data master
        $gaji_pokok = floatval($gaji['gaji_pokok'] ?? 0);
        $total_lembur_master = floatval($gaji['total_lembur'] ?? 0);
        $total_potongan_master = floatval($gaji['total_potongan'] ?? 0);
        $total_bpjs_master = floatval($gaji['total_bpjs'] ?? 0);
        $gaji_bersih_master = floatval($gaji['gaji_bersih'] ?? 0);
        
        // Hitung gaji bersih yang seharusnya
        $calculated_bersih = $gaji_pokok + $total_lembur_master - $total_potongan_master - $total_bpjs_master;
        
        // Log jika ada perbedaan signifikan
        if (abs($calculated_bersih - $gaji_bersih_master) > 100) {
            error_log("Data tidak konsisten untuk gaji_karyawan_id: " . $gaji['id']);
            error_log("Gaji bersih master: " . $gaji_bersih_master);
            error_log("Gaji bersih kalkulasi: " . $calculated_bersih);
        }
        
        return true;
    }
}