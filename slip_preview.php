<?php
require_once 'config.php';

// Preview slip tanpa login (untuk embed iframe)
if (isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $filepath = SLIP_PATH . '/' . $filename;
    
    if (file_exists($filepath)) {
        $html = file_get_contents($filepath);
        
        // Tambahkan meta untuk scaling yang baik di iframe
        $html = str_replace(
            '<head>',
            '<head><meta name="viewport" content="width=device-width, initial-scale=0.8">',
            $html
        );
        
        echo $html;
    } else {
        echo '<div class="alert alert-danger">File tidak ditemukan</div>';
    }
    exit();
}

echo '<div class="alert alert-warning">Tidak ada file untuk dipreview</div>';
?>