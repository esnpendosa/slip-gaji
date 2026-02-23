<?php
require_once 'config.php';

class FonnteAPISimple {
    private $apiKey;
    
    public function __construct() {
        $this->apiKey = defined('FONNTE_API_KEY') ? FONNTE_API_KEY : '';
    }
    
    /**
     * Kirim notifikasi WhatsApp dengan cara yang sederhana dan pasti bekerja
     */
    public function sendWhatsApp($phone, $message) {
        // Debug: Tampilkan data yang akan dikirim
        error_log("Fonnte API - Phone: $phone, Message: " . substr($message, 0, 50));
        
        if (empty($this->apiKey)) {
            error_log("Fonnte API - API Key kosong");
            return ['success' => false, 'message' => 'API Key tidak ditemukan'];
        }
        
        if (empty($phone)) {
            error_log("Fonnte API - Phone kosong");
            return ['success' => false, 'message' => 'Nomor HP tidak valid'];
        }
        
        // Format nomor HP
        $phone = $this->formatPhoneNumber($phone);
        if (!$phone) {
            error_log("Fonnte API - Format phone tidak valid: $phone");
            return ['success' => false, 'message' => 'Format nomor HP tidak valid'];
        }
        
        // URL API Fonnte
        $url = 'https://api.fonnte.com/send';
        
        // Data untuk POST
        $data = [
            'target' => $phone,
            'message' => $message,
            'countryCode' => '62',
            'delay' => 2 // Delay 2 detik
        ];
        
        // Encode data
        $postData = http_build_query($data);
        
        // Buat header
        $headers = [
            'Authorization: ' . $this->apiKey,
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($postData)
        ];
        
        error_log("Fonnte API - Mengirim ke: $url");
        error_log("Fonnte API - Data: " . json_encode($data));
        
        // Coba dengan curl dulu
        if (function_exists('curl_init')) {
            error_log("Fonnte API - Menggunakan CURL");
            return $this->sendWithCurl($url, $data, $headers);
        } 
        
        // Fallback ke file_get_contents
        error_log("Fonnte API - Menggunakan file_get_contents");
        return $this->sendWithFileGetContents($url, $data, $headers);
    }
    
    private function sendWithCurl($url, $data, $headers) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_VERBOSE => true // Untuk debugging
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        error_log("Fonnte API CURL - Response: $response");
        error_log("Fonnte API CURL - HTTP Code: $httpCode");
        error_log("Fonnte API CURL - Error: $error");
        
        curl_close($ch);
        
        return $this->processResponse($response, $httpCode, $error);
    }
    
    private function sendWithFileGetContents($url, $data, $headers) {
        $options = [
            'http' => [
                'header' => implode("\r\n", $headers),
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 30,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        
        $context = stream_context_create($options);
        
        try {
            error_log("Fonnte API - Mengakses URL: $url");
            $response = @file_get_contents($url, false, $context);
            
            // Ambil HTTP status code dari header
            $httpCode = 0;
            if (isset($http_response_header[0])) {
                preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
                $httpCode = isset($matches[1]) ? (int)$matches[1] : 0;
            }
            
            error_log("Fonnte API file_get_contents - Response: " . substr($response, 0, 500));
            error_log("Fonnte API file_get_contents - HTTP Code: $httpCode");
            
            return $this->processResponse($response, $httpCode, null);
            
        } catch (Exception $e) {
            error_log("Fonnte API file_get_contents - Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'http_code' => 0
            ];
        }
    }
    
    private function processResponse($response, $httpCode, $error) {
        // Simpan log ke file untuk debugging
        $this->logToFile([
            'response' => $response,
            'http_code' => $httpCode,
            'error' => $error
        ]);
        
        if ($response === false) {
            return [
                'success' => false,
                'message' => 'Gagal terhubung: ' . $error,
                'http_code' => $httpCode
            ];
        }
        
        // Parse response JSON
        $result = json_decode($response, true);
        
        if ($httpCode == 200 && isset($result['status']) && $result['status'] == true) {
            return [
                'success' => true,
                'message' => 'Notifikasi berhasil dikirim',
                'data' => $result
            ];
        } else {
            $errorMsg = isset($result['reason']) ? $result['reason'] : 'Unknown error';
            return [
                'success' => false,
                'message' => 'Gagal: ' . $errorMsg,
                'http_code' => $httpCode,
                'data' => $result
            ];
        }
    }
    
    private function formatPhoneNumber($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (empty($phone)) {
            return false;
        }
        
        // Jika diawali dengan 0, ganti dengan 62
        if (substr($phone, 0, 1) == '0') {
            $phone = '62' . substr($phone, 1);
        }
        
        // Jika diawali dengan +62, hapus +
        if (substr($phone, 0, 3) == '+62') {
            $phone = '62' . substr($phone, 3);
        }
        
        // Pastikan panjang minimal 10 digit
        if (strlen($phone) < 10) {
            return false;
        }
        
        return $phone;
    }
    
    private function logToFile($data) {
        $logDir = dirname(__DIR__) . '/logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . '/fonnte_debug_' . date('Y-m-d') . '.txt';
        $logEntry = date('Y-m-d H:i:s') . " - " . json_encode($data, JSON_PRETTY_PRINT) . PHP_EOL . "---" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Kirim slip gaji
     */
    public function sendSlipGaji($phone, $nama, $periode, $linkSlip) {
        $message = "Halo {$nama}!\n\n";
        $message .= "Slip gaji periode {$periode} sudah tersedia.\n\n";
        $message .= "Anda dapat mengakses slip gaji Anda melalui link berikut:\n";
        $message .= $linkSlip . "\n\n";
        $message .= "Terima kasih.\n";
        $message .= "PT. SEKAR PUTRA DAERAH";
        
        return $this->sendWhatsApp($phone, $message);
    }
    
    /**
     * Test koneksi API
     */
    public function testConnection() {
        // Test dengan nomor Anda sendiri atau nomor dummy
        $testPhone = '6285730302827'; // Ganti dengan nomor Anda
        $testMessage = 'Test koneksi API Fonnte - ' . date('Y-m-d H:i:s');
        
        error_log("Test Connection - Phone: $testPhone");
        
        return $this->sendWhatsApp($testPhone, $testMessage);
    }
}
?>