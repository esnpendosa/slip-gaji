-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 23 Feb 2026 pada 12.14
-- Versi server: 8.0.30
-- Versi PHP: 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Basis data: `sistem_penggajian`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `gaji_karyawan`
--

CREATE TABLE `gaji_karyawan` (
  `id` int NOT NULL,
  `karyawan_id` int NOT NULL,
  `periode_id` int NOT NULL,
  `gaji_pokok` decimal(15,2) DEFAULT '0.00',
  `total_lembur` decimal(15,2) DEFAULT '0.00',
  `total_potongan` decimal(15,2) DEFAULT '0.00',
  `total_bpjs` decimal(15,2) DEFAULT '0.00',
  `gaji_bersih` decimal(15,2) DEFAULT '0.00',
  `pdf_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','generated','sent') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_notified_at` timestamp NULL DEFAULT NULL,
  `notification_count` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `gaji_karyawan`
--

INSERT INTO `gaji_karyawan` (`id`, `karyawan_id`, `periode_id`, `gaji_pokok`, `total_lembur`, `total_potongan`, `total_bpjs`, `gaji_bersih`, `pdf_file`, `status`, `created_at`, `last_notified_at`, `notification_count`) VALUES
(1, 3, 1, 5195000.00, 0.00, 0.00, 0.00, 5195000.00, 'slip_gaji_3_1_20260204_032049.html', 'sent', '2026-02-04 03:20:49', NULL, 0),
(2, 4, 1, 51905000.00, 0.00, 0.00, 0.00, 51905000.00, 'slip_gaji_4_1_20260204_040343.html', 'sent', '2026-02-04 03:27:32', NULL, 0),
(3, 5, 1, 5190500.00, 0.00, 0.00, 0.00, 5190500.00, 'slip_gaji_5_1_20260204_035339.html', 'sent', '2026-02-04 03:51:29', '2026-02-04 03:59:27', 0),
(4, 4, 2, 5195401.00, 200000.00, 207816.04, 207816.04, 5187584.96, 'slip_gaji_4_2_20260204_084824.html', 'sent', '2026-02-04 05:56:37', '2026-02-04 08:48:58', 0),
(5, 1, 2, 5195401.00, 425000.00, 207816.04, 207816.04, 5412584.96, 'slip_gaji_1_2_20260204_084843.html', 'sent', '2026-02-04 05:57:37', '2026-02-04 08:49:00', 0),
(6, 3, 2, 5195401.00, 0.00, 207816.04, 207816.04, 4987584.96, 'slip_gaji_3_2_20260204_084733.html', 'sent', '2026-02-04 05:57:38', '2026-02-04 08:49:03', 0),
(7, 5, 2, 5195401.00, 0.00, 207816.04, 207816.04, 4987584.96, 'slip_gaji_5_2_20260204_084733.html', 'sent', '2026-02-04 05:57:38', '2026-02-04 08:49:05', 0),
(8, 2, 2, 5195401.00, 0.00, 207816.04, 207816.04, 4987584.96, 'slip_gaji_2_2_20260204_084733.html', 'sent', '2026-02-04 05:57:38', '2026-02-04 08:49:07', 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `karyawan`
--

CREATE TABLE `karyawan` (
  `id` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `dept` enum('ADMIN','PRODUKSI') DEFAULT 'ADMIN',
  `no_hp` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `karyawan`
--

INSERT INTO `karyawan` (`id`, `nama`, `dept`, `no_hp`, `email`, `created_at`) VALUES
(1, 'Budi Santoso', 'ADMIN', '6285730302827', NULL, '2026-02-04 02:44:18'),
(2, 'Siti Aminah', 'PRODUKSI', '6289876543210', NULL, '2026-02-04 02:44:18'),
(3, 'Joko Widodo', 'PRODUKSI', '6281122334455', NULL, '2026-02-04 02:44:18'),
(4, 'aad', 'ADMIN', '6283141932593', NULL, '2026-02-04 03:26:52'),
(5, 'Sayang', 'ADMIN', '6287792275754', NULL, '2026-02-04 03:51:07');

-- --------------------------------------------------------

--
-- Struktur dari tabel `lembur_detail`
--

CREATE TABLE `lembur_detail` (
  `id` int NOT NULL,
  `gaji_karyawan_id` int NOT NULL,
  `jenis_lembur_id` int NOT NULL,
  `jam` decimal(5,2) DEFAULT '0.00',
  `total` decimal(15,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `lembur_detail`
--

INSERT INTO `lembur_detail` (`id`, `gaji_karyawan_id`, `jenis_lembur_id`, `jam`, `total`) VALUES
(3, 6, 1, 2.00, 40000.00),
(4, 6, 2, 5.00, 125000.00),
(5, 6, 5, 5.00, 200000.00),
(7, 4, 2, 8.00, 200000.00),
(8, 5, 1, 10.00, 200000.00),
(9, 5, 2, 9.00, 225000.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `lembur_jenis`
--

CREATE TABLE `lembur_jenis` (
  `id` int NOT NULL,
  `nama` varchar(50) NOT NULL,
  `nilai_per_jam` decimal(15,2) NOT NULL,
  `deskripsi` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `lembur_jenis`
--

INSERT INTO `lembur_jenis` (`id`, `nama`, `nilai_per_jam`, `deskripsi`) VALUES
(1, 'Biasa 1', 20000.00, NULL),
(2, 'Biasa 2', 25000.00, NULL),
(3, 'Libur 1', 30000.00, NULL),
(4, 'Libur 2', 35000.00, NULL),
(5, 'Libur 3', 40000.00, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi_log`
--

CREATE TABLE `notifikasi_log` (
  `id` int NOT NULL,
  `gaji_karyawan_id` int NOT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `pesan` text,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `response` text,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `notifikasi_log`
--

INSERT INTO `notifikasi_log` (`id`, `gaji_karyawan_id`, `no_hp`, `pesan`, `status`, `response`, `sent_at`, `created_at`) VALUES
(1, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142043196],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2779,\"remaining\":2778,\"used\":1}},\"requestid\":367177728,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 03:25:22', '2026-02-04 03:25:22'),
(2, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142043302],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2778,\"remaining\":2777,\"used\":1}},\"requestid\":367178672,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 03:25:54', '2026-02-04 03:25:54'),
(3, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142043350],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2777,\"remaining\":2776,\"used\":1}},\"requestid\":367179553,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 03:26:09', '2026-02-04 03:26:09'),
(4, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142043622],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2776,\"remaining\":2775,\"used\":1}},\"requestid\":367182787,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 03:27:42', '2026-02-04 03:27:42'),
(5, 2, '6283141932593', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142043626],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2775,\"remaining\":2774,\"used\":1}},\"requestid\":367182867,\"status\":true,\"target\":[\"6283141932593\"]}}', '2026-02-04 03:27:44', '2026-02-04 03:27:44'),
(6, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142043701],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2774,\"remaining\":2773,\"used\":1}},\"requestid\":367184623,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 03:28:17', '2026-02-04 03:28:17'),
(7, 2, '6283141932593', 'Slip gaji periode Januari 2024', 'failed', '{\"success\":false,\"message\":\"Gagal terhubung ke API Fonnte: \",\"http_code\":0}', '2026-02-04 03:28:40', '2026-02-04 03:28:40'),
(8, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142044625],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2773,\"remaining\":2772,\"used\":1}},\"requestid\":367186496,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 03:29:13', '2026-02-04 03:29:13'),
(9, 2, '6283141932593', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142044630],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2772,\"remaining\":2771,\"used\":1}},\"requestid\":367186557,\"status\":true,\"target\":[\"6283141932593\"]}}', '2026-02-04 03:29:15', '2026-02-04 03:29:15'),
(10, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'failed', '{\"success\":false,\"message\":\"Gagal terhubung ke API Fonnte: \",\"http_code\":0}', '2026-02-04 03:30:09', '2026-02-04 03:30:09'),
(11, 2, '6283141932593', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142044925],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2771,\"remaining\":2770,\"used\":1}},\"requestid\":367188768,\"status\":true,\"target\":[\"6283141932593\"]}}', '2026-02-04 03:30:22', '2026-02-04 03:30:22'),
(12, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045025],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2770,\"remaining\":2769,\"used\":1}},\"requestid\":367189885,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 03:30:55', '2026-02-04 03:30:55'),
(13, 2, '6283141932593', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045035],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2769,\"remaining\":2768,\"used\":1}},\"requestid\":367189955,\"status\":true,\"target\":[\"6283141932593\"]}}', '2026-02-04 03:30:57', '2026-02-04 03:30:57'),
(14, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045137],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2768,\"remaining\":2767,\"used\":1}},\"requestid\":367191709,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 03:31:30', '2026-02-04 03:31:30'),
(15, 2, '6283141932593', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045140],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2767,\"remaining\":2766,\"used\":1}},\"requestid\":367191770,\"status\":true,\"target\":[\"6283141932593\"]}}', '2026-02-04 03:31:32', '2026-02-04 03:31:32'),
(16, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045249],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2766,\"remaining\":2765,\"used\":1}},\"requestid\":367192983,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 03:32:06', '2026-02-04 03:32:06'),
(17, 2, '6283141932593', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045252],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2765,\"remaining\":2764,\"used\":1}},\"requestid\":367193114,\"status\":true,\"target\":[\"6283141932593\"]}}', '2026-02-04 03:32:08', '2026-02-04 03:32:08'),
(18, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045322],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2764,\"remaining\":2763,\"used\":1}},\"requestid\":367193852,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 03:32:41', '2026-02-04 03:32:41'),
(19, 2, '6283141932593', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045330],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2763,\"remaining\":2762,\"used\":1}},\"requestid\":367193912,\"status\":true,\"target\":[\"6283141932593\"]}}', '2026-02-04 03:32:43', '2026-02-04 03:32:43'),
(20, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045409],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2762,\"remaining\":2761,\"used\":1}},\"requestid\":367194964,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 03:33:16', '2026-02-04 03:33:16'),
(21, 2, '6283141932593', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045413],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2761,\"remaining\":2760,\"used\":1}},\"requestid\":367194988,\"status\":true,\"target\":[\"6283141932593\"]}}', '2026-02-04 03:33:18', '2026-02-04 03:33:18'),
(22, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045482],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2760,\"remaining\":2759,\"used\":1}},\"requestid\":367195516,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 03:33:51', '2026-02-04 03:33:51'),
(23, 2, '6283141932593', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045484],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2759,\"remaining\":2758,\"used\":1}},\"requestid\":367195538,\"status\":true,\"target\":[\"6283141932593\"]}}', '2026-02-04 03:33:53', '2026-02-04 03:33:53'),
(24, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045547],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2756,\"remaining\":2755,\"used\":1}},\"requestid\":367196473,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 03:34:26', '2026-02-04 03:34:26'),
(25, 2, '6283141932593', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045554],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2755,\"remaining\":2754,\"used\":1}},\"requestid\":367196508,\"status\":true,\"target\":[\"6283141932593\"]}}', '2026-02-04 03:34:28', '2026-02-04 03:34:28'),
(26, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045635],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2752,\"remaining\":2751,\"used\":1}},\"requestid\":367197109,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 03:35:01', '2026-02-04 03:35:01'),
(27, 2, '6283141932593', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045643],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2751,\"remaining\":2750,\"used\":1}},\"requestid\":367197301,\"status\":true,\"target\":[\"6283141932593\"]}}', '2026-02-04 03:35:03', '2026-02-04 03:35:03'),
(28, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045741],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2750,\"remaining\":2749,\"used\":1}},\"requestid\":367198590,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 03:35:36', '2026-02-04 03:35:36'),
(29, 2, '6283141932593', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045747],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2749,\"remaining\":2748,\"used\":1}},\"requestid\":367198670,\"status\":true,\"target\":[\"6283141932593\"]}}', '2026-02-04 03:35:38', '2026-02-04 03:35:38'),
(30, 1, '6281122334455', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045855],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2748,\"remaining\":2747,\"used\":1}},\"requestid\":367200759,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 03:36:11', '2026-02-04 03:36:11'),
(31, 2, '6283141932593', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142045858],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2747,\"remaining\":2746,\"used\":1}},\"requestid\":367200864,\"status\":true,\"target\":[\"6283141932593\"]}}', '2026-02-04 03:36:13', '2026-02-04 03:36:13'),
(32, 3, '6287792275754', 'Slip gaji periode Januari 2024', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142049827],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2737,\"remaining\":2736,\"used\":1}},\"requestid\":367243521,\"status\":true,\"target\":[\"6287792275754\"]}}', '2026-02-04 03:59:27', '2026-02-04 03:59:27'),
(33, 4, '6283141932593', 'Slip gaji periode Februari 2026', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142067617],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2712,\"remaining\":2711,\"used\":1}},\"requestid\":367480685,\"status\":true,\"target\":[\"6283141932593\"]}}', '2026-02-04 05:58:02', '2026-02-04 05:58:02'),
(34, 5, '6285730302827', 'Slip gaji periode Februari 2026', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142067628],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2711,\"remaining\":2710,\"used\":1}},\"requestid\":367480907,\"status\":true,\"target\":[\"6285730302827\"]}}', '2026-02-04 05:58:04', '2026-02-04 05:58:04'),
(35, 6, '6281122334455', 'Slip gaji periode Februari 2026', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142067640],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2710,\"remaining\":2709,\"used\":1}},\"requestid\":367481157,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 05:58:07', '2026-02-04 05:58:07'),
(36, 7, '6287792275754', 'Slip gaji periode Februari 2026', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142067647],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2709,\"remaining\":2708,\"used\":1}},\"requestid\":367481332,\"status\":true,\"target\":[\"6287792275754\"]}}', '2026-02-04 05:58:09', '2026-02-04 05:58:09'),
(37, 8, '6289876543210', 'Slip gaji periode Februari 2026', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142067655],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2708,\"remaining\":2707,\"used\":1}},\"requestid\":367481454,\"status\":true,\"target\":[\"6289876543210\"]}}', '2026-02-04 05:58:11', '2026-02-04 05:58:11'),
(38, 4, '6283141932593', 'Slip gaji periode Februari 2026', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142087194],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2707,\"remaining\":2706,\"used\":1}},\"requestid\":367746446,\"status\":true,\"target\":[\"6283141932593\"]}}', '2026-02-04 07:57:12', '2026-02-04 07:57:12'),
(39, 5, '6285730302827', 'Slip gaji periode Februari 2026', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142087201],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2706,\"remaining\":2705,\"used\":1}},\"requestid\":367746484,\"status\":true,\"target\":[\"6285730302827\"]}}', '2026-02-04 07:57:14', '2026-02-04 07:57:14'),
(40, 6, '6281122334455', 'Slip gaji periode Februari 2026', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142087207],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2705,\"remaining\":2704,\"used\":1}},\"requestid\":367746533,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 07:57:16', '2026-02-04 07:57:16'),
(41, 7, '6287792275754', 'Slip gaji periode Februari 2026', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142087211],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2704,\"remaining\":2703,\"used\":1}},\"requestid\":367746590,\"status\":true,\"target\":[\"6287792275754\"]}}', '2026-02-04 07:57:19', '2026-02-04 07:57:19'),
(42, 8, '6289876543210', 'Slip gaji periode Februari 2026', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142087215],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2703,\"remaining\":2702,\"used\":1}},\"requestid\":367746649,\"status\":true,\"target\":[\"6289876543210\"]}}', '2026-02-04 07:57:21', '2026-02-04 07:57:21'),
(43, 4, '6283141932593', 'Slip gaji periode Februari 2026', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142096225],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2698,\"remaining\":2697,\"used\":1}},\"requestid\":367856913,\"status\":true,\"target\":[\"6283141932593\"]}}', '2026-02-04 08:48:58', '2026-02-04 08:48:58'),
(44, 5, '6285730302827', 'Slip gaji periode Februari 2026', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142096236],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2697,\"remaining\":2696,\"used\":1}},\"requestid\":367856993,\"status\":true,\"target\":[\"6285730302827\"]}}', '2026-02-04 08:49:00', '2026-02-04 08:49:00'),
(45, 6, '6281122334455', 'Slip gaji periode Februari 2026', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142096241],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2696,\"remaining\":2695,\"used\":1}},\"requestid\":367857148,\"status\":true,\"target\":[\"6281122334455\"]}}', '2026-02-04 08:49:03', '2026-02-04 08:49:03'),
(46, 7, '6287792275754', 'Slip gaji periode Februari 2026', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142096243],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2695,\"remaining\":2694,\"used\":1}},\"requestid\":367857336,\"status\":true,\"target\":[\"6287792275754\"]}}', '2026-02-04 08:49:05', '2026-02-04 08:49:05'),
(47, 8, '6289876543210', 'Slip gaji periode Februari 2026', 'sent', '{\"success\":true,\"message\":\"Notifikasi berhasil dikirim\",\"data\":{\"detail\":\"success! message in queue\",\"id\":[142096250],\"process\":\"pending\",\"quota\":{\"085730302827\":{\"details\":\"deduced from total quota\",\"quota\":2694,\"remaining\":2693,\"used\":1}},\"requestid\":367857599,\"status\":true,\"target\":[\"6289876543210\"]}}', '2026-02-04 08:49:07', '2026-02-04 08:49:07');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan_gaji`
--

CREATE TABLE `pengaturan_gaji` (
  `id` int NOT NULL,
  `nama_perusahaan` varchar(100) DEFAULT 'PT. SEKAR PUTRA DAERAH',
  `alamat` text,
  `telepon` varchar(20) DEFAULT NULL,
  `gaji_pokok_global` decimal(15,2) DEFAULT '0.00',
  `bpjs_jht_percent` decimal(5,2) DEFAULT '3.00',
  `bpjs_jkn_percent` decimal(5,2) DEFAULT '1.00',
  `uang_makan` decimal(15,2) DEFAULT '0.00',
  `tunjangan_lain` decimal(15,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `pengaturan_gaji`
--

INSERT INTO `pengaturan_gaji` (`id`, `nama_perusahaan`, `alamat`, `telepon`, `gaji_pokok_global`, `bpjs_jht_percent`, `bpjs_jkn_percent`, `uang_makan`, `tunjangan_lain`, `created_at`, `updated_at`) VALUES
(1, 'PT. SEKAR PUTRA DAERAH', 'Jl. Raya Gresik - Surabaya, Gresik', '031-3980000', 5195401.00, 3.00, 1.00, 0.00, 0.00, '2026-02-04 04:21:20', '2026-02-04 05:36:28');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan_sistem`
--

CREATE TABLE `pengaturan_sistem` (
  `id` int NOT NULL,
  `gaji_pokok_global` decimal(15,2) DEFAULT '0.00',
  `bpjs_jht_percent` decimal(5,2) DEFAULT '3.00',
  `bpjs_jkn_percent` decimal(5,2) DEFAULT '1.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `pengaturan_sistem`
--

INSERT INTO `pengaturan_sistem` (`id`, `gaji_pokok_global`, `bpjs_jht_percent`, `bpjs_jkn_percent`, `created_at`, `updated_at`) VALUES
(1, 5195401.00, 3.00, 1.00, '2026-02-04 04:19:33', '2026-02-04 05:36:53');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan_ttd`
--

CREATE TABLE `pengaturan_ttd` (
  `id` int NOT NULL,
  `nama_direktur` varchar(100) DEFAULT 'Sumiati',
  `jabatan` varchar(100) DEFAULT 'DIREKTUR',
  `file_ttd` varchar(255) DEFAULT 'ttd_direktur.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `pengaturan_ttd`
--

INSERT INTO `pengaturan_ttd` (`id`, `nama_direktur`, `jabatan`, `file_ttd`) VALUES
(1, 'Sumiati', 'DIREKTUR', 'ttd_direktur.png');

-- --------------------------------------------------------

--
-- Struktur dari tabel `periode_gaji`
--

CREATE TABLE `periode_gaji` (
  `id` int NOT NULL,
  `nama_periode` varchar(50) DEFAULT NULL,
  `periode_start` date NOT NULL,
  `periode_end` date NOT NULL,
  `status` enum('draft','final','paid') DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `periode_gaji`
--

INSERT INTO `periode_gaji` (`id`, `nama_periode`, `periode_start`, `periode_end`, `status`, `created_at`) VALUES
(1, 'Januari 2024', '2024-01-01', '2024-01-31', 'paid', '2026-02-04 02:44:18'),
(2, 'Februari 2026', '2026-02-04', '2026-03-04', 'final', '2026-02-04 05:55:14');

-- --------------------------------------------------------

--
-- Struktur dari tabel `potongan_detail`
--

CREATE TABLE `potongan_detail` (
  `id` int NOT NULL,
  `gaji_karyawan_id` int NOT NULL,
  `jenis_potongan_id` int NOT NULL,
  `nominal` decimal(15,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `potongan_detail`
--

INSERT INTO `potongan_detail` (`id`, `gaji_karyawan_id`, `jenis_potongan_id`, `nominal`) VALUES
(9, 6, 3, 155862.03),
(10, 6, 4, 51954.01),
(11, 7, 3, 155862.03),
(12, 7, 4, 51954.01),
(13, 8, 3, 155862.03),
(14, 8, 4, 51954.01),
(15, 4, 3, 155862.03),
(16, 4, 4, 51954.01),
(17, 5, 3, 155862.03),
(18, 5, 4, 51954.01);

-- --------------------------------------------------------

--
-- Struktur dari tabel `potongan_jenis`
--

CREATE TABLE `potongan_jenis` (
  `id` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jenis` enum('fixed','percentage') DEFAULT 'fixed',
  `nilai` decimal(15,2) DEFAULT NULL,
  `berlaku` enum('semua','produksi','admin') DEFAULT 'semua'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `potongan_jenis`
--

INSERT INTO `potongan_jenis` (`id`, `nama`, `jenis`, `nilai`, `berlaku`) VALUES
(1, 'Potongan Ijin/Alpha', 'fixed', 50000.00, 'semua'),
(2, 'Potongan DT/PC', 'fixed', 30000.00, 'semua'),
(3, 'BPJS (JHT, JP)', 'percentage', 3.00, 'semua'),
(4, 'BPJS JKN', 'percentage', 1.00, 'semua'),
(5, 'Koreksi', 'fixed', 0.00, 'semua'),
(6, 'Lain-lain', 'fixed', 0.00, 'semua'),
(7, 'Management Fee', 'percentage', 5.00, 'produksi'),
(8, 'PPh 21 2026', 'percentage', 0.00, 'produksi'),
(9, 'PPh 21 2025', 'percentage', 0.00, 'produksi');

-- --------------------------------------------------------

--
-- Struktur dari tabel `produksi_data`
--

CREATE TABLE `produksi_data` (
  `id` int NOT NULL,
  `gaji_karyawan_id` int NOT NULL,
  `jumlah_2026` decimal(10,2) DEFAULT '0.00',
  `upah_perjam_2026` decimal(15,2) DEFAULT '0.00',
  `jumlah_2025` decimal(10,2) DEFAULT '0.00',
  `upah_perjam_2025` decimal(15,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', '2026-02-04 02:44:17');

--
-- Indeks untuk tabel yang dibuang
--

--
-- Indeks untuk tabel `gaji_karyawan`
--
ALTER TABLE `gaji_karyawan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `karyawan_id` (`karyawan_id`),
  ADD KEY `periode_id` (`periode_id`);

--
-- Indeks untuk tabel `karyawan`
--
ALTER TABLE `karyawan`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `lembur_detail`
--
ALTER TABLE `lembur_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gaji_karyawan_id` (`gaji_karyawan_id`),
  ADD KEY `jenis_lembur_id` (`jenis_lembur_id`);

--
-- Indeks untuk tabel `lembur_jenis`
--
ALTER TABLE `lembur_jenis`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `notifikasi_log`
--
ALTER TABLE `notifikasi_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gaji_karyawan_id` (`gaji_karyawan_id`);

--
-- Indeks untuk tabel `pengaturan_gaji`
--
ALTER TABLE `pengaturan_gaji`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pengaturan_sistem`
--
ALTER TABLE `pengaturan_sistem`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pengaturan_ttd`
--
ALTER TABLE `pengaturan_ttd`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `periode_gaji`
--
ALTER TABLE `periode_gaji`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `potongan_detail`
--
ALTER TABLE `potongan_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gaji_karyawan_id` (`gaji_karyawan_id`),
  ADD KEY `jenis_potongan_id` (`jenis_potongan_id`);

--
-- Indeks untuk tabel `potongan_jenis`
--
ALTER TABLE `potongan_jenis`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `produksi_data`
--
ALTER TABLE `produksi_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gaji_karyawan_id` (`gaji_karyawan_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `gaji_karyawan`
--
ALTER TABLE `gaji_karyawan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `karyawan`
--
ALTER TABLE `karyawan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `lembur_detail`
--
ALTER TABLE `lembur_detail`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `lembur_jenis`
--
ALTER TABLE `lembur_jenis`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `notifikasi_log`
--
ALTER TABLE `notifikasi_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT untuk tabel `pengaturan_gaji`
--
ALTER TABLE `pengaturan_gaji`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pengaturan_sistem`
--
ALTER TABLE `pengaturan_sistem`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pengaturan_ttd`
--
ALTER TABLE `pengaturan_ttd`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `periode_gaji`
--
ALTER TABLE `periode_gaji`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `potongan_detail`
--
ALTER TABLE `potongan_detail`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT untuk tabel `potongan_jenis`
--
ALTER TABLE `potongan_jenis`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `produksi_data`
--
ALTER TABLE `produksi_data`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `gaji_karyawan`
--
ALTER TABLE `gaji_karyawan`
  ADD CONSTRAINT `gaji_karyawan_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gaji_karyawan_ibfk_2` FOREIGN KEY (`periode_id`) REFERENCES `periode_gaji` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `lembur_detail`
--
ALTER TABLE `lembur_detail`
  ADD CONSTRAINT `lembur_detail_ibfk_1` FOREIGN KEY (`gaji_karyawan_id`) REFERENCES `gaji_karyawan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lembur_detail_ibfk_2` FOREIGN KEY (`jenis_lembur_id`) REFERENCES `lembur_jenis` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `notifikasi_log`
--
ALTER TABLE `notifikasi_log`
  ADD CONSTRAINT `notifikasi_log_ibfk_1` FOREIGN KEY (`gaji_karyawan_id`) REFERENCES `gaji_karyawan` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `potongan_detail`
--
ALTER TABLE `potongan_detail`
  ADD CONSTRAINT `potongan_detail_ibfk_1` FOREIGN KEY (`gaji_karyawan_id`) REFERENCES `gaji_karyawan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `potongan_detail_ibfk_2` FOREIGN KEY (`jenis_potongan_id`) REFERENCES `potongan_jenis` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `produksi_data`
--
ALTER TABLE `produksi_data`
  ADD CONSTRAINT `produksi_data_ibfk_1` FOREIGN KEY (`gaji_karyawan_id`) REFERENCES `gaji_karyawan` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
