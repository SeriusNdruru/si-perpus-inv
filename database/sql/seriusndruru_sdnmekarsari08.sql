-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 03 Agu 2026 pada 08.59
-- Versi server: 10.5.29-MariaDB-log
-- Versi PHP: 8.4.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Basis data: `seriusndruru_sdnmekarsari08`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `assets`
--

CREATE TABLE `assets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `asset_code` varchar(90) NOT NULL,
  `barcode` varchar(100) NOT NULL,
  `serial_number` varchar(120) DEFAULT NULL,
  `condition_status` enum('good','fair','damaged','lost') NOT NULL DEFAULT 'good',
  `asset_status` enum('unprocessed','available','borrowed','reserved','maintenance','damaged','lost','disposed') NOT NULL DEFAULT 'available',
  `acquisition_date` date DEFAULT NULL,
  `acquisition_source` enum('purchase','donation','grant','transfer','other') NOT NULL DEFAULT 'purchase',
  `acquisition_price` decimal(15,2) UNSIGNED DEFAULT NULL,
  `supplier_id` bigint(20) UNSIGNED DEFAULT NULL,
  `current_location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `current_shelf_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `assets`
--

INSERT INTO `assets` (`id`, `item_id`, `asset_code`, `barcode`, `serial_number`, `condition_status`, `asset_status`, `acquisition_date`, `acquisition_source`, `acquisition_price`, `supplier_id`, `current_location_id`, `current_shelf_id`, `notes`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'BK-TEST-001-001', 'BK-TEST-001-001', NULL, 'good', 'disposed', '2026-07-31', 'purchase', NULL, NULL, 3, NULL, NULL, 2, 2, '2026-07-31 12:21:55', '2026-08-03 12:06:49'),
(2, 1, 'BK-TEST-001-002', 'BK-TEST-001-002', NULL, 'good', 'disposed', '2026-07-31', 'purchase', NULL, NULL, 3, NULL, NULL, 2, 2, '2026-07-31 12:21:55', '2026-08-03 12:07:47'),
(3, 1, 'BK-TEST-001-003', 'BK-TEST-001-003', NULL, 'good', 'disposed', '2026-07-31', 'purchase', NULL, NULL, 3, NULL, NULL, 2, 2, '2026-07-31 12:21:55', '2026-08-03 12:08:55');

--
-- Trigger `assets`
--
DELIMITER $$
CREATE TRIGGER `trg_assets_after_update_shelf` AFTER UPDATE ON `assets` FOR EACH ROW BEGIN
    IF NOT (OLD.current_shelf_id <=> NEW.current_shelf_id) THEN
        INSERT INTO asset_shelf_history (
            asset_id, old_shelf_id, new_shelf_id, changed_by, notes
        ) VALUES (
            NEW.id, OLD.current_shelf_id, NEW.current_shelf_id, @app_user_id,
            'Perubahan rak buku.'
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_assets_before_insert` BEFORE INSERT ON `assets` FOR EACH ROW BEGIN
    DECLARE v_tracking_type VARCHAR(20);
    DECLARE v_item_type VARCHAR(30);

    SELECT tracking_type, item_type
    INTO v_tracking_type, v_item_type
    FROM items
    WHERE id = NEW.item_id;

    IF v_tracking_type IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Item untuk aset tidak ditemukan.';
    END IF;

    IF v_tracking_type <> 'asset' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Aset individual hanya boleh dibuat untuk item tracking_type asset.';
    END IF;

    IF v_item_type = 'book' THEN
        SET NEW.asset_status = 'unprocessed';
        SET NEW.current_shelf_id = NULL;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_assets_before_update` BEFORE UPDATE ON `assets` FOR EACH ROW BEGIN
    DECLARE v_item_type VARCHAR(30);
    DECLARE v_completion_status VARCHAR(30);
    DECLARE v_shelf_status VARCHAR(20);

    SELECT i.item_type, bd.completion_status
    INTO v_item_type, v_completion_status
    FROM items i
    LEFT JOIN book_details bd ON bd.item_id = i.id
    WHERE i.id = NEW.item_id;

    IF NEW.current_shelf_id IS NOT NULL THEN
        SELECT status INTO v_shelf_status
        FROM library_shelves
        WHERE id = NEW.current_shelf_id;

        IF v_shelf_status IS NULL OR v_shelf_status <> 'active' THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Rak tidak ditemukan atau tidak aktif.';
        END IF;
    END IF;

    IF v_item_type = 'book' AND NEW.asset_status = 'available' THEN
        IF NEW.current_shelf_id IS NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Buku belum dapat tersedia sebelum rak ditentukan.';
        END IF;

        IF v_completion_status NOT IN ('complete', 'verified') THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Buku belum dapat tersedia sebelum data katalog dilengkapi.';
        END IF;

        IF NEW.condition_status IN ('damaged', 'lost') THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Buku rusak atau hilang tidak dapat berstatus available.';
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `asset_shelf_history`
--

CREATE TABLE `asset_shelf_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `asset_id` bigint(20) UNSIGNED NOT NULL,
  `old_shelf_id` bigint(20) UNSIGNED DEFAULT NULL,
  `new_shelf_id` bigint(20) UNSIGNED DEFAULT NULL,
  `changed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `asset_shelf_history`
--

INSERT INTO `asset_shelf_history` (`id`, `asset_id`, `old_shelf_id`, `new_shelf_id`, `changed_by`, `changed_at`, `notes`) VALUES
(1, 1, NULL, 1, 4, '2026-07-31 13:50:32', 'Penempatan massal eksemplar ke rak melalui modul perpustakaan.'),
(2, 2, NULL, 1, 4, '2026-07-31 13:50:32', 'Penempatan massal eksemplar ke rak melalui modul perpustakaan.'),
(3, 3, NULL, 1, 4, '2026-07-31 13:50:32', 'Penempatan massal eksemplar ke rak melalui modul perpustakaan.'),
(4, 1, 1, NULL, 2, '2026-08-03 12:06:48', 'Penempatan rak dilepas karena aset dihapuskan.'),
(5, 1, 1, NULL, NULL, '2026-08-03 05:06:49', 'Perubahan rak buku.'),
(6, 2, 1, NULL, 2, '2026-08-03 12:07:47', 'Penempatan rak dilepas karena aset dihapuskan.'),
(7, 2, 1, NULL, NULL, '2026-08-03 05:07:48', 'Perubahan rak buku.'),
(8, 3, 1, NULL, 2, '2026-08-03 12:08:55', 'Penempatan rak dilepas karena aset dihapuskan.'),
(9, 3, 1, NULL, NULL, '2026-08-03 05:08:55', 'Perubahan rak buku.');

-- --------------------------------------------------------

--
-- Struktur dari tabel `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` enum('login','logout','insert','update','delete','approve','export','other') NOT NULL,
  `module_name` varchar(80) NOT NULL,
  `table_name` varchar(80) DEFAULT NULL,
  `record_id` bigint(20) UNSIGNED DEFAULT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `module_name`, `table_name`, `record_id`, `old_data`, `new_data`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 4, 'insert', 'library_loans', 'loans', 1, NULL, '{\"loan_code\":\"PJM-20260801-000819-WRNA\",\"member_id\":1,\"asset_ids\":[1],\"due_date\":\"2026-08-08\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-07-31 17:08:19'),
(2, 4, 'update', 'library_returns', 'loan_items', 1, '{\"return_status\":\"borrowed\",\"asset_status\":\"borrowed\",\"fine_amount\":0}', '{\"return_status\":\"returned\",\"condition_in\":\"good\",\"days_late\":0,\"fine_amount\":0,\"returned_at\":\"2026-08-01 00:20:17\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-07-31 17:20:17'),
(3, 4, 'insert', 'library_loans', 'loans', 2, NULL, '{\"loan_code\":\"PJM-20260801-003324-BFBY\",\"member_id\":1,\"asset_ids\":[1,2],\"due_date\":\"2026-08-01\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-07-31 17:33:24'),
(4, 4, 'update', 'library_returns', 'loan_items', 2, '{\"return_status\":\"borrowed\",\"asset_status\":\"borrowed\",\"fine_amount\":0}', '{\"return_status\":\"returned\",\"condition_in\":\"good\",\"days_late\":0,\"fine_amount\":0,\"returned_at\":\"2026-08-01 00:35:23\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-07-31 17:35:23'),
(5, 4, 'update', 'library_returns', 'loan_items', 3, '{\"return_status\":\"borrowed\",\"asset_status\":\"borrowed\",\"fine_amount\":0}', '{\"return_status\":\"returned\",\"condition_in\":\"good\",\"days_late\":0,\"fine_amount\":0,\"returned_at\":\"2026-08-01 00:35:36\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-07-31 17:35:36'),
(6, 4, 'insert', 'library_reservations', 'reservations', 1, NULL, '{\"reservation_code\":\"RSV-20260801-005716-SN3U\",\"member_id\":1,\"item_id\":1,\"queue_number\":1,\"status\":\"ready\",\"expires_at\":\"2026-08-03 00:57:16\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-07-31 17:57:16'),
(7, 2, 'update', 'system_settings', 'system_settings', NULL, '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"IP\",\"institution.address\":\"Alamat instansi belum diatur.\",\"institution.email\":\"\",\"institution.name\":\"Rius Library\",\"institution.phone\":\"\",\"inventory.asset_code_separator\":\"-\",\"library.default_loan_days\":\"7\",\"library.fine_per_day\":\"1000\",\"library.max_active_loans\":\"3\",\"library.max_active_reservations\":\"3\",\"library.reservation_hold_days\":\"2\"}', '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SD\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.address\":\"Alamat instansi belum diatur.\",\"institution.phone\":\"\",\"institution.email\":\"\",\"library.default_loan_days\":\"7\",\"library.max_active_loans\":\"3\",\"library.fine_per_day\":\"1000\",\"library.reservation_hold_days\":\"2\",\"library.max_active_reservations\":\"3\",\"inventory.asset_code_separator\":\"-\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-07-31 18:55:57'),
(8, 2, 'login', 'authentication', 'users', 2, NULL, '{\"status\":\"success\",\"roles\":[\"SUPER_ADMIN\"]}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 02:02:53'),
(9, 2, 'logout', 'authentication', 'users', 2, NULL, '{\"status\":\"success\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 02:04:01'),
(10, 3, 'login', 'authentication', 'users', 3, NULL, '{\"status\":\"success\",\"roles\":[\"INVENTORY_ADMIN\"]}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 02:04:29'),
(11, 3, 'logout', 'authentication', 'users', 3, NULL, '{\"status\":\"success\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 02:28:09'),
(12, 2, 'login', 'authentication', 'users', 2, NULL, '{\"status\":\"success\",\"roles\":[\"SUPER_ADMIN\"]}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 02:28:31'),
(13, 2, 'update', 'system_settings', 'system_settings', NULL, '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SD\",\"institution.address\":\"Alamat instansi belum diatur.\",\"institution.email\":\"\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.phone\":\"\",\"inventory.asset_code_separator\":\"-\",\"library.default_loan_days\":\"7\",\"library.fine_per_day\":\"1000\",\"library.max_active_loans\":\"3\",\"library.max_active_reservations\":\"3\",\"library.reservation_hold_days\":\"2\"}', '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SIP\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.address\":\"Alamat instansi belum diatur.\",\"institution.phone\":\"\",\"institution.email\":\"\",\"library.default_loan_days\":\"7\",\"library.max_active_loans\":\"3\",\"library.fine_per_day\":\"1000\",\"library.reservation_hold_days\":\"2\",\"library.max_active_reservations\":\"3\",\"inventory.asset_code_separator\":\"-\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 02:33:46'),
(14, 2, 'insert', 'database_backup', 'database', NULL, NULL, '{\"filename\":\"backup_db_perpustakaan_inventaris_20260801_093914.sql\",\"size\":70196}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 02:39:14'),
(15, 2, 'logout', 'authentication', 'users', 2, NULL, '{\"status\":\"success\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 04:32:24'),
(16, 2, 'login', 'authentication', 'users', 2, NULL, '{\"status\":\"success\",\"roles\":[\"SUPER_ADMIN\"]}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:19:36'),
(17, 2, 'update', 'system_settings', 'system_settings', NULL, '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SIP\",\"institution.address\":\"Alamat instansi belum diatur.\",\"institution.email\":\"\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.phone\":\"\",\"inventory.asset_code_separator\":\"-\",\"library.default_loan_days\":\"7\",\"library.fine_per_day\":\"1000\",\"library.loan_request_hold_days\":\"2\",\"library.max_active_loans\":\"3\",\"library.max_active_reservations\":\"3\",\"library.reservation_hold_days\":\"2\",\"portal.about_content\":\"Perpustakaan menyediakan koleksi belajar, layanan sirkulasi, dan informasi inventaris yang dapat diakses secara transparan.\",\"portal.about_title\":\"Tentang Perpustakaan\",\"portal.about_video_url\":\"\",\"portal.contact_intro\":\"Hubungi pengelola perpustakaan untuk pertanyaan layanan, koleksi, atau akun anggota.\",\"portal.hero_subtitle\":\"Temukan koleksi, ajukan peminjaman, pantau pengembalian, dan akses informasi inventaris dari satu tempat.\",\"portal.hero_title\":\"Perpustakaan yang dekat dengan siswa\",\"portal.opening_hours\":\"Senin–Jumat, 07.30–15.30\"}', '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SIP\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.address\":\"SDN Mekarsari 08,Jl. Mesjid Darussalam 03, Desa Mekarsari, Kecamatan Tambun Selatan, Kabupaten Bekasi, Jawa Barat, 17510\",\"institution.phone\":\"082277625541\",\"institution.email\":\"sdnegerimekarsari08@gmail.com\",\"library.default_loan_days\":\"7\",\"library.max_active_loans\":\"3\",\"library.fine_per_day\":\"1000\",\"library.reservation_hold_days\":\"2\",\"library.max_active_reservations\":\"3\",\"inventory.asset_code_separator\":\"-\",\"portal.hero_title\":\"Perpustakaan yang dekat dengan siswa\",\"portal.hero_subtitle\":\"Temukan koleksi, ajukan peminjaman, pantau pengembalian, dan akses informasi inventaris dari satu tempat.\",\"portal.about_title\":\"Tentang Perpustakaan\",\"portal.about_content\":\"Perpustakaan menyediakan koleksi belajar, layanan sirkulasi, dan informasi inventaris yang dapat diakses secara transparan.\",\"portal.about_video_url\":\"\",\"portal.contact_intro\":\"Hubungi pengelola perpustakaan untuk pertanyaan layanan, koleksi, atau akun anggota.\",\"portal.opening_hours\":\"Senin–Jumat, 07.30–15.30\",\"library.loan_request_hold_days\":\"2\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:22:44'),
(18, 2, 'update', 'system_settings', 'system_settings', NULL, '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SIP\",\"institution.address\":\"SDN Mekarsari 08,Jl. Mesjid Darussalam 03, Desa Mekarsari, Kecamatan Tambun Selatan, Kabupaten Bekasi, Jawa Barat, 17510\",\"institution.email\":\"sdnegerimekarsari08@gmail.com\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.phone\":\"082277625541\",\"inventory.asset_code_separator\":\"-\",\"library.default_loan_days\":\"7\",\"library.fine_per_day\":\"1000\",\"library.loan_request_hold_days\":\"2\",\"library.max_active_loans\":\"3\",\"library.max_active_reservations\":\"3\",\"library.reservation_hold_days\":\"2\",\"portal.about_content\":\"Perpustakaan menyediakan koleksi belajar, layanan sirkulasi, dan informasi inventaris yang dapat diakses secara transparan.\",\"portal.about_title\":\"Tentang Perpustakaan\",\"portal.about_video_url\":\"\",\"portal.contact_intro\":\"Hubungi pengelola perpustakaan untuk pertanyaan layanan, koleksi, atau akun anggota.\",\"portal.hero_subtitle\":\"Temukan koleksi, ajukan peminjaman, pantau pengembalian, dan akses informasi inventaris dari satu tempat.\",\"portal.hero_title\":\"Perpustakaan yang dekat dengan siswa\",\"portal.opening_hours\":\"Senin–Jumat, 07.30–15.30\"}', '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SIP\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.address\":\"SDN Mekarsari 08,\\r\\nJl. Mesjid Darussalam 03,\\r\\nDesa Mekarsari,\\r\\nKecamatan Tambun Selatan,\\r\\nKabupaten Bekasi,\\r\\nJawa Barat,\\r\\n17510\",\"institution.phone\":\"082277625541\",\"institution.email\":\"sdnegerimekarsari08@gmail.com\",\"library.default_loan_days\":\"7\",\"library.max_active_loans\":\"3\",\"library.fine_per_day\":\"1000\",\"library.reservation_hold_days\":\"2\",\"library.max_active_reservations\":\"3\",\"inventory.asset_code_separator\":\"-\",\"portal.hero_title\":\"Perpustakaan yang dekat dengan siswa\",\"portal.hero_subtitle\":\"Temukan koleksi, ajukan peminjaman, pantau pengembalian, dan akses informasi inventaris dari satu tempat.\",\"portal.about_title\":\"Tentang Perpustakaan\",\"portal.about_content\":\"Perpustakaan menyediakan koleksi belajar, layanan sirkulasi, dan informasi inventaris yang dapat diakses secara transparan.\",\"portal.about_video_url\":\"\",\"portal.contact_intro\":\"Hubungi pengelola perpustakaan untuk pertanyaan layanan, koleksi, atau akun anggota.\",\"portal.opening_hours\":\"Senin–Jumat, 07.30–15.30\",\"library.loan_request_hold_days\":\"2\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:23:26'),
(19, 2, 'update', 'system_settings', 'system_settings', NULL, '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SIP\",\"institution.address\":\"SDN Mekarsari 08,\\r\\nJl. Mesjid Darussalam 03,\\r\\nDesa Mekarsari,\\r\\nKecamatan Tambun Selatan,\\r\\nKabupaten Bekasi,\\r\\nJawa Barat,\\r\\n17510\",\"institution.email\":\"sdnegerimekarsari08@gmail.com\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.phone\":\"082277625541\",\"inventory.asset_code_separator\":\"-\",\"library.default_loan_days\":\"7\",\"library.fine_per_day\":\"1000\",\"library.loan_request_hold_days\":\"2\",\"library.max_active_loans\":\"3\",\"library.max_active_reservations\":\"3\",\"library.reservation_hold_days\":\"2\",\"portal.about_content\":\"Perpustakaan menyediakan koleksi belajar, layanan sirkulasi, dan informasi inventaris yang dapat diakses secara transparan.\",\"portal.about_title\":\"Tentang Perpustakaan\",\"portal.about_video_url\":\"\",\"portal.contact_intro\":\"Hubungi pengelola perpustakaan untuk pertanyaan layanan, koleksi, atau akun anggota.\",\"portal.hero_subtitle\":\"Temukan koleksi, ajukan peminjaman, pantau pengembalian, dan akses informasi inventaris dari satu tempat.\",\"portal.hero_title\":\"Perpustakaan yang dekat dengan siswa\",\"portal.opening_hours\":\"Senin–Jumat, 07.30–15.30\"}', '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SIP\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.address\":\"SDN Mekarsari 08, Jl. Mesjid Darussalam 03, Desa Mekarsari, Kecamatan Tambun Selatan, Kabupaten Bekasi, Jawa Barat, 17510\",\"institution.phone\":\"082277625541\",\"institution.email\":\"sdnegerimekarsari08@gmail.com\",\"library.default_loan_days\":\"7\",\"library.max_active_loans\":\"3\",\"library.fine_per_day\":\"1000\",\"library.reservation_hold_days\":\"2\",\"library.max_active_reservations\":\"3\",\"inventory.asset_code_separator\":\"-\",\"portal.hero_title\":\"Perpustakaan yang dekat dengan siswa\",\"portal.hero_subtitle\":\"Temukan koleksi, ajukan peminjaman, pantau pengembalian, dan akses informasi inventaris dari satu tempat.\",\"portal.about_title\":\"Tentang Perpustakaan\",\"portal.about_content\":\"Perpustakaan menyediakan koleksi belajar, layanan sirkulasi, dan informasi inventaris yang dapat diakses secara transparan.\",\"portal.about_video_url\":\"\",\"portal.contact_intro\":\"Hubungi pengelola perpustakaan untuk pertanyaan layanan, koleksi, atau akun anggota.\",\"portal.opening_hours\":\"Senin–Jumat, 07.30–15.30\",\"library.loan_request_hold_days\":\"2\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:23:58'),
(20, 2, 'update', 'system_settings', 'system_settings', NULL, '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SIP\",\"institution.address\":\"SDN Mekarsari 08, Jl. Mesjid Darussalam 03, Desa Mekarsari, Kecamatan Tambun Selatan, Kabupaten Bekasi, Jawa Barat, 17510\",\"institution.email\":\"sdnegerimekarsari08@gmail.com\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.phone\":\"082277625541\",\"inventory.asset_code_separator\":\"-\",\"library.default_loan_days\":\"7\",\"library.fine_per_day\":\"1000\",\"library.loan_request_hold_days\":\"2\",\"library.max_active_loans\":\"3\",\"library.max_active_reservations\":\"3\",\"library.reservation_hold_days\":\"2\",\"portal.about_content\":\"Perpustakaan menyediakan koleksi belajar, layanan sirkulasi, dan informasi inventaris yang dapat diakses secara transparan.\",\"portal.about_title\":\"Tentang Perpustakaan\",\"portal.about_video_url\":\"\",\"portal.contact_intro\":\"Hubungi pengelola perpustakaan untuk pertanyaan layanan, koleksi, atau akun anggota.\",\"portal.hero_subtitle\":\"Temukan koleksi, ajukan peminjaman, pantau pengembalian, dan akses informasi inventaris dari satu tempat.\",\"portal.hero_title\":\"Perpustakaan yang dekat dengan siswa\",\"portal.opening_hours\":\"Senin–Jumat, 07.30–15.30\"}', '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SIP\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.address\":\"SDN Mekarsari 08, Jl. Mesjid Darussalam 03, Desa Mekarsari, Kecamatan Tambun Selatan, Kabupaten Bekasi, Jawa Barat, 17510\",\"institution.phone\":\"082277625541\",\"institution.email\":\"sdnegerimekarsari08@gmail.com\",\"library.default_loan_days\":\"7\",\"library.max_active_loans\":\"3\",\"library.fine_per_day\":\"1000\",\"library.reservation_hold_days\":\"2\",\"library.max_active_reservations\":\"3\",\"inventory.asset_code_separator\":\"-\",\"portal.hero_title\":\"Perpustakaan yang dekat dengan siswa\",\"portal.hero_subtitle\":\"Temukan koleksi, ajukan peminjaman, pantau pengembalian, dan akses informasi inventaris dari satu tempat.\",\"portal.about_title\":\"Tentang Perpustakaan\",\"portal.about_content\":\"Perpustakaan menyediakan koleksi belajar, layanan sirkulasi, dan informasi inventaris yang dapat diakses secara transparan.\",\"portal.about_video_url\":\"\",\"portal.contact_intro\":\"Hubungi pengelola perpustakaan untuk pertanyaan layanan, koleksi, atau akun anggota.\",\"portal.opening_hours\":\"Senin–Jumat, 07.30–15.30\",\"library.loan_request_hold_days\":\"2\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:24:27'),
(21, 2, 'update', 'system_settings', 'system_settings', NULL, '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SIP\",\"institution.address\":\"SDN Mekarsari 08, Jl. Mesjid Darussalam 03, Desa Mekarsari, Kecamatan Tambun Selatan, Kabupaten Bekasi, Jawa Barat, 17510\",\"institution.email\":\"sdnegerimekarsari08@gmail.com\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.phone\":\"082277625541\",\"inventory.asset_code_separator\":\"-\",\"library.default_loan_days\":\"7\",\"library.fine_per_day\":\"1000\",\"library.loan_request_hold_days\":\"2\",\"library.max_active_loans\":\"3\",\"library.max_active_reservations\":\"3\",\"library.reservation_hold_days\":\"2\",\"portal.about_content\":\"Perpustakaan menyediakan koleksi belajar, layanan sirkulasi, dan informasi inventaris yang dapat diakses secara transparan.\",\"portal.about_title\":\"Tentang Perpustakaan\",\"portal.about_video_url\":\"\",\"portal.contact_intro\":\"Hubungi pengelola perpustakaan untuk pertanyaan layanan, koleksi, atau akun anggota.\",\"portal.hero_subtitle\":\"Temukan koleksi, ajukan peminjaman, pantau pengembalian, dan akses informasi inventaris dari satu tempat.\",\"portal.hero_title\":\"Perpustakaan yang dekat dengan siswa\",\"portal.opening_hours\":\"Senin–Jumat, 07.30–15.30\"}', '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SIP\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.address\":\"SDN Mekarsari 08, Jl. Mesjid Darussalam 03, Desa Mekarsari, Kecamatan Tambun Selatan, Kabupaten Bekasi, Jawa Barat, 17510\",\"institution.phone\":\"082277625541\",\"institution.email\":\"sdnegerimekarsari08@gmail.com\",\"library.default_loan_days\":\"7\",\"library.max_active_loans\":\"3\",\"library.fine_per_day\":\"1000\",\"library.reservation_hold_days\":\"2\",\"library.max_active_reservations\":\"3\",\"inventory.asset_code_separator\":\"-\",\"portal.hero_title\":\"Perpustakaan yang dekat dengan siswa\",\"portal.hero_subtitle\":\"Temukan koleksi, ajukan peminjaman, pantau pengembalian, dan akses informasi inventaris dari satu tempat.\",\"portal.about_title\":\"Tentang Perpustakaan\",\"portal.about_content\":\"Perpustakaan menyediakan koleksi belajar, layanan sirkulasi, dan informasi inventaris yang dapat diakses secara transparan.\",\"portal.about_video_url\":\"\",\"portal.contact_intro\":\"Hubungi pengelola perpustakaan untuk pertanyaan layanan, koleksi, atau akun anggota.\",\"portal.opening_hours\":\"Senin–Jumat, 07.30–15.30\",\"library.loan_request_hold_days\":\"2\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:24:31'),
(22, 2, 'update', 'system_settings', 'system_settings', NULL, '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SIP\",\"institution.address\":\"SDN Mekarsari 08, Jl. Mesjid Darussalam 03, Desa Mekarsari, Kecamatan Tambun Selatan, Kabupaten Bekasi, Jawa Barat, 17510\",\"institution.email\":\"sdnegerimekarsari08@gmail.com\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.phone\":\"082277625541\",\"inventory.asset_code_separator\":\"-\",\"library.default_loan_days\":\"7\",\"library.fine_per_day\":\"1000\",\"library.loan_request_hold_days\":\"2\",\"library.max_active_loans\":\"3\",\"library.max_active_reservations\":\"3\",\"library.reservation_hold_days\":\"2\",\"portal.about_content\":\"Perpustakaan menyediakan koleksi belajar, layanan sirkulasi, dan informasi inventaris yang dapat diakses secara transparan.\",\"portal.about_title\":\"Tentang Perpustakaan\",\"portal.about_video_url\":\"\",\"portal.contact_intro\":\"Hubungi pengelola perpustakaan untuk pertanyaan layanan, koleksi, atau akun anggota.\",\"portal.hero_subtitle\":\"Temukan koleksi, ajukan peminjaman, pantau pengembalian, dan akses informasi inventaris dari satu tempat.\",\"portal.hero_title\":\"Perpustakaan yang dekat dengan siswa\",\"portal.opening_hours\":\"Senin–Jumat, 07.30–15.30\"}', '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SIP\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.address\":\"SDN Mekarsari 08, Jl. Mesjid Darussalam 03, Desa Mekarsari, Kecamatan Tambun Selatan, Kabupaten Bekasi, Jawa Barat, 17510\",\"institution.phone\":\"082277625541\",\"institution.email\":\"sdnegerimekarsari08@gmail.com\",\"library.default_loan_days\":\"7\",\"library.max_active_loans\":\"3\",\"library.fine_per_day\":\"1000\",\"library.reservation_hold_days\":\"2\",\"library.max_active_reservations\":\"3\",\"inventory.asset_code_separator\":\"-\",\"portal.hero_title\":\"Perpustakaan yang dekat dengan siswa\",\"portal.hero_subtitle\":\"Temukan koleksi, ajukan peminjaman, pantau pengembalian, dan akses informasi inventaris dari satu tempat.\",\"portal.about_title\":\"Tentang Perpustakaan\",\"portal.about_content\":\"Perpustakaan menyediakan koleksi belajar, layanan sirkulasi, dan informasi inventaris yang dapat diakses secara transparan.\",\"portal.about_video_url\":\"https:\\/\\/youtu.be\\/wEeExOHXoas?si=Q6x99gkcVZqGFvFP\",\"portal.contact_intro\":\"Hubungi pengelola perpustakaan untuk pertanyaan layanan, koleksi, atau akun anggota.\",\"portal.opening_hours\":\"Senin–Jumat, 07.30–15.30\",\"library.loan_request_hold_days\":\"2\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:25:46'),
(23, 2, 'update', 'system_settings', 'system_settings', NULL, '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SIP\",\"institution.address\":\"SDN Mekarsari 08, Jl. Mesjid Darussalam 03, Desa Mekarsari, Kecamatan Tambun Selatan, Kabupaten Bekasi, Jawa Barat, 17510\",\"institution.email\":\"sdnegerimekarsari08@gmail.com\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.phone\":\"082277625541\",\"inventory.asset_code_separator\":\"-\",\"library.default_loan_days\":\"7\",\"library.fine_per_day\":\"1000\",\"library.loan_request_hold_days\":\"2\",\"library.max_active_loans\":\"3\",\"library.max_active_reservations\":\"3\",\"library.reservation_hold_days\":\"2\",\"portal.about_content\":\"Perpustakaan menyediakan koleksi belajar, layanan sirkulasi, dan informasi inventaris yang dapat diakses secara transparan.\",\"portal.about_title\":\"Tentang Perpustakaan\",\"portal.about_video_url\":\"https:\\/\\/youtu.be\\/wEeExOHXoas?si=Q6x99gkcVZqGFvFP\",\"portal.contact_intro\":\"Hubungi pengelola perpustakaan untuk pertanyaan layanan, koleksi, atau akun anggota.\",\"portal.hero_subtitle\":\"Temukan koleksi, ajukan peminjaman, pantau pengembalian, dan akses informasi inventaris dari satu tempat.\",\"portal.hero_title\":\"Perpustakaan yang dekat dengan siswa\",\"portal.opening_hours\":\"Senin–Jumat, 07.30–15.30\"}', '{\"application.name\":\"Sistem Inventaris dan Perpustakaan\",\"application.short_name\":\"SIP\",\"institution.name\":\"SDN Mekarsari 08\",\"institution.address\":\"SDN Mekarsari 08, Jl. Mesjid Darussalam 03, Desa Mekarsari, Kecamatan Tambun Selatan, Kabupaten Bekasi, Jawa Barat, 17510\",\"institution.phone\":\"082277625541\",\"institution.email\":\"sdnegerimekarsari08@gmail.com\",\"library.default_loan_days\":\"7\",\"library.max_active_loans\":\"3\",\"library.fine_per_day\":\"1000\",\"library.reservation_hold_days\":\"2\",\"library.max_active_reservations\":\"3\",\"inventory.asset_code_separator\":\"-\",\"portal.hero_title\":\"Perpustakaan yang dekat dengan siswa\",\"portal.hero_subtitle\":\"Temukan koleksi, ajukan peminjaman, pantau pengembalian, dan akses informasi inventaris dari satu tempat.\",\"portal.about_title\":\"Tentang Perpustakaan\",\"portal.about_content\":\"Perpustakaan menyediakan koleksi belajar, layanan sirkulasi, dan informasi inventaris yang dapat diakses secara transparan.\",\"portal.about_video_url\":\"https:\\/\\/www.youtube.com\\/watch?v=wEeExOHXoas\",\"portal.contact_intro\":\"Hubungi pengelola perpustakaan untuk pertanyaan layanan, koleksi, atau akun anggota.\",\"portal.opening_hours\":\"Senin–Jumat, 07.30–15.30\",\"library.loan_request_hold_days\":\"2\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:26:12'),
(24, NULL, 'insert', 'library_members', 'members', 2, NULL, '{\"member_code\":\"AGT-202608-0001\",\"user_id\":5,\"member_name\":\"Serius ndruru\",\"member_type\":\"student\",\"identity_number\":\"12345678\",\"department\":\"Kelas 2\",\"phone\":\"082277625541\",\"email\":\"seriusndruru099@gmail.com\",\"address\":\"Pasir Konci, Desa Pasirsari, Kecamatan Cikarang Selatan, Kabupaten Bekasi, Provinsi Jawa Barat.\",\"join_date\":\"2026-08-01 00:00:00\",\"expiry_date\":\"2027-08-01 00:00:00\",\"status\":\"active\",\"created_by\":null,\"updated_at\":\"2026-08-01 12:31:09\",\"created_at\":\"2026-08-01 12:31:09\",\"id\":2}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:31:09'),
(25, 5, 'insert', 'member_self_registration', 'users', 5, NULL, '{\"username\":\"riusndruru\",\"email\":\"seriusndruru099@gmail.com\",\"role\":\"MEMBER\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:31:09'),
(26, 5, 'insert', 'library_loan_requests', 'loan_requests', 1, NULL, '{\"request_code\":\"REQ-20260801-123158-JUAF\",\"member_id\":2,\"status\":\"submitted\",\"requested_at\":\"2026-08-01 12:31:58\",\"member_notes\":null,\"updated_at\":\"2026-08-01 12:31:58\",\"created_at\":\"2026-08-01 12:31:58\",\"id\":1}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:31:58'),
(27, 2, 'logout', 'authentication', 'users', 2, NULL, '{\"status\":\"success\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:33:03'),
(28, 4, 'login', 'authentication', 'users', 4, NULL, '{\"status\":\"success\",\"roles\":[\"LIBRARY_ADMIN\"]}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:33:14'),
(29, 4, 'update', 'inventory_assets', 'assets', 1, '{\"asset_status\":\"available\"}', '{\"asset_status\":\"reserved\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:33:36'),
(30, 4, 'update', 'library_loan_requests', 'loan_requests', 1, '{\"status\":\"submitted\",\"approved_at\":null,\"processed_by\":null}', '{\"status\":\"approved\",\"approved_at\":\"2026-08-01 12:33:36\",\"processed_by\":4}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:33:36'),
(31, 4, 'update', 'library_loan_requests', 'loan_requests', 1, '{\"status\":\"approved\",\"ready_at\":null,\"pickup_expires_at\":null}', '{\"status\":\"ready\",\"ready_at\":\"2026-08-01 12:33:49\",\"pickup_expires_at\":\"2026-08-03 12:33:49\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:33:49'),
(32, 4, 'update', 'inventory_assets', 'assets', 1, '{\"asset_status\":\"reserved\"}', '{\"asset_status\":\"available\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:34:14'),
(33, 4, 'update', 'library_loan_requests', 'loan_requests', 1, '{\"status\":\"ready\",\"collected_at\":null}', '{\"status\":\"collected\",\"collected_at\":\"2026-08-01 12:34:14\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:34:14'),
(34, 4, 'update', 'library_returns', 'loan_items', 4, '{\"return_status\":\"borrowed\",\"asset_status\":\"borrowed\",\"fine_amount\":0}', '{\"return_status\":\"returned\",\"condition_in\":\"good\",\"days_late\":0,\"fine_amount\":0,\"returned_at\":\"2026-08-01 12:34:50\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:34:50'),
(35, 5, 'insert', 'library_loan_requests', 'loan_requests', 2, NULL, '{\"request_code\":\"REQ-20260801-123548-2XRE\",\"member_id\":2,\"status\":\"submitted\",\"requested_at\":\"2026-08-01 12:35:48\",\"member_notes\":null,\"updated_at\":\"2026-08-01 12:35:48\",\"created_at\":\"2026-08-01 12:35:48\",\"id\":2}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:35:48'),
(36, 4, 'update', 'inventory_assets', 'assets', 1, '{\"asset_status\":\"available\"}', '{\"asset_status\":\"reserved\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:36:13'),
(37, 4, 'update', 'library_loan_requests', 'loan_requests', 2, '{\"status\":\"submitted\",\"approved_at\":null,\"processed_by\":null}', '{\"status\":\"approved\",\"approved_at\":\"2026-08-01 12:36:13\",\"processed_by\":4}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:36:13'),
(38, 4, 'update', 'library_loan_requests', 'loan_requests', 2, '{\"status\":\"approved\",\"ready_at\":null,\"pickup_expires_at\":null}', '{\"status\":\"ready\",\"ready_at\":\"2026-08-01 12:36:15\",\"pickup_expires_at\":\"2026-08-03 12:36:15\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:36:15'),
(39, 4, 'update', 'inventory_assets', 'assets', 1, '{\"asset_status\":\"reserved\"}', '{\"asset_status\":\"available\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:36:17'),
(40, 4, 'update', 'library_loan_requests', 'loan_requests', 2, '{\"status\":\"ready\",\"collected_at\":null}', '{\"status\":\"collected\",\"collected_at\":\"2026-08-01 12:36:17\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:36:17'),
(41, 4, 'logout', 'authentication', 'users', 4, NULL, '{\"status\":\"success\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:38:08'),
(42, 2, 'login', 'authentication', 'users', 2, NULL, '{\"status\":\"success\",\"roles\":[\"SUPER_ADMIN\"]}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:38:14'),
(43, 2, 'update', 'library_members', 'members', 2, '{\"member_name\":\"Serius ndruru\"}', '{\"member_name\":\"Serius Ndruru\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 05:38:39'),
(44, 2, 'update', 'library_members', 'members', 2, '{\"join_date\":\"2026-07-31T17:00:00.000000Z\",\"expiry_date\":\"2027-07-31T17:00:00.000000Z\"}', '{\"join_date\":\"2024-07-15 00:00:00\",\"expiry_date\":\"2030-04-19 00:00:00\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 06:30:42'),
(45, 2, 'update', 'library_members', 'members', 2, '{\"member_name\":\"Serius Ndruru\"}', '{\"member_name\":\"serius ndruru\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 06:56:48'),
(46, 2, 'update', 'library_members', 'members', 2, '{\"member_name\":\"serius ndruru\"}', '{\"member_name\":\"Serius Ndruru\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 06:57:23'),
(47, 2, 'logout', 'authentication', 'users', 2, NULL, '{\"status\":\"success\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:19:56'),
(48, 3, 'login', 'authentication', 'users', 3, NULL, '{\"status\":\"success\",\"roles\":[\"INVENTORY_ADMIN\"]}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:20:06'),
(49, 5, 'insert', 'public_damage_reports', 'public_damage_reports', 1, NULL, '{\"report_code\":\"RUS-20260801-142646-8IIV\",\"item_id\":1,\"asset_id\":\"1\",\"location_id\":3,\"reporter_name\":\"Rius\",\"reporter_contact\":\"082277625541\",\"issue_description\":\"bukunya sobek bagian halaman 230\",\"photo_path\":\"damage-reports\\/cSL1TKMEDTLjsoSfKevO8aAQdMBhTRFfwViUye8g.png\",\"status\":\"submitted\",\"updated_at\":\"2026-08-01 14:26:46\",\"created_at\":\"2026-08-01 14:26:46\",\"id\":1}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:26:46'),
(50, 3, 'update', 'public_damage_reports', 'public_damage_reports', 1, '{\"status\":\"submitted\",\"handled_by\":null}', '{\"status\":\"reviewed\",\"handled_by\":3}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:32:09'),
(51, 3, 'update', 'public_damage_reports', 'public_damage_reports', 1, '{\"status\":\"reviewed\"}', '{\"status\":\"in_progress\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:32:23'),
(52, 3, 'update', 'public_damage_reports', 'public_damage_reports', 1, '{\"status\":\"in_progress\"}', '{\"status\":\"resolved\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:32:33'),
(53, 3, 'update', 'public_damage_reports', 'public_damage_reports', 1, '{\"status\":\"resolved\"}', '{\"status\":\"submitted\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:34:16'),
(54, 3, 'update', 'public_damage_reports', 'public_damage_reports', 1, '{\"status\":\"submitted\"}', '{\"status\":\"reviewed\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:34:31'),
(55, 3, 'update', 'public_damage_reports', 'public_damage_reports', 1, '{\"status\":\"reviewed\"}', '{\"status\":\"in_progress\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:34:41'),
(56, 3, 'update', 'public_damage_reports', 'public_damage_reports', 1, '{\"status\":\"in_progress\"}', '{\"status\":\"submitted\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:34:50'),
(57, 5, 'insert', 'library_loan_requests', 'loan_requests', 3, NULL, '{\"request_code\":\"REQ-20260801-143621-NHPC\",\"member_id\":2,\"status\":\"submitted\",\"requested_at\":\"2026-08-01 14:36:21\",\"member_notes\":null,\"updated_at\":\"2026-08-01 14:36:21\",\"created_at\":\"2026-08-01 14:36:21\",\"id\":3}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:36:21'),
(58, 3, 'logout', 'authentication', 'users', 3, NULL, '{\"status\":\"success\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:36:28'),
(59, 4, 'login', 'authentication', 'users', 4, NULL, '{\"status\":\"success\",\"roles\":[\"LIBRARY_ADMIN\"]}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:36:36'),
(60, 4, 'logout', 'authentication', 'users', 4, NULL, '{\"status\":\"success\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:50:43'),
(61, 2, 'login', 'authentication', 'users', 2, NULL, '{\"status\":\"success\",\"roles\":[\"SUPER_ADMIN\"]}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:50:54'),
(62, 2, 'logout', 'authentication', 'users', 2, NULL, '{\"status\":\"success\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:51:35'),
(63, 3, 'login', 'authentication', 'users', 3, NULL, '{\"status\":\"success\",\"roles\":[\"INVENTORY_ADMIN\"]}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 07:51:47'),
(64, 3, 'logout', 'authentication', 'users', 3, NULL, '{\"status\":\"success\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 08:18:02'),
(65, 2, 'login', 'authentication', 'users', 2, NULL, '{\"status\":\"success\",\"roles\":[\"SUPER_ADMIN\"]}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 08:18:10'),
(66, 2, 'login', 'authentication', 'users', 2, NULL, '{\"status\":\"success\",\"roles\":[\"SUPER_ADMIN\"]}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 12:24:57'),
(67, 2, 'login', 'authentication', 'users', 2, NULL, '{\"status\":\"success\",\"roles\":[\"SUPER_ADMIN\"]}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 13:04:43'),
(68, 2, 'logout', 'authentication', 'users', 2, NULL, '{\"status\":\"success\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 13:11:00'),
(69, 2, 'login', 'authentication', 'users', 2, NULL, '{\"status\":\"success\",\"roles\":[\"SUPER_ADMIN\"]}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 14:00:27'),
(70, 2, 'update', 'library_members', 'members', 1, '{\"department\":\"Teknik Informatika\"}', '{\"department\":\"Kelas 1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-01 14:09:52'),
(71, 2, 'login', 'authentication', 'users', 2, NULL, '{\"status\":\"success\",\"roles\":[\"SUPER_ADMIN\"]}', '157.66.3.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 07:37:22'),
(72, 4, 'login', 'authentication', 'users', 4, NULL, '{\"status\":\"success\",\"roles\":[\"LIBRARY_ADMIN\"]}', '157.66.3.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 07:41:28'),
(73, 4, 'update', 'inventory_assets', 'assets', 2, '{\"asset_status\":\"available\"}', '{\"asset_status\":\"reserved\"}', '157.66.3.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 07:41:46'),
(74, 4, 'update', 'library_loan_requests', 'loan_requests', 3, '{\"status\":\"submitted\",\"approved_at\":null,\"processed_by\":null}', '{\"status\":\"approved\",\"approved_at\":\"2026-08-03 07:41:47\",\"processed_by\":4}', '157.66.3.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 07:41:47'),
(75, 4, 'update', 'library_loan_requests', 'loan_requests', 3, '{\"status\":\"approved\",\"ready_at\":null,\"pickup_expires_at\":null}', '{\"status\":\"ready\",\"ready_at\":\"2026-08-03 07:41:58\",\"pickup_expires_at\":\"2026-08-05 07:41:58\"}', '157.66.3.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 07:41:58'),
(76, 4, 'update', 'inventory_assets', 'assets', 2, '{\"asset_status\":\"reserved\"}', '{\"asset_status\":\"available\"}', '157.66.3.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 07:42:02'),
(77, 4, 'update', 'library_loan_requests', 'loan_requests', 3, '{\"status\":\"ready\",\"collected_at\":null}', '{\"status\":\"collected\",\"collected_at\":\"2026-08-03 07:42:02\"}', '157.66.3.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 07:42:02'),
(78, 4, 'update', 'library_returns', 'loan_items', 6, '{\"return_status\":\"borrowed\",\"asset_status\":\"borrowed\",\"fine_amount\":0}', '{\"return_status\":\"returned\",\"condition_in\":\"good\",\"days_late\":0,\"fine_amount\":0,\"returned_at\":\"2026-08-03 07:42:20\"}', '157.66.3.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 07:42:20'),
(79, 4, 'update', 'library_returns', 'loan_items', 5, '{\"return_status\":\"borrowed\",\"asset_status\":\"borrowed\",\"fine_amount\":0}', '{\"return_status\":\"returned\",\"condition_in\":\"good\",\"days_late\":0,\"fine_amount\":0,\"returned_at\":\"2026-08-03 07:42:42\"}', '157.66.3.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 07:42:42'),
(80, 3, 'login', 'authentication', 'users', 3, NULL, '{\"status\":\"success\",\"roles\":[\"INVENTORY_ADMIN\"]}', '157.66.3.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 07:44:36'),
(81, 3, 'update', 'public_damage_reports', 'public_damage_reports', 1, '{\"status\":\"submitted\"}', '{\"status\":\"resolved\"}', '157.66.3.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 07:44:50'),
(82, 2, 'login', 'authentication', 'users', 2, NULL, '{\"status\":\"success\",\"roles\":[\"SUPER_ADMIN\"]}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 11:36:57'),
(83, 2, 'update', 'library_members', 'members', 1, '{\"status\":\"active\"}', '{\"status\":\"inactive\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 11:38:05'),
(84, 2, 'insert', 'inventory_disposal', 'disposals', 1, NULL, '{\"disposal_code\":\"DSP-20260803-0001\",\"asset_id\":1,\"reason\":\"hapus\",\"proposed_at\":\"2026-08-03 11:58:00\",\"status\":\"proposed\",\"proposed_by\":2,\"notes\":null,\"updated_at\":\"2026-08-03 12:06:09\",\"created_at\":\"2026-08-03 12:06:09\",\"id\":1}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 12:06:09'),
(85, 2, 'update', 'inventory_disposal', 'disposals', 1, '{\"approved_at\":null,\"status\":\"proposed\",\"approved_by\":null}', '{\"approved_at\":\"2026-08-03 12:06:22\",\"status\":\"approved\",\"approved_by\":2}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 12:06:22'),
(86, 2, 'update', 'inventory_assets', 'assets', 1, '{\"asset_status\":\"available\",\"current_shelf_id\":1,\"updated_by\":4}', '{\"asset_status\":\"disposed\",\"current_shelf_id\":null,\"updated_by\":2}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 12:06:49'),
(87, 2, 'update', 'inventory_disposal', 'disposals', 1, '{\"disposed_at\":null,\"disposal_method\":null,\"status\":\"approved\",\"notes\":null}', '{\"disposed_at\":\"2026-08-03 12:06:00\",\"disposal_method\":\"destroyed\",\"status\":\"completed\",\"notes\":\"\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 12:06:49'),
(88, 2, 'insert', 'inventory_disposal', 'disposals', 2, NULL, '{\"disposal_code\":\"DSP-20260803-0002\",\"asset_id\":2,\"reason\":\"Hapus\",\"proposed_at\":\"2026-08-03 12:07:00\",\"status\":\"proposed\",\"proposed_by\":2,\"notes\":null,\"updated_at\":\"2026-08-03 12:07:34\",\"created_at\":\"2026-08-03 12:07:34\",\"id\":2}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 12:07:34'),
(89, 2, 'update', 'inventory_disposal', 'disposals', 2, '{\"approved_at\":null,\"status\":\"proposed\",\"approved_by\":null}', '{\"approved_at\":\"2026-08-03 12:07:39\",\"status\":\"approved\",\"approved_by\":2}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 12:07:39'),
(90, 2, 'update', 'inventory_assets', 'assets', 2, '{\"asset_status\":\"available\",\"current_shelf_id\":1,\"updated_by\":4}', '{\"asset_status\":\"disposed\",\"current_shelf_id\":null,\"updated_by\":2}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 12:07:48'),
(91, 2, 'update', 'inventory_disposal', 'disposals', 2, '{\"disposed_at\":null,\"disposal_method\":null,\"status\":\"approved\",\"notes\":null}', '{\"disposed_at\":\"2026-08-03 12:07:00\",\"disposal_method\":\"destroyed\",\"status\":\"completed\",\"notes\":\"\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 12:07:48'),
(92, 2, 'insert', 'inventory_disposal', 'disposals', 3, NULL, '{\"disposal_code\":\"DSP-20260803-0003\",\"asset_id\":3,\"reason\":\"Hapus\",\"proposed_at\":\"2026-08-03 12:08:00\",\"status\":\"proposed\",\"proposed_by\":2,\"notes\":null,\"updated_at\":\"2026-08-03 12:08:27\",\"created_at\":\"2026-08-03 12:08:27\",\"id\":3}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 12:08:27'),
(93, 2, 'update', 'inventory_disposal', 'disposals', 3, '{\"approved_at\":null,\"status\":\"proposed\",\"approved_by\":null}', '{\"approved_at\":\"2026-08-03 12:08:29\",\"status\":\"approved\",\"approved_by\":2}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 12:08:29'),
(94, 2, 'update', 'inventory_assets', 'assets', 3, '{\"asset_status\":\"available\",\"current_shelf_id\":1,\"updated_by\":4}', '{\"asset_status\":\"disposed\",\"current_shelf_id\":null,\"updated_by\":2}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 12:08:55'),
(95, 2, 'update', 'inventory_disposal', 'disposals', 3, '{\"disposed_at\":null,\"disposal_method\":null,\"status\":\"approved\",\"notes\":null}', '{\"disposed_at\":\"2026-08-03 12:08:00\",\"disposal_method\":\"destroyed\",\"status\":\"completed\",\"notes\":\"\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 12:08:55'),
(96, 2, 'update', 'inventory_items', 'items', 1, '{\"status\":\"active\"}', '{\"status\":\"inactive\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 12:10:33');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `module_name`, `table_name`, `record_id`, `old_data`, `new_data`, `ip_address`, `user_agent`, `created_at`) VALUES
(97, 2, 'login', 'authentication', 'users', 2, NULL, '{\"status\":\"success\",\"roles\":[\"SUPER_ADMIN\"]}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 13:03:21'),
(98, 2, 'update', 'inventory_items', 'items', 1, '{\"status\":\"inactive\"}', '{\"status\":\"active\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 13:03:56'),
(99, 2, 'update', 'inventory_items', 'items', 1, '{\"status\":\"active\"}', '{\"status\":\"inactive\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 13:04:08'),
(100, 3, 'login', 'authentication', 'users', 3, NULL, '{\"status\":\"success\",\"roles\":[\"INVENTORY_ADMIN\"]}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 13:15:27'),
(101, 3, 'update', 'master_suppliers', 'suppliers', 1, '{\"status\":\"active\"}', '{\"status\":\"inactive\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 13:16:46'),
(102, 3, 'update', 'master_suppliers', 'suppliers', 1, '{\"status\":\"inactive\"}', '{\"status\":\"active\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 13:17:01'),
(103, 3, 'update', 'master_categories', 'categories', 1, '{\"status\":\"active\"}', '{\"status\":\"inactive\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 13:17:33'),
(104, 2, 'logout', 'authentication', 'users', 2, NULL, '{\"status\":\"success\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 13:29:46'),
(105, 3, 'login', 'authentication', 'users', 3, NULL, '{\"status\":\"success\",\"roles\":[\"INVENTORY_ADMIN\"]}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 13:29:59'),
(106, 3, 'update', 'master_suppliers', 'suppliers', 1, '{\"status\":\"active\"}', '{\"status\":\"inactive\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 14:02:08'),
(107, 3, 'logout', 'authentication', 'users', 3, NULL, '{\"status\":\"success\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 14:15:25'),
(108, 2, 'login', 'authentication', 'users', 2, NULL, '{\"status\":\"success\",\"roles\":[\"SUPER_ADMIN\"]}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 14:15:36'),
(109, 2, 'update', 'library_shelves', 'library_shelves', 1, '{\"status\":\"active\"}', '{\"status\":\"inactive\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 14:15:50'),
(110, 2, 'update', 'master_locations', 'locations', 3, '{\"status\":\"active\"}', '{\"status\":\"inactive\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 14:16:20'),
(111, 2, 'update', 'master_locations', 'locations', 2, '{\"status\":\"active\"}', '{\"status\":\"inactive\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 14:16:25'),
(112, 2, 'update', 'master_locations', 'locations', 1, '{\"status\":\"active\"}', '{\"status\":\"inactive\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 14:16:28'),
(113, 3, 'login', 'authentication', 'users', 3, NULL, '{\"status\":\"success\",\"roles\":[\"INVENTORY_ADMIN\"]}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 14:19:19'),
(114, 2, 'insert', 'master_locations', 'locations', 4, NULL, '{\"parent_id\":null,\"location_code\":\"GDG-BARANG\",\"location_name\":\"Gudang\",\"location_type\":\"building\",\"description\":null,\"status\":\"active\",\"updated_at\":\"2026-08-03 15:33:14\",\"created_at\":\"2026-08-03 15:33:14\",\"id\":4}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 15:33:14'),
(115, 2, 'update', 'master_locations', 'locations', 4, '{\"location_type\":\"building\"}', '{\"location_type\":\"warehouse\"}', '36.67.243.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-03 15:33:58');

-- --------------------------------------------------------

--
-- Struktur dari tabel `authors`
--

CREATE TABLE `authors` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `author_name` varchar(180) NOT NULL,
  `biography` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `authors`
--

INSERT INTO `authors` (`id`, `author_name`, `biography`, `created_at`, `updated_at`) VALUES
(1, 'Andi Pratama', NULL, '2026-07-31 12:33:55', '2026-07-31 12:33:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `book_authors`
--

CREATE TABLE `book_authors` (
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `author_id` bigint(20) UNSIGNED NOT NULL,
  `author_role` enum('author','editor','translator','illustrator') NOT NULL DEFAULT 'author',
  `author_order` smallint(5) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `book_authors`
--

INSERT INTO `book_authors` (`item_id`, `author_id`, `author_role`, `author_order`) VALUES
(1, 1, 'author', 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `book_details`
--

CREATE TABLE `book_details` (
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `isbn_10` varchar(20) DEFAULT NULL,
  `isbn_13` varchar(20) DEFAULT NULL,
  `publisher_id` bigint(20) UNSIGNED DEFAULT NULL,
  `publication_year` smallint(5) UNSIGNED DEFAULT NULL,
  `grade_level` varchar(20) NOT NULL DEFAULT 'umum',
  `edition` varchar(80) DEFAULT NULL,
  `language` varchar(50) DEFAULT 'Indonesia',
  `page_count` int(10) UNSIGNED DEFAULT NULL,
  `classification_code` varchar(50) DEFAULT NULL,
  `call_number` varchar(80) DEFAULT NULL,
  `cover_path` varchar(255) DEFAULT NULL,
  `catalog_notes` text DEFAULT NULL,
  `completion_status` enum('incomplete','complete','verified') NOT NULL DEFAULT 'incomplete',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `book_details`
--

INSERT INTO `book_details` (`item_id`, `isbn_10`, `isbn_13`, `publisher_id`, `publication_year`, `grade_level`, `edition`, `language`, `page_count`, `classification_code`, `call_number`, `cover_path`, `catalog_notes`, `completion_status`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, NULL, '9786021234567', 1, 2026, 'umum', 'Edisi 1', 'Indonesia', 250, '005.13', '005.13 AND d', NULL, NULL, 'complete', '2026-07-31 12:21:55', 2, '2026-07-31 12:33:55');

--
-- Trigger `book_details`
--
DELIMITER $$
CREATE TRIGGER `trg_book_details_before_insert` BEFORE INSERT ON `book_details` FOR EACH ROW BEGIN
    DECLARE v_item_type VARCHAR(30);

    SELECT item_type INTO v_item_type
    FROM items
    WHERE id = NEW.item_id;

    IF v_item_type IS NULL OR v_item_type <> 'book' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'book_details hanya boleh dibuat untuk item bertipe book.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `category_code` varchar(50) NOT NULL,
  `category_name` varchar(150) NOT NULL,
  `scope` enum('inventory','library','both') NOT NULL DEFAULT 'both',
  `description` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `category_code`, `category_name`, `scope`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, NULL, 'BOOK', 'Buku', 'both', 'Kategori utama untuk buku perpustakaan.', 'inactive', '2026-07-31 10:21:00', '2026-08-03 13:17:33'),
(2, NULL, 'EQUIPMENT', 'Peralatan', 'inventory', 'Peralatan umum.', 'active', '2026-07-31 10:21:00', '2026-07-31 10:21:00'),
(3, NULL, 'ELECTRONIC', 'Elektronik', 'inventory', 'Barang elektronik.', 'active', '2026-07-31 10:21:00', '2026-07-31 10:21:00'),
(4, NULL, 'FURNITURE', 'Furnitur', 'inventory', 'Meja, kursi, lemari, dan furnitur lain.', 'active', '2026-07-31 10:21:00', '2026-07-31 10:21:00'),
(5, NULL, 'CONSUMABLE', 'Barang Habis Pakai', 'inventory', 'Barang yang dicatat berdasarkan jumlah stok.', 'active', '2026-07-31 10:21:00', '2026-07-31 10:21:00'),
(6, NULL, 'OTHER', 'Lainnya', 'both', 'Kategori umum lainnya.', 'active', '2026-07-31 10:21:00', '2026-07-31 10:21:00'),
(7, NULL, 'BUKU', 'Buku', 'both', 'Seluruh koleksi buku', 'active', '2026-07-31 11:36:41', '2026-07-31 11:36:41');

-- --------------------------------------------------------

--
-- Struktur dari tabel `disposals`
--

CREATE TABLE `disposals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `disposal_code` varchar(70) NOT NULL,
  `asset_id` bigint(20) UNSIGNED NOT NULL,
  `reason` text NOT NULL,
  `proposed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `disposed_at` datetime DEFAULT NULL,
  `disposal_method` enum('destroyed','sold','donated','returned','other') DEFAULT NULL,
  `status` enum('proposed','approved','rejected','completed') NOT NULL DEFAULT 'proposed',
  `proposed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `disposals`
--

INSERT INTO `disposals` (`id`, `disposal_code`, `asset_id`, `reason`, `proposed_at`, `approved_at`, `disposed_at`, `disposal_method`, `status`, `proposed_by`, `approved_by`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'DSP-20260803-0001', 1, 'hapus', '2026-08-03 11:58:00', '2026-08-03 12:06:22', '2026-08-03 12:06:00', 'destroyed', 'completed', 2, 2, '', '2026-08-03 12:06:09', '2026-08-03 12:06:49'),
(2, 'DSP-20260803-0002', 2, 'Hapus', '2026-08-03 12:07:00', '2026-08-03 12:07:39', '2026-08-03 12:07:00', 'destroyed', 'completed', 2, 2, '', '2026-08-03 12:07:34', '2026-08-03 12:07:48'),
(3, 'DSP-20260803-0003', 3, 'Hapus', '2026-08-03 12:08:00', '2026-08-03 12:08:29', '2026-08-03 12:08:00', 'destroyed', 'completed', 2, 2, '', '2026-08-03 12:08:27', '2026-08-03 12:08:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `email_delivery_logs`
--

CREATE TABLE `email_delivery_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `member_id` bigint(20) UNSIGNED DEFAULT NULL,
  `recipient_email` varchar(150) NOT NULL,
  `mail_type` varchar(80) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `delivery_status` enum('sent','failed') NOT NULL,
  `reference_type` varchar(80) DEFAULT NULL,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `email_delivery_logs`
--

INSERT INTO `email_delivery_logs` (`id`, `user_id`, `member_id`, `recipient_email`, `mail_type`, `subject`, `delivery_status`, `reference_type`, `reference_id`, `error_message`, `sent_at`, `created_at`) VALUES
(1, 5, 2, 'seriusndruru099@gmail.com', 'loan_request_approved', 'Pengajuan peminjaman disetujui', 'sent', 'loan_request', 3, NULL, '2026-08-03 07:41:54', '2026-08-03 07:41:54'),
(2, 5, 2, 'seriusndruru099@gmail.com', 'loan_request_ready', 'Buku siap diambil', 'sent', 'loan_request', 3, NULL, '2026-08-03 07:41:58', '2026-08-03 07:41:58'),
(3, 5, 2, 'seriusndruru099@gmail.com', 'loan_request_collected', 'Pengambilan buku dikonfirmasi', 'sent', 'loan_request', 3, NULL, '2026-08-03 07:42:03', '2026-08-03 07:42:03');

-- --------------------------------------------------------

--
-- Struktur dari tabel `fine_payments`
--

CREATE TABLE `fine_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payment_code` varchar(70) NOT NULL,
  `loan_item_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(15,2) UNSIGNED NOT NULL,
  `payment_date` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_method` enum('cash','transfer','other') NOT NULL DEFAULT 'cash',
  `received_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `items`
--

CREATE TABLE `items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_code` varchar(60) NOT NULL,
  `item_name` varchar(220) NOT NULL,
  `item_type` enum('book','equipment','electronic','furniture','consumable','other') NOT NULL,
  `tracking_type` enum('asset','quantity') NOT NULL DEFAULT 'asset',
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `unit_id` bigint(20) UNSIGNED NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `contract_number` varchar(180) DEFAULT NULL,
  `contract_date` date DEFAULT NULL,
  `contract_start_date` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `asset_type_code` varchar(80) DEFAULT NULL,
  `skpd_name` varchar(160) NOT NULL DEFAULT 'SDN MEKARSARI 08',
  `minimum_stock` decimal(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `items`
--

INSERT INTO `items` (`id`, `item_code`, `item_name`, `item_type`, `tracking_type`, `category_id`, `unit_id`, `description`, `image_path`, `contract_number`, `contract_date`, `contract_start_date`, `contract_end_date`, `asset_type_code`, `skpd_name`, `minimum_stock`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'BK-TEST-001', 'Dasar Pemrograman Web', 'book', 'asset', 7, 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SDN MEKARSARI 08', 1.00, 'inactive', 2, 2, '2026-07-31 12:21:55', '2026-08-03 13:04:08');

--
-- Trigger `items`
--
DELIMITER $$
CREATE TRIGGER `trg_items_after_insert_book` AFTER INSERT ON `items` FOR EACH ROW BEGIN
    IF NEW.item_type = 'book' THEN
        INSERT INTO book_details (item_id, completion_status)
        VALUES (NEW.id, 'incomplete');
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_items_after_update_book` AFTER UPDATE ON `items` FOR EACH ROW BEGIN
    IF NEW.item_type = 'book' AND OLD.item_type <> 'book' THEN
        INSERT IGNORE INTO book_details (item_id, completion_status)
        VALUES (NEW.id, 'incomplete');
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_items_before_update` BEFORE UPDATE ON `items` FOR EACH ROW BEGIN
    DECLARE v_asset_count INT DEFAULT 0;
    DECLARE v_balance_count INT DEFAULT 0;

    IF OLD.item_type = 'book' AND NEW.item_type <> 'book' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Item buku tidak boleh diubah menjadi jenis nonbuku.';
    END IF;

    IF OLD.tracking_type <> NEW.tracking_type THEN
        SELECT COUNT(*) INTO v_asset_count
        FROM assets
        WHERE item_id = OLD.id;

        SELECT COUNT(*) INTO v_balance_count
        FROM stock_balances
        WHERE item_id = OLD.id;

        IF v_asset_count > 0 OR v_balance_count > 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Tracking type tidak boleh diubah setelah item memiliki stok atau aset.';
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `library_shelves`
--

CREATE TABLE `library_shelves` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shelf_code` varchar(50) NOT NULL,
  `shelf_name` varchar(150) NOT NULL,
  `location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `classification_range` varchar(100) DEFAULT NULL,
  `capacity` int(10) UNSIGNED DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `library_shelves`
--

INSERT INTO `library_shelves` (`id`, `shelf_code`, `shelf_name`, `location_id`, `classification_range`, `capacity`, `description`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'RK-TI-01', 'Rak Teknologi Informasi', 3, '000-099', 100, 'Rak buku komputer dan teknologi', 'inactive', 4, '2026-07-31 13:34:06', '2026-08-03 14:15:50');

-- --------------------------------------------------------

--
-- Struktur dari tabel `loans`
--

CREATE TABLE `loans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `loan_code` varchar(70) NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `loan_date` datetime NOT NULL DEFAULT current_timestamp(),
  `default_due_date` date NOT NULL,
  `status` enum('active','completed','overdue','cancelled') NOT NULL DEFAULT 'active',
  `processed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `loans`
--

INSERT INTO `loans` (`id`, `loan_code`, `member_id`, `loan_date`, `default_due_date`, `status`, `processed_by`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'PJM-20260801-000819-WRNA', 1, '2026-08-01 00:08:19', '2026-08-08', 'completed', 4, NULL, '2026-07-31 17:08:19', '2026-07-31 17:20:17'),
(2, 'PJM-20260801-003324-BFBY', 1, '2026-08-01 00:33:24', '2026-08-01', 'completed', 4, NULL, '2026-07-31 17:33:24', '2026-07-31 17:35:36'),
(3, 'PJM-20260801-123414-JDC5', 2, '2026-08-01 12:34:14', '2026-08-08', 'completed', 4, 'Dibuat dari pengajuan REQ-20260801-123158-JUAF', '2026-08-01 05:34:14', '2026-08-01 05:34:50'),
(4, 'PJM-20260801-123617-6VLZ', 2, '2026-08-01 12:36:17', '2026-08-08', 'completed', 4, 'Dibuat dari pengajuan REQ-20260801-123548-2XRE', '2026-08-01 05:36:17', '2026-08-03 07:42:42'),
(5, 'PJM-20260803-074202-D98M', 2, '2026-08-03 07:42:02', '2026-08-10', 'completed', 4, 'Dibuat dari pengajuan REQ-20260801-143621-NHPC', '2026-08-03 07:42:02', '2026-08-03 07:42:20');

-- --------------------------------------------------------

--
-- Struktur dari tabel `loan_items`
--

CREATE TABLE `loan_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `loan_id` bigint(20) UNSIGNED NOT NULL,
  `asset_id` bigint(20) UNSIGNED NOT NULL,
  `borrowed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `due_date` date NOT NULL,
  `condition_out` enum('good','fair','damaged') NOT NULL DEFAULT 'good',
  `returned_at` datetime DEFAULT NULL,
  `condition_in` enum('good','fair','damaged','lost') DEFAULT NULL,
  `return_status` enum('borrowed','returned','damaged','lost') NOT NULL DEFAULT 'borrowed',
  `fine_amount` decimal(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `return_notes` text DEFAULT NULL,
  `returned_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `loan_items`
--

INSERT INTO `loan_items` (`id`, `loan_id`, `asset_id`, `borrowed_at`, `due_date`, `condition_out`, `returned_at`, `condition_in`, `return_status`, `fine_amount`, `return_notes`, `returned_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2026-08-01 00:08:19', '2026-08-08', 'good', '2026-08-01 00:20:17', 'good', 'returned', 0.00, NULL, 4, '2026-07-31 17:08:19', '2026-07-31 17:20:17'),
(2, 2, 1, '2026-08-01 00:33:24', '2026-08-01', 'good', '2026-08-01 00:35:23', 'good', 'returned', 0.00, NULL, 4, '2026-07-31 17:33:24', '2026-07-31 17:35:23'),
(3, 2, 2, '2026-08-01 00:33:24', '2026-08-01', 'good', '2026-08-01 00:35:36', 'good', 'returned', 0.00, NULL, 4, '2026-07-31 17:33:24', '2026-07-31 17:35:36'),
(4, 3, 1, '2026-08-01 12:34:14', '2026-08-08', 'good', '2026-08-01 12:34:50', 'good', 'returned', 0.00, NULL, 4, '2026-08-01 05:34:14', '2026-08-01 05:34:50'),
(5, 4, 1, '2026-08-01 12:36:17', '2026-08-08', 'good', '2026-08-03 07:42:42', 'good', 'returned', 0.00, NULL, 4, '2026-08-01 05:36:17', '2026-08-03 07:42:42'),
(6, 5, 2, '2026-08-03 07:42:02', '2026-08-10', 'good', '2026-08-03 07:42:20', 'good', 'returned', 0.00, NULL, 4, '2026-08-03 07:42:02', '2026-08-03 07:42:20');

--
-- Trigger `loan_items`
--
DELIMITER $$
CREATE TRIGGER `trg_loan_items_after_insert` AFTER INSERT ON `loan_items` FOR EACH ROW BEGIN
    DECLARE v_item_id BIGINT UNSIGNED;
    DECLARE v_location_id BIGINT UNSIGNED;
    DECLARE v_processed_by BIGINT UNSIGNED;

    SELECT item_id, current_location_id
    INTO v_item_id, v_location_id
    FROM assets
    WHERE id = NEW.asset_id;

    SELECT processed_by INTO v_processed_by
    FROM loans
    WHERE id = NEW.loan_id;

    UPDATE assets
    SET asset_status = 'borrowed', updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.asset_id;

    INSERT INTO stock_movements (
        movement_code, item_id, asset_id, movement_type, quantity,
        from_location_id, reference_type, reference_id, created_by, notes
    ) VALUES (
        CONCAT('MOV-', REPLACE(UUID(), '-', '')),
        v_item_id, NEW.asset_id, 'loan', 1,
        v_location_id, 'loan_item', NEW.id, v_processed_by,
        'Buku dipinjam.'
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_loan_items_after_update` AFTER UPDATE ON `loan_items` FOR EACH ROW BEGIN
    DECLARE v_item_id BIGINT UNSIGNED;
    DECLARE v_location_id BIGINT UNSIGNED;
    DECLARE v_shelf_id BIGINT UNSIGNED;
    DECLARE v_completion_status VARCHAR(30);
    DECLARE v_remaining INT DEFAULT 0;

    IF OLD.return_status = 'borrowed' AND NEW.return_status <> 'borrowed' THEN
        SELECT a.item_id, a.current_location_id, a.current_shelf_id, bd.completion_status
        INTO v_item_id, v_location_id, v_shelf_id, v_completion_status
        FROM assets a
        LEFT JOIN book_details bd ON bd.item_id = a.item_id
        WHERE a.id = NEW.asset_id;

        IF NEW.return_status = 'returned' THEN
            UPDATE assets
            SET asset_status = CASE
                    WHEN v_shelf_id IS NOT NULL
                         AND v_completion_status IN ('complete', 'verified')
                         AND COALESCE(NEW.condition_in, condition_status) IN ('good', 'fair')
                    THEN 'available'
                    ELSE 'unprocessed'
                END,
                condition_status = COALESCE(NEW.condition_in, condition_status),
                updated_by = NEW.returned_by,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = NEW.asset_id;
        ELSEIF NEW.return_status = 'damaged' THEN
            UPDATE assets
            SET asset_status = 'damaged',
                condition_status = 'damaged',
                updated_by = NEW.returned_by,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = NEW.asset_id;
        ELSEIF NEW.return_status = 'lost' THEN
            UPDATE assets
            SET asset_status = 'lost',
                condition_status = 'lost',
                updated_by = NEW.returned_by,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = NEW.asset_id;
        END IF;

        INSERT INTO stock_movements (
            movement_code, item_id, asset_id, movement_type, quantity,
            to_location_id, reference_type, reference_id, created_by, notes
        ) VALUES (
            CONCAT('MOV-', REPLACE(UUID(), '-', '')),
            v_item_id, NEW.asset_id, 'return', 1,
            v_location_id, 'loan_item', NEW.id, NEW.returned_by,
            CONCAT('Pengembalian buku dengan status ', NEW.return_status, '.')
        );

        SELECT COUNT(*) INTO v_remaining
        FROM loan_items
        WHERE loan_id = NEW.loan_id
          AND return_status = 'borrowed';

        IF v_remaining = 0 THEN
            UPDATE loans
            SET status = 'completed', updated_at = CURRENT_TIMESTAMP
            WHERE id = NEW.loan_id;
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_loan_items_before_insert` BEFORE INSERT ON `loan_items` FOR EACH ROW BEGIN
    DECLARE v_asset_status VARCHAR(30);
    DECLARE v_item_type VARCHAR(30);
    DECLARE v_loan_status VARCHAR(30);
    DECLARE v_member_status VARCHAR(30);
    DECLARE v_default_due_date DATE;
    DECLARE v_active_loan_count INT DEFAULT 0;
    DECLARE v_max_active_loans INT DEFAULT 3;
    DECLARE v_member_id BIGINT UNSIGNED;

    SELECT a.asset_status, i.item_type
    INTO v_asset_status, v_item_type
    FROM assets a
    JOIN items i ON i.id = a.item_id
    WHERE a.id = NEW.asset_id;

    IF v_asset_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Aset buku tidak ditemukan.';
    END IF;

    IF v_item_type <> 'book' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Hanya aset bertipe buku yang dapat dipinjam.';
    END IF;

    IF v_asset_status <> 'available' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Buku tidak tersedia untuk dipinjam.';
    END IF;

    SELECT l.status, l.default_due_date, l.member_id, m.status
    INTO v_loan_status, v_default_due_date, v_member_id, v_member_status
    FROM loans l
    JOIN members m ON m.id = l.member_id
    WHERE l.id = NEW.loan_id;

    IF v_loan_status IS NULL OR v_loan_status <> 'active' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Transaksi peminjaman tidak aktif.';
    END IF;

    IF v_member_status <> 'active' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Anggota tidak aktif dan tidak dapat meminjam.';
    END IF;

    SELECT CAST(setting_value AS UNSIGNED)
    INTO v_max_active_loans
    FROM system_settings
    WHERE setting_key = 'library.max_active_loans';

    SELECT COUNT(*)
    INTO v_active_loan_count
    FROM loan_items li
    JOIN loans l ON l.id = li.loan_id
    WHERE l.member_id = v_member_id
      AND li.return_status = 'borrowed';

    IF v_active_loan_count >= v_max_active_loans THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Anggota telah mencapai batas maksimal peminjaman aktif.';
    END IF;

    IF NEW.due_date IS NULL THEN
        SET NEW.due_date = v_default_due_date;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `loan_requests`
--

CREATE TABLE `loan_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `request_code` varchar(70) NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('submitted','approved','ready','collected','rejected','cancelled','expired') NOT NULL DEFAULT 'submitted',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `ready_at` datetime DEFAULT NULL,
  `pickup_expires_at` datetime DEFAULT NULL,
  `collected_at` datetime DEFAULT NULL,
  `processed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `member_notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `loan_requests`
--

INSERT INTO `loan_requests` (`id`, `request_code`, `member_id`, `status`, `requested_at`, `approved_at`, `ready_at`, `pickup_expires_at`, `collected_at`, `processed_by`, `member_notes`, `admin_notes`, `created_at`, `updated_at`) VALUES
(1, 'REQ-20260801-123158-JUAF', 2, 'collected', '2026-08-01 12:31:58', '2026-08-01 12:33:36', '2026-08-01 12:33:49', '2026-08-03 12:33:49', '2026-08-01 12:34:14', 4, NULL, NULL, '2026-08-01 05:31:58', '2026-08-01 05:34:14'),
(2, 'REQ-20260801-123548-2XRE', 2, 'collected', '2026-08-01 12:35:48', '2026-08-01 12:36:13', '2026-08-01 12:36:15', '2026-08-03 12:36:15', '2026-08-01 12:36:17', 4, NULL, NULL, '2026-08-01 05:35:48', '2026-08-01 05:36:17'),
(3, 'REQ-20260801-143621-NHPC', 2, 'collected', '2026-08-01 14:36:21', '2026-08-03 07:41:47', '2026-08-03 07:41:58', '2026-08-05 07:41:58', '2026-08-03 07:42:02', 4, NULL, NULL, '2026-08-01 07:36:21', '2026-08-03 07:42:02');

-- --------------------------------------------------------

--
-- Struktur dari tabel `loan_request_items`
--

CREATE TABLE `loan_request_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `loan_request_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `asset_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `loan_request_items`
--

INSERT INTO `loan_request_items` (`id`, `loan_request_id`, `item_id`, `asset_id`, `created_at`) VALUES
(1, 1, 1, 1, '2026-08-01 05:31:58'),
(2, 2, 1, 1, '2026-08-01 05:35:48'),
(3, 3, 1, 2, '2026-08-01 07:36:21');

-- --------------------------------------------------------

--
-- Struktur dari tabel `locations`
--

CREATE TABLE `locations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `location_code` varchar(50) NOT NULL,
  `location_name` varchar(150) NOT NULL,
  `location_type` enum('building','floor','room','warehouse','cabinet','other') NOT NULL DEFAULT 'room',
  `description` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `locations`
--

INSERT INTO `locations` (`id`, `parent_id`, `location_code`, `location_name`, `location_type`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, NULL, 'GDG-UTAMA', 'Gedung Utama', 'building', NULL, 'inactive', '2026-07-31 12:01:26', '2026-08-03 14:16:27'),
(2, 1, 'LT-01', 'Lantai 1', 'floor', NULL, 'inactive', '2026-07-31 12:02:03', '2026-08-03 14:16:24'),
(3, 2, 'R-PERPUS', 'Ruang Perpustakaan', 'room', NULL, 'inactive', '2026-07-31 12:02:49', '2026-08-03 14:16:20'),
(4, NULL, 'GDG-BARANG', 'Gudang', 'warehouse', NULL, 'active', '2026-08-03 15:33:14', '2026-08-03 15:33:57');

-- --------------------------------------------------------

--
-- Struktur dari tabel `maintenance_records`
--

CREATE TABLE `maintenance_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `maintenance_code` varchar(70) NOT NULL,
  `asset_id` bigint(20) UNSIGNED NOT NULL,
  `reported_at` datetime NOT NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `issue_description` text NOT NULL,
  `action_taken` text DEFAULT NULL,
  `cost` decimal(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `vendor_name` varchar(180) DEFAULT NULL,
  `status` enum('reported','in_progress','completed','cancelled') NOT NULL DEFAULT 'reported',
  `reported_by` bigint(20) UNSIGNED DEFAULT NULL,
  `handled_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `members`
--

CREATE TABLE `members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_code` varchar(60) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `member_name` varchar(180) NOT NULL,
  `member_type` enum('student','teacher','staff','public') NOT NULL DEFAULT 'student',
  `identity_number` varchar(80) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `join_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('active','suspended','inactive','expired') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `members`
--

INSERT INTO `members` (`id`, `member_code`, `user_id`, `member_name`, `member_type`, `identity_number`, `department`, `phone`, `email`, `address`, `join_date`, `expiry_date`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'AGT-2026-00001', NULL, 'Andi Pratama', 'student', '20260001', 'Kelas 1', '081234567890', 'andi@example.com', NULL, '2026-07-31', '2027-07-31', 'inactive', 4, '2026-07-31 16:33:55', '2026-08-03 11:38:05'),
(2, 'AGT-202608-0001', 5, 'Serius Ndruru', 'student', '12345678', 'Kelas 2', '082277625541', 'seriusndruru099@gmail.com', 'Pasir Konci, Desa Pasirsari, Kecamatan Cikarang Selatan, Kabupaten Bekasi, Provinsi Jawa Barat.', '2024-07-15', '2030-04-19', 'active', NULL, '2026-08-01 05:31:09', '2026-08-01 06:57:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `member_notifications`
--

CREATE TABLE `member_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `loan_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notification_key` varchar(150) NOT NULL,
  `notification_type` enum('due_tomorrow','overdue','request_status','system') NOT NULL DEFAULT 'system',
  `title` varchar(180) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `member_notifications`
--

INSERT INTO `member_notifications` (`id`, `member_id`, `loan_item_id`, `notification_key`, `notification_type`, `title`, `message`, `is_read`, `read_at`, `created_at`) VALUES
(1, 2, NULL, 'loan-request:1:submitted', 'request_status', 'Pengajuan peminjaman diterima', 'Pengajuan REQ-20260801-123158-JUAF sudah diterima dan menunggu persetujuan petugas.', 1, '2026-08-01 13:05:41', '2026-08-01 05:31:58'),
(2, 2, NULL, 'loan-request:1:approved', 'request_status', 'Pengajuan peminjaman disetujui', 'Pengajuan REQ-20260801-123158-JUAF disetujui. Petugas sedang menyiapkan buku.', 1, '2026-08-01 13:05:41', '2026-08-01 05:33:36'),
(3, 2, NULL, 'loan-request:1:ready', 'request_status', 'Buku siap diambil', 'Buku untuk pengajuan REQ-20260801-123158-JUAF siap diambil sebelum 03/08/2026 12:33.', 1, '2026-08-01 13:05:41', '2026-08-01 05:33:49'),
(4, 2, NULL, 'loan-request:1:collected', 'request_status', 'Pengambilan buku dikonfirmasi', 'Buku pada pengajuan REQ-20260801-123158-JUAF sudah dicatat sebagai peminjaman aktif.', 1, '2026-08-01 13:05:41', '2026-08-01 05:34:14'),
(5, 2, NULL, 'loan-request:2:submitted', 'request_status', 'Pengajuan peminjaman diterima', 'Pengajuan REQ-20260801-123548-2XRE sudah diterima dan menunggu persetujuan petugas.', 1, '2026-08-01 13:05:41', '2026-08-01 05:35:48'),
(6, 2, NULL, 'loan-request:2:approved', 'request_status', 'Pengajuan peminjaman disetujui', 'Pengajuan REQ-20260801-123548-2XRE disetujui. Petugas sedang menyiapkan buku.', 1, '2026-08-01 13:05:41', '2026-08-01 05:36:13'),
(7, 2, NULL, 'loan-request:2:ready', 'request_status', 'Buku siap diambil', 'Buku untuk pengajuan REQ-20260801-123548-2XRE siap diambil sebelum 03/08/2026 12:36.', 1, '2026-08-01 13:05:41', '2026-08-01 05:36:15'),
(8, 2, NULL, 'loan-request:2:collected', 'request_status', 'Pengambilan buku dikonfirmasi', 'Buku pada pengajuan REQ-20260801-123548-2XRE sudah dicatat sebagai peminjaman aktif.', 1, '2026-08-01 13:05:41', '2026-08-01 05:36:17'),
(9, 2, NULL, 'loan-request:3:submitted', 'request_status', 'Pengajuan peminjaman diterima', 'Pengajuan REQ-20260801-143621-NHPC sudah diterima dan menunggu persetujuan petugas.', 0, NULL, '2026-08-01 07:36:21'),
(10, 2, NULL, 'loan-request:3:approved', 'request_status', 'Pengajuan peminjaman disetujui', 'Pengajuan REQ-20260801-143621-NHPC disetujui. Petugas sedang menyiapkan buku.', 0, NULL, '2026-08-03 07:41:47'),
(11, 2, NULL, 'loan-request:3:ready', 'request_status', 'Buku siap diambil', 'Buku untuk pengajuan REQ-20260801-143621-NHPC siap diambil sebelum 05/08/2026 07:41.', 0, NULL, '2026-08-03 07:41:58'),
(12, 2, NULL, 'loan-request:3:collected', 'request_status', 'Pengambilan buku dikonfirmasi', 'Buku pada pengajuan REQ-20260801-143621-NHPC sudah dicatat sebagai peminjaman aktif.', 0, NULL, '2026-08-03 07:42:02');

-- --------------------------------------------------------

--
-- Struktur dari tabel `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(150) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `public_contact_messages`
--

CREATE TABLE `public_contact_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sender_name` varchar(180) NOT NULL,
  `sender_email` varchar(150) DEFAULT NULL,
  `sender_phone` varchar(50) DEFAULT NULL,
  `subject` varchar(220) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','replied','closed') NOT NULL DEFAULT 'unread',
  `handled_by` bigint(20) UNSIGNED DEFAULT NULL,
  `handled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `public_damage_reports`
--

CREATE TABLE `public_damage_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `report_code` varchar(70) NOT NULL,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `asset_id` bigint(20) UNSIGNED DEFAULT NULL,
  `location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reporter_name` varchar(180) DEFAULT NULL,
  `reporter_contact` varchar(150) DEFAULT NULL,
  `issue_description` text NOT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `status` enum('submitted','reviewed','in_progress','resolved','rejected') NOT NULL DEFAULT 'submitted',
  `handled_by` bigint(20) UNSIGNED DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `public_damage_reports`
--

INSERT INTO `public_damage_reports` (`id`, `report_code`, `item_id`, `asset_id`, `location_id`, `reporter_name`, `reporter_contact`, `issue_description`, `photo_path`, `status`, `handled_by`, `admin_notes`, `created_at`, `updated_at`) VALUES
(1, 'RUS-20260801-142646-8IIV', 1, 1, 3, 'Rius', '082277625541', 'bukunya sobek bagian halaman 230', 'damage-reports/cSL1TKMEDTLjsoSfKevO8aAQdMBhTRFfwViUye8g.png', 'resolved', 3, NULL, '2026-08-01 07:26:46', '2026-08-03 07:44:50');

-- --------------------------------------------------------

--
-- Struktur dari tabel `publishers`
--

CREATE TABLE `publishers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `publisher_name` varchar(180) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `publishers`
--

INSERT INTO `publishers` (`id`, `publisher_name`, `city`, `address`, `phone`, `email`, `created_at`, `updated_at`) VALUES
(1, 'Penerbit Teknologi Nusantara', NULL, NULL, NULL, NULL, '2026-07-31 12:33:55', '2026-07-31 12:33:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `reservations`
--

CREATE TABLE `reservations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reservation_code` varchar(70) NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `reservation_date` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `queue_number` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('waiting','ready','completed','cancelled','expired') NOT NULL DEFAULT 'waiting',
  `processed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `reservations`
--

INSERT INTO `reservations` (`id`, `reservation_code`, `member_id`, `item_id`, `reservation_date`, `expires_at`, `queue_number`, `status`, `processed_by`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'RSV-20260801-005716-SN3U', 1, 1, '2026-08-01 00:57:16', NULL, 1, 'expired', 4, NULL, '2026-07-31 17:57:16', '2026-08-03 07:37:48');

-- --------------------------------------------------------

--
-- Struktur dari tabel `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role_code` varchar(50) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `roles`
--

INSERT INTO `roles` (`id`, `role_code`, `role_name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'SUPER_ADMIN', 'Super Admin', 'Mengelola akun, hak akses, konfigurasi, serta seluruh modul sistem.', '2026-07-31 10:21:00', '2026-07-31 12:48:52'),
(2, 'INVENTORY_ADMIN', 'Admin Inventaris', 'Mengelola barang, aset, stok, kategori, satuan, supplier, lokasi, opname, dan penghapusan.', '2026-07-31 10:21:00', '2026-07-31 12:48:52'),
(4, 'MANAGER', 'Pimpinan', 'Melihat dashboard dan laporan.', '2026-07-31 10:21:00', '2026-07-31 10:21:00'),
(5, 'MEMBER', 'Anggota', 'Mengakses katalog dan data peminjaman pribadi.', '2026-07-31 10:21:00', '2026-07-31 10:21:00'),
(6, 'LIBRARY_ADMIN', 'Admin Perpustakaan', 'Mengelola katalog buku, rak, anggota, peminjaman, pengembalian, reservasi, dan denda.', '2026-07-31 12:48:52', '2026-07-31 12:48:52');

-- --------------------------------------------------------

--
-- Struktur dari tabel `stock_balances`
--

CREATE TABLE `stock_balances` (
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `location_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` decimal(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `movement_code` varchar(70) NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `asset_id` bigint(20) UNSIGNED DEFAULT NULL,
  `movement_type` enum('receipt','issue','transfer','adjustment_in','adjustment_out','loan','return','maintenance_out','maintenance_in','disposal','opname') NOT NULL,
  `quantity` decimal(15,2) UNSIGNED NOT NULL DEFAULT 1.00,
  `from_location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `to_location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reference_type` varchar(60) DEFAULT NULL,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `movement_date` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `movement_code`, `item_id`, `asset_id`, `movement_type`, `quantity`, `from_location_id`, `to_location_id`, `reference_type`, `reference_id`, `movement_date`, `created_by`, `notes`, `created_at`) VALUES
(1, 'MOV-05dc8f14837a4b6f97069b9c5fde542f', 1, 1, 'receipt', 1.00, NULL, 3, 'initial_stock', 1, '2026-07-31 19:21:55', 2, 'Penerimaan unit aset awal.', '2026-07-31 12:21:55'),
(2, 'MOV-b71a4958ac2546bb9f44234cf6efedf9', 1, 2, 'receipt', 1.00, NULL, 3, 'initial_stock', 1, '2026-07-31 19:21:55', 2, 'Penerimaan unit aset awal.', '2026-07-31 12:21:55'),
(3, 'MOV-d0c595bb3bf743cd836cd9636642e818', 1, 3, 'receipt', 1.00, NULL, 3, 'initial_stock', 1, '2026-07-31 19:21:55', 2, 'Penerimaan unit aset awal.', '2026-07-31 12:21:55'),
(4, 'MOV-6df67f448d0211f19a3ed0c0bf2f5be4', 1, 1, 'loan', 1.00, 3, NULL, 'loan_item', 1, '2026-08-01 00:08:19', 4, 'Buku dipinjam.', '2026-07-31 17:08:19'),
(5, 'MOV-19dab6af8d0411f19a3ed0c0bf2f5be4', 1, 1, 'return', 1.00, NULL, 3, 'loan_item', 1, '2026-08-01 00:20:17', 4, 'Pengembalian buku dengan status returned.', '2026-07-31 17:20:17'),
(6, 'MOV-eeea33248d0511f19a3ed0c0bf2f5be4', 1, 1, 'loan', 1.00, 3, NULL, 'loan_item', 2, '2026-08-01 00:33:24', 4, 'Buku dipinjam.', '2026-07-31 17:33:24'),
(7, 'MOV-eeeb7f368d0511f19a3ed0c0bf2f5be4', 1, 2, 'loan', 1.00, 3, NULL, 'loan_item', 3, '2026-08-01 00:33:24', 4, 'Buku dipinjam.', '2026-07-31 17:33:24'),
(8, 'MOV-35e827c58d0611f19a3ed0c0bf2f5be4', 1, 1, 'return', 1.00, NULL, 3, 'loan_item', 2, '2026-08-01 00:35:23', 4, 'Pengembalian buku dengan status returned.', '2026-07-31 17:35:23'),
(9, 'MOV-3d46fb2a8d0611f19a3ed0c0bf2f5be4', 1, 2, 'return', 1.00, NULL, 3, 'loan_item', 3, '2026-08-01 00:35:36', 4, 'Pengembalian buku dengan status returned.', '2026-07-31 17:35:36'),
(10, 'MOV-a1c596008d6a11f1af0aa83b763dddc6', 1, 1, 'loan', 1.00, 3, NULL, 'loan_item', 4, '2026-08-01 12:34:14', 4, 'Buku dipinjam.', '2026-08-01 05:34:14'),
(11, 'MOV-b721a5c38d6a11f1af0aa83b763dddc6', 1, 1, 'return', 1.00, NULL, 3, 'loan_item', 4, '2026-08-01 12:34:50', 4, 'Pengembalian buku dengan status returned.', '2026-08-01 05:34:50'),
(12, 'MOV-eb0d2eb28d6a11f1af0aa83b763dddc6', 1, 1, 'loan', 1.00, 3, NULL, 'loan_item', 5, '2026-08-01 12:36:17', 4, 'Buku dipinjam.', '2026-08-01 05:36:17'),
(13, 'MOV-24e1b7ba8ed411f1ac91000c291f55b4', 1, 2, 'loan', 1.00, 3, NULL, 'loan_item', 6, '2026-08-03 00:42:02', 4, 'Buku dipinjam.', '2026-08-03 00:42:02'),
(14, 'MOV-2f79c3ea8ed411f1ac91000c291f55b4', 1, 2, 'return', 1.00, NULL, 3, 'loan_item', 6, '2026-08-03 00:42:20', 4, 'Pengembalian buku dengan status returned.', '2026-08-03 00:42:20'),
(15, 'MOV-3cc3dc0a8ed411f1ac91000c291f55b4', 1, 1, 'return', 1.00, NULL, 3, 'loan_item', 5, '2026-08-03 00:42:42', 4, 'Pengembalian buku dengan status returned.', '2026-08-03 00:42:42'),
(16, 'MOV-fe013b09b4ee4cd4af7ae6258bf0fabe', 1, 1, 'disposal', 1.00, 3, NULL, 'disposal', 1, '2026-08-03 12:06:00', 2, 'Penghapusan aset BK-TEST-001-001 melalui metode Dimusnahkan.', '2026-08-03 12:06:50'),
(17, 'MOV-d81658202a0b4b719d24295a0dd4bebb', 1, 2, 'disposal', 1.00, 3, NULL, 'disposal', 2, '2026-08-03 12:07:00', 2, 'Penghapusan aset BK-TEST-001-002 melalui metode Dimusnahkan.', '2026-08-03 12:07:48'),
(18, 'MOV-9741d0b334804b41bbaf6303f38c2fa0', 1, 3, 'disposal', 1.00, 3, NULL, 'disposal', 3, '2026-08-03 12:08:00', 2, 'Penghapusan aset BK-TEST-001-003 melalui metode Dimusnahkan.', '2026-08-03 12:08:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `stock_opnames`
--

CREATE TABLE `stock_opnames` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `opname_code` varchar(70) NOT NULL,
  `location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `opname_date` date NOT NULL,
  `status` enum('draft','in_progress','completed','cancelled') NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `stock_opname_items`
--

CREATE TABLE `stock_opname_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stock_opname_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `asset_id` bigint(20) UNSIGNED DEFAULT NULL,
  `expected_quantity` decimal(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `actual_quantity` decimal(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `difference_quantity` decimal(15,2) NOT NULL DEFAULT 0.00,
  `finding_status` enum('matched','surplus','shortage','damaged','missing') NOT NULL DEFAULT 'matched',
  `notes` text DEFAULT NULL,
  `checked_by` bigint(20) UNSIGNED DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `supplier_code` varchar(50) NOT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_code`, `supplier_name`, `contact_person`, `phone`, `email`, `address`, `status`, `created_at`, `updated_at`) VALUES
(1, 'SUP-001', 'PT. Sumber Ilmu', 'Budi Santoso', '0812-3456-7890', 'supplier@example.com', 'Kota Makassar', 'inactive', '2026-07-31 11:51:55', '2026-08-03 14:02:08');

-- --------------------------------------------------------

--
-- Struktur dari tabel `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `value_type` enum('string','integer','decimal','boolean','json') NOT NULL DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `value_type`, `description`, `updated_by`, `updated_at`) VALUES
('application.name', 'Sistem Inventaris dan Perpustakaan', 'string', 'Nama aplikasi yang tampil pada halaman login dan judul halaman.', 2, '2026-08-01 05:26:11'),
('application.short_name', 'SIP', 'string', 'Inisial singkat aplikasi yang tampil pada logo sidebar.', 2, '2026-08-01 05:26:12'),
('institution.address', 'SDN Mekarsari 08, Jl. Mesjid Darussalam 03, Desa Mekarsari, Kecamatan Tambun Selatan, Kabupaten Bekasi, Jawa Barat, 17510', 'string', 'Alamat instansi untuk identitas aplikasi dan dokumen.', 2, '2026-08-01 05:26:12'),
('institution.email', 'sdnegerimekarsari08@gmail.com', 'string', 'Email resmi instansi.', 2, '2026-08-01 05:26:12'),
('institution.name', 'SDN Mekarsari 08', 'string', 'Nama instansi pemilik sistem.', 2, '2026-08-01 05:26:12'),
('institution.phone', '082277625541', 'string', 'Nomor telepon resmi instansi.', 2, '2026-08-01 05:26:12'),
('inventory.asset_code_separator', '-', 'string', 'Pemisah antara kode barang dan nomor urut unit aset baru.', 2, '2026-08-01 05:26:12'),
('library.allow_incomplete_book_loan', 'false', 'boolean', 'Izin peminjaman buku yang data katalognya belum lengkap.', NULL, '2026-07-31 10:21:00'),
('library.default_loan_days', '7', 'integer', 'Lama peminjaman standar dalam hari.', 2, '2026-08-01 05:26:12'),
('library.fine_per_day', '1000', 'decimal', 'Nominal denda keterlambatan per hari untuk setiap eksemplar.', 2, '2026-08-01 05:26:12'),
('library.loan_request_hold_days', '2', 'integer', 'Masa pengambilan buku setelah pengajuan siap.', 2, '2026-08-01 05:26:12'),
('library.max_active_loans', '3', 'integer', 'Jumlah maksimal eksemplar yang boleh dipinjam aktif oleh satu anggota.', 2, '2026-08-01 05:26:12'),
('library.max_active_reservations', '3', 'integer', 'Jumlah maksimal reservasi aktif untuk satu anggota.', 2, '2026-08-01 05:26:12'),
('library.reservation_hold_days', '2', 'integer', 'Jumlah hari buku berstatus siap diambil sebelum reservasi kedaluwarsa.', 2, '2026-08-01 05:26:12'),
('portal.about_content', 'Perpustakaan menyediakan koleksi belajar, layanan sirkulasi, dan informasi inventaris yang dapat diakses secara transparan.', 'string', 'Isi halaman tentang portal publik.', 2, '2026-08-01 05:26:12'),
('portal.about_title', 'Tentang Perpustakaan', 'string', 'Judul halaman tentang.', 2, '2026-08-01 05:26:12'),
('portal.about_video_url', 'https://www.youtube.com/watch?v=wEeExOHXoas', 'string', 'Tautan video YouTube atau Vimeo.', 2, '2026-08-01 05:26:12'),
('portal.contact_intro', 'Hubungi pengelola perpustakaan untuk pertanyaan layanan, koleksi, atau akun anggota.', 'string', 'Pengantar halaman kontak.', 2, '2026-08-01 05:26:12'),
('portal.hero_subtitle', 'Temukan koleksi, ajukan peminjaman, pantau pengembalian, dan akses informasi inventaris dari satu tempat.', 'string', 'Subjudul portal publik.', 2, '2026-08-01 05:26:12'),
('portal.hero_title', 'Perpustakaan yang dekat dengan siswa', 'string', 'Judul utama portal publik.', 2, '2026-08-01 05:26:12'),
('portal.opening_hours', 'Senin–Jumat, 07.30–15.30', 'string', 'Jam layanan perpustakaan.', 2, '2026-08-01 05:26:12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `units`
--

CREATE TABLE `units` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `unit_code` varchar(30) NOT NULL,
  `unit_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `units`
--

INSERT INTO `units` (`id`, `unit_code`, `unit_name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'PCS', 'Pcs', 'Satuan barang satuan.', 'active', '2026-07-31 10:21:00', '2026-07-31 10:21:00'),
(2, 'UNIT', 'Unit', 'Satuan unit inventaris.', 'active', '2026-07-31 10:21:00', '2026-07-31 10:21:00'),
(3, 'COPY', 'Eksemplar', 'Satuan untuk buku.', 'active', '2026-07-31 10:21:00', '2026-07-31 10:21:00'),
(4, 'BOX', 'Kotak', 'Satuan barang dalam kotak.', 'active', '2026-07-31 10:21:00', '2026-07-31 10:21:00'),
(5, 'SET', 'Set', 'Satuan perangkat dalam satu set.', 'active', '2026-07-31 10:21:00', '2026-07-31 10:21:00'),
(6, 'PACK', 'Paket', 'satuan barang dalam bentuk paket', 'active', '2026-07-31 11:44:41', '2026-07-31 11:44:41');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `username` varchar(60) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `status` enum('active','inactive','locked') NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `email`, `email_verified_at`, `password_hash`, `password_changed_at`, `phone`, `status`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Serius Ndruru', 'seriusndruru', 'seriusndruru7@gmail.com', NULL, '$2y$12$zDXjzanNOxyFOH2b4wQtiOeFwZruFsuvkEB3fQp2wv0Pj9vpgoOQO', NULL, NULL, 'active', '2026-07-31 18:13:01', '2026-07-31 11:11:32', '2026-07-31 11:13:01'),
(2, 'Admin Full Access', 'Admin1', NULL, NULL, '$2y$12$paOO7a4bYIBIwUuSlVRaBeOZva6/.7Y49y585VlANfnSgpJIQvV7u', NULL, NULL, 'active', '2026-08-03 14:15:36', '2026-07-31 11:17:29', '2026-08-03 14:15:36'),
(3, 'Admin Inventory', 'admin2', NULL, NULL, '$2y$12$LJh7vW1VQCNB7bpwpHsjReoLT79TLShz7YCe40rCblxqOqeAAgOJG', NULL, NULL, 'active', '2026-08-03 14:19:19', '2026-07-31 12:50:19', '2026-08-03 14:19:19'),
(4, 'Admin Library', 'admin3', NULL, NULL, '$2y$12$sVcDUiVyHF0y3TQjMSX8nuhsVdN3J7GxkgVSC8eVeNwCpGn9tIkDO', NULL, NULL, 'active', '2026-08-03 07:41:28', '2026-07-31 12:53:45', '2026-08-03 07:41:28'),
(5, 'Serius Ndruru', 'riusndruru', 'seriusndruru099@gmail.com', '2026-08-01 15:17:27', '$2y$12$3/ZOLyp4cSoMRhKvdmh0Aup6Hx190K56Ec0x7sL1ipzYo9h3L9rDq', NULL, '082277625541', 'active', '2026-08-01 20:41:40', '2026-08-01 05:31:09', '2026-08-01 13:41:40');

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`, `assigned_at`) VALUES
(1, 1, '2026-07-31 11:11:32'),
(2, 1, '2026-07-31 11:17:29'),
(3, 2, '2026-07-31 12:50:19'),
(4, 6, '2026-07-31 12:53:45'),
(5, 5, '2026-08-01 05:31:09');

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `vw_inventory_summary`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `vw_inventory_summary` (
`item_id` bigint(20) unsigned
,`item_code` varchar(60)
,`item_name` varchar(220)
,`item_type` enum('book','equipment','electronic','furniture','consumable','other')
,`tracking_type` enum('asset','quantity')
,`category_name` varchar(150)
,`unit_name` varchar(100)
,`total_quantity` decimal(37,2)
,`available_quantity` decimal(37,2)
,`minimum_stock` decimal(15,2) unsigned
,`status` enum('active','inactive')
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `vw_library_books`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `vw_library_books` (
`item_id` bigint(20) unsigned
,`item_code` varchar(60)
,`title` varchar(220)
,`category_id` bigint(20) unsigned
,`category_name` varchar(150)
,`isbn_10` varchar(20)
,`isbn_13` varchar(20)
,`publisher_id` bigint(20) unsigned
,`publisher_name` varchar(180)
,`publication_year` smallint(5) unsigned
,`edition` varchar(80)
,`language` varchar(50)
,`page_count` int(10) unsigned
,`classification_code` varchar(50)
,`call_number` varchar(80)
,`completion_status` enum('incomplete','complete','verified')
,`item_status` enum('active','inactive')
,`total_copies` bigint(21)
,`available_copies` decimal(22,0)
,`borrowed_copies` decimal(22,0)
,`unprocessed_copies` decimal(22,0)
,`damaged_copies` decimal(22,0)
,`lost_copies` decimal(22,0)
,`copies_without_shelf` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `vw_library_copies`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `vw_library_copies` (
`asset_id` bigint(20) unsigned
,`asset_code` varchar(90)
,`barcode` varchar(100)
,`item_id` bigint(20) unsigned
,`item_code` varchar(60)
,`title` varchar(220)
,`isbn_13` varchar(20)
,`classification_code` varchar(50)
,`call_number` varchar(80)
,`condition_status` enum('good','fair','damaged','lost')
,`asset_status` enum('unprocessed','available','borrowed','reserved','maintenance','damaged','lost','disposed')
,`current_location_id` bigint(20) unsigned
,`location_name` varchar(150)
,`current_shelf_id` bigint(20) unsigned
,`shelf_code` varchar(50)
,`shelf_name` varchar(150)
,`acquisition_date` date
,`acquisition_price` decimal(15,2) unsigned
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `vw_overdue_loans`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `vw_overdue_loans` (
`loan_item_id` bigint(20) unsigned
,`loan_id` bigint(20) unsigned
,`loan_code` varchar(70)
,`member_id` bigint(20) unsigned
,`member_code` varchar(60)
,`member_name` varchar(180)
,`asset_id` bigint(20) unsigned
,`asset_code` varchar(90)
,`item_id` bigint(20) unsigned
,`title` varchar(220)
,`borrowed_at` datetime
,`due_date` date
,`days_overdue` int(8)
,`estimated_fine` decimal(22,2)
);

--
-- Indeks untuk tabel yang dibuang
--

--
-- Indeks untuk tabel `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `asset_code` (`asset_code`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `fk_assets_supplier` (`supplier_id`),
  ADD KEY `fk_assets_created_by` (`created_by`),
  ADD KEY `fk_assets_updated_by` (`updated_by`),
  ADD KEY `idx_assets_item` (`item_id`),
  ADD KEY `idx_assets_status` (`asset_status`),
  ADD KEY `idx_assets_condition` (`condition_status`),
  ADD KEY `idx_assets_location` (`current_location_id`),
  ADD KEY `idx_assets_shelf` (`current_shelf_id`);

--
-- Indeks untuk tabel `asset_shelf_history`
--
ALTER TABLE `asset_shelf_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_shelf_history_old_shelf` (`old_shelf_id`),
  ADD KEY `fk_shelf_history_new_shelf` (`new_shelf_id`),
  ADD KEY `fk_shelf_history_user` (`changed_by`),
  ADD KEY `idx_shelf_history_asset_date` (`asset_id`,`changed_at`);

--
-- Indeks untuk tabel `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_logs_user_date` (`user_id`,`created_at`),
  ADD KEY `idx_audit_logs_module_date` (`module_name`,`created_at`),
  ADD KEY `idx_audit_logs_record` (`table_name`,`record_id`);

--
-- Indeks untuk tabel `authors`
--
ALTER TABLE `authors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_authors_name` (`author_name`);

--
-- Indeks untuk tabel `book_authors`
--
ALTER TABLE `book_authors`
  ADD PRIMARY KEY (`item_id`,`author_id`,`author_role`),
  ADD KEY `fk_book_authors_author` (`author_id`),
  ADD KEY `idx_book_authors_order` (`item_id`,`author_order`);

--
-- Indeks untuk tabel `book_details`
--
ALTER TABLE `book_details`
  ADD PRIMARY KEY (`item_id`),
  ADD UNIQUE KEY `isbn_10` (`isbn_10`),
  ADD UNIQUE KEY `isbn_13` (`isbn_13`),
  ADD KEY `fk_book_details_updated_by` (`updated_by`),
  ADD KEY `idx_book_details_publisher` (`publisher_id`),
  ADD KEY `idx_book_details_completion` (`completion_status`),
  ADD KEY `idx_book_details_classification` (`classification_code`),
  ADD KEY `idx_book_details_grade_level` (`grade_level`);

--
-- Indeks untuk tabel `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_code` (`category_code`),
  ADD KEY `idx_categories_parent` (`parent_id`),
  ADD KEY `idx_categories_scope_status` (`scope`,`status`);

--
-- Indeks untuk tabel `disposals`
--
ALTER TABLE `disposals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `disposal_code` (`disposal_code`),
  ADD UNIQUE KEY `asset_id` (`asset_id`),
  ADD KEY `fk_disposals_proposed_by` (`proposed_by`),
  ADD KEY `fk_disposals_approved_by` (`approved_by`);

--
-- Indeks untuk tabel `email_delivery_logs`
--
ALTER TABLE `email_delivery_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_email_delivery_logs_user` (`user_id`),
  ADD KEY `fk_email_delivery_logs_member` (`member_id`),
  ADD KEY `idx_email_delivery_status_date` (`delivery_status`,`created_at`),
  ADD KEY `idx_email_delivery_recipient` (`recipient_email`,`created_at`),
  ADD KEY `idx_email_delivery_reference` (`reference_type`,`reference_id`);

--
-- Indeks untuk tabel `fine_payments`
--
ALTER TABLE `fine_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_code` (`payment_code`),
  ADD KEY `fk_fine_payments_received_by` (`received_by`),
  ADD KEY `idx_fine_payments_loan_item` (`loan_item_id`),
  ADD KEY `idx_fine_payments_date` (`payment_date`);

--
-- Indeks untuk tabel `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_code` (`item_code`),
  ADD KEY `fk_items_unit` (`unit_id`),
  ADD KEY `fk_items_created_by` (`created_by`),
  ADD KEY `fk_items_updated_by` (`updated_by`),
  ADD KEY `idx_items_name` (`item_name`),
  ADD KEY `idx_items_type_status` (`item_type`,`status`),
  ADD KEY `idx_items_category` (`category_id`),
  ADD KEY `idx_items_contract_number` (`contract_number`);

--
-- Indeks untuk tabel `library_shelves`
--
ALTER TABLE `library_shelves`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shelf_code` (`shelf_code`),
  ADD KEY `fk_library_shelves_created_by` (`created_by`),
  ADD KEY `idx_shelves_location_status` (`location_id`,`status`);

--
-- Indeks untuk tabel `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `loan_code` (`loan_code`),
  ADD KEY `fk_loans_processed_by` (`processed_by`),
  ADD KEY `idx_loans_member_date` (`member_id`,`loan_date`),
  ADD KEY `idx_loans_status_due` (`status`,`default_due_date`);

--
-- Indeks untuk tabel `loan_items`
--
ALTER TABLE `loan_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_loan_asset` (`loan_id`,`asset_id`),
  ADD KEY `fk_loan_items_returned_by` (`returned_by`),
  ADD KEY `idx_loan_items_asset_status` (`asset_id`,`return_status`),
  ADD KEY `idx_loan_items_due_status` (`due_date`,`return_status`);

--
-- Indeks untuk tabel `loan_requests`
--
ALTER TABLE `loan_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_code` (`request_code`),
  ADD KEY `fk_loan_requests_processor` (`processed_by`),
  ADD KEY `idx_loan_requests_member_status` (`member_id`,`status`),
  ADD KEY `idx_loan_requests_status_date` (`status`,`requested_at`);

--
-- Indeks untuk tabel `loan_request_items`
--
ALTER TABLE `loan_request_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_loan_request_item` (`loan_request_id`,`item_id`),
  ADD UNIQUE KEY `uq_loan_request_asset` (`loan_request_id`,`asset_id`),
  ADD KEY `idx_loan_request_items_item` (`item_id`),
  ADD KEY `idx_loan_request_items_asset` (`asset_id`);

--
-- Indeks untuk tabel `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `location_code` (`location_code`),
  ADD KEY `idx_locations_parent` (`parent_id`),
  ADD KEY `idx_locations_type_status` (`location_type`,`status`);

--
-- Indeks untuk tabel `maintenance_records`
--
ALTER TABLE `maintenance_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `maintenance_code` (`maintenance_code`),
  ADD KEY `fk_maintenance_reported_by` (`reported_by`),
  ADD KEY `fk_maintenance_handled_by` (`handled_by`),
  ADD KEY `idx_maintenance_asset_status` (`asset_id`,`status`);

--
-- Indeks untuk tabel `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_code` (`member_code`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `identity_number` (`identity_number`),
  ADD KEY `fk_members_created_by` (`created_by`),
  ADD KEY `idx_members_name` (`member_name`),
  ADD KEY `idx_members_type_status` (`member_type`,`status`);

--
-- Indeks untuk tabel `member_notifications`
--
ALTER TABLE `member_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `notification_key` (`notification_key`),
  ADD KEY `fk_member_notifications_loan_item` (`loan_item_id`),
  ADD KEY `idx_member_notifications_member_read` (`member_id`,`is_read`,`created_at`);

--
-- Indeks untuk tabel `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`),
  ADD KEY `idx_password_reset_created_at` (`created_at`);

--
-- Indeks untuk tabel `public_contact_messages`
--
ALTER TABLE `public_contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_public_contact_messages_handler` (`handled_by`),
  ADD KEY `idx_public_contact_messages_status` (`status`,`created_at`);

--
-- Indeks untuk tabel `public_damage_reports`
--
ALTER TABLE `public_damage_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `report_code` (`report_code`),
  ADD KEY `fk_public_damage_reports_item` (`item_id`),
  ADD KEY `fk_public_damage_reports_handler` (`handled_by`),
  ADD KEY `idx_public_damage_reports_status` (`status`,`created_at`),
  ADD KEY `idx_public_damage_reports_asset` (`asset_id`),
  ADD KEY `idx_public_damage_reports_location` (`location_id`);

--
-- Indeks untuk tabel `publishers`
--
ALTER TABLE `publishers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `publisher_name` (`publisher_name`);

--
-- Indeks untuk tabel `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reservation_code` (`reservation_code`),
  ADD KEY `fk_reservations_processed_by` (`processed_by`),
  ADD KEY `idx_reservations_item_status` (`item_id`,`status`),
  ADD KEY `idx_reservations_member_status` (`member_id`,`status`);

--
-- Indeks untuk tabel `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_code` (`role_code`);

--
-- Indeks untuk tabel `stock_balances`
--
ALTER TABLE `stock_balances`
  ADD PRIMARY KEY (`item_id`,`location_id`),
  ADD KEY `fk_stock_balances_location` (`location_id`);

--
-- Indeks untuk tabel `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `movement_code` (`movement_code`),
  ADD KEY `fk_stock_movements_from_location` (`from_location_id`),
  ADD KEY `fk_stock_movements_to_location` (`to_location_id`),
  ADD KEY `fk_stock_movements_created_by` (`created_by`),
  ADD KEY `idx_stock_movements_item_date` (`item_id`,`movement_date`),
  ADD KEY `idx_stock_movements_asset_date` (`asset_id`,`movement_date`),
  ADD KEY `idx_stock_movements_reference` (`reference_type`,`reference_id`);

--
-- Indeks untuk tabel `stock_opnames`
--
ALTER TABLE `stock_opnames`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `opname_code` (`opname_code`),
  ADD KEY `fk_stock_opnames_location` (`location_id`),
  ADD KEY `fk_stock_opnames_created_by` (`created_by`),
  ADD KEY `fk_stock_opnames_approved_by` (`approved_by`),
  ADD KEY `idx_stock_opnames_date_status` (`opname_date`,`status`);

--
-- Indeks untuk tabel `stock_opname_items`
--
ALTER TABLE `stock_opname_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stock_opname_items_item` (`item_id`),
  ADD KEY `fk_stock_opname_items_asset` (`asset_id`),
  ADD KEY `fk_stock_opname_items_checked_by` (`checked_by`),
  ADD KEY `idx_stock_opname_items_opname_item` (`stock_opname_id`,`item_id`);

--
-- Indeks untuk tabel `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_code` (`supplier_code`),
  ADD KEY `idx_suppliers_name` (`supplier_name`);

--
-- Indeks untuk tabel `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`),
  ADD KEY `fk_system_settings_updated_by` (`updated_by`);

--
-- Indeks untuk tabel `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unit_code` (`unit_code`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_email_verified` (`email_verified_at`),
  ADD KEY `idx_users_password_changed` (`password_changed_at`);

--
-- Indeks untuk tabel `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `fk_user_roles_role` (`role_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `assets`
--
ALTER TABLE `assets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `asset_shelf_history`
--
ALTER TABLE `asset_shelf_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT untuk tabel `authors`
--
ALTER TABLE `authors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `disposals`
--
ALTER TABLE `disposals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `email_delivery_logs`
--
ALTER TABLE `email_delivery_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `fine_payments`
--
ALTER TABLE `fine_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `items`
--
ALTER TABLE `items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `library_shelves`
--
ALTER TABLE `library_shelves`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `loans`
--
ALTER TABLE `loans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `loan_items`
--
ALTER TABLE `loan_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `loan_requests`
--
ALTER TABLE `loan_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `loan_request_items`
--
ALTER TABLE `loan_request_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `locations`
--
ALTER TABLE `locations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `maintenance_records`
--
ALTER TABLE `maintenance_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `members`
--
ALTER TABLE `members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `member_notifications`
--
ALTER TABLE `member_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT untuk tabel `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `public_contact_messages`
--
ALTER TABLE `public_contact_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `public_damage_reports`
--
ALTER TABLE `public_damage_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `publishers`
--
ALTER TABLE `publishers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT untuk tabel `stock_opnames`
--
ALTER TABLE `stock_opnames`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `stock_opname_items`
--
ALTER TABLE `stock_opname_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `units`
--
ALTER TABLE `units`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

-- --------------------------------------------------------

--
-- Struktur untuk view `vw_inventory_summary`
--
DROP TABLE IF EXISTS `vw_inventory_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`seriusndruru_sdnmekarsari08`@`localhost` SQL SECURITY DEFINER VIEW `vw_inventory_summary`  AS SELECT `i`.`id` AS `item_id`, `i`.`item_code` AS `item_code`, `i`.`item_name` AS `item_name`, `i`.`item_type` AS `item_type`, `i`.`tracking_type` AS `tracking_type`, `c`.`category_name` AS `category_name`, `u`.`unit_name` AS `unit_name`, CASE WHEN `i`.`tracking_type` = 'asset' THEN coalesce(`aa`.`total_assets`,0) ELSE coalesce(`qs`.`total_stock`,0) END AS `total_quantity`, CASE WHEN `i`.`tracking_type` = 'asset' THEN coalesce(`aa`.`available_assets`,0) ELSE coalesce(`qs`.`total_stock`,0) END AS `available_quantity`, `i`.`minimum_stock` AS `minimum_stock`, `i`.`status` AS `status` FROM ((((`items` `i` left join `categories` `c` on(`c`.`id` = `i`.`category_id`)) left join `units` `u` on(`u`.`id` = `i`.`unit_id`)) left join (select `assets`.`item_id` AS `item_id`,count(0) AS `total_assets`,sum(case when `assets`.`asset_status` = 'available' then 1 else 0 end) AS `available_assets` from `assets` group by `assets`.`item_id`) `aa` on(`aa`.`item_id` = `i`.`id`)) left join (select `stock_balances`.`item_id` AS `item_id`,sum(`stock_balances`.`quantity`) AS `total_stock` from `stock_balances` group by `stock_balances`.`item_id`) `qs` on(`qs`.`item_id` = `i`.`id`)) ;

-- --------------------------------------------------------

--
-- Struktur untuk view `vw_library_books`
--
DROP TABLE IF EXISTS `vw_library_books`;

CREATE ALGORITHM=UNDEFINED DEFINER=`seriusndruru_sdnmekarsari08`@`localhost` SQL SECURITY DEFINER VIEW `vw_library_books`  AS SELECT `i`.`id` AS `item_id`, `i`.`item_code` AS `item_code`, `i`.`item_name` AS `title`, `i`.`category_id` AS `category_id`, `c`.`category_name` AS `category_name`, `bd`.`isbn_10` AS `isbn_10`, `bd`.`isbn_13` AS `isbn_13`, `bd`.`publisher_id` AS `publisher_id`, `p`.`publisher_name` AS `publisher_name`, `bd`.`publication_year` AS `publication_year`, `bd`.`edition` AS `edition`, `bd`.`language` AS `language`, `bd`.`page_count` AS `page_count`, `bd`.`classification_code` AS `classification_code`, `bd`.`call_number` AS `call_number`, `bd`.`completion_status` AS `completion_status`, `i`.`status` AS `item_status`, count(`a`.`id`) AS `total_copies`, sum(case when `a`.`asset_status` = 'available' then 1 else 0 end) AS `available_copies`, sum(case when `a`.`asset_status` = 'borrowed' then 1 else 0 end) AS `borrowed_copies`, sum(case when `a`.`asset_status` = 'unprocessed' then 1 else 0 end) AS `unprocessed_copies`, sum(case when `a`.`asset_status` = 'damaged' then 1 else 0 end) AS `damaged_copies`, sum(case when `a`.`asset_status` = 'lost' then 1 else 0 end) AS `lost_copies`, sum(case when `a`.`id` is not null and `a`.`current_shelf_id` is null then 1 else 0 end) AS `copies_without_shelf` FROM ((((`items` `i` join `book_details` `bd` on(`bd`.`item_id` = `i`.`id`)) left join `categories` `c` on(`c`.`id` = `i`.`category_id`)) left join `publishers` `p` on(`p`.`id` = `bd`.`publisher_id`)) left join `assets` `a` on(`a`.`item_id` = `i`.`id`)) WHERE `i`.`item_type` = 'book' GROUP BY `i`.`id`, `i`.`item_code`, `i`.`item_name`, `i`.`category_id`, `c`.`category_name`, `bd`.`isbn_10`, `bd`.`isbn_13`, `bd`.`publisher_id`, `p`.`publisher_name`, `bd`.`publication_year`, `bd`.`edition`, `bd`.`language`, `bd`.`page_count`, `bd`.`classification_code`, `bd`.`call_number`, `bd`.`completion_status`, `i`.`status` ;

-- --------------------------------------------------------

--
-- Struktur untuk view `vw_library_copies`
--
DROP TABLE IF EXISTS `vw_library_copies`;

CREATE ALGORITHM=UNDEFINED DEFINER=`seriusndruru_sdnmekarsari08`@`localhost` SQL SECURITY DEFINER VIEW `vw_library_copies`  AS SELECT `a`.`id` AS `asset_id`, `a`.`asset_code` AS `asset_code`, `a`.`barcode` AS `barcode`, `i`.`id` AS `item_id`, `i`.`item_code` AS `item_code`, `i`.`item_name` AS `title`, `bd`.`isbn_13` AS `isbn_13`, `bd`.`classification_code` AS `classification_code`, `bd`.`call_number` AS `call_number`, `a`.`condition_status` AS `condition_status`, `a`.`asset_status` AS `asset_status`, `a`.`current_location_id` AS `current_location_id`, `l`.`location_name` AS `location_name`, `a`.`current_shelf_id` AS `current_shelf_id`, `s`.`shelf_code` AS `shelf_code`, `s`.`shelf_name` AS `shelf_name`, `a`.`acquisition_date` AS `acquisition_date`, `a`.`acquisition_price` AS `acquisition_price`, `a`.`created_at` AS `created_at`, `a`.`updated_at` AS `updated_at` FROM ((((`assets` `a` join `items` `i` on(`i`.`id` = `a`.`item_id` and `i`.`item_type` = 'book')) join `book_details` `bd` on(`bd`.`item_id` = `i`.`id`)) left join `locations` `l` on(`l`.`id` = `a`.`current_location_id`)) left join `library_shelves` `s` on(`s`.`id` = `a`.`current_shelf_id`)) ;

-- --------------------------------------------------------

--
-- Struktur untuk view `vw_overdue_loans`
--
DROP TABLE IF EXISTS `vw_overdue_loans`;

CREATE ALGORITHM=UNDEFINED DEFINER=`seriusndruru_sdnmekarsari08`@`localhost` SQL SECURITY DEFINER VIEW `vw_overdue_loans`  AS SELECT `li`.`id` AS `loan_item_id`, `l`.`id` AS `loan_id`, `l`.`loan_code` AS `loan_code`, `m`.`id` AS `member_id`, `m`.`member_code` AS `member_code`, `m`.`member_name` AS `member_name`, `a`.`id` AS `asset_id`, `a`.`asset_code` AS `asset_code`, `i`.`id` AS `item_id`, `i`.`item_name` AS `title`, `li`.`borrowed_at` AS `borrowed_at`, `li`.`due_date` AS `due_date`, to_days(curdate()) - to_days(`li`.`due_date`) AS `days_overdue`, (to_days(curdate()) - to_days(`li`.`due_date`)) * cast((select `system_settings`.`setting_value` from `system_settings` where `system_settings`.`setting_key` = 'library.fine_per_day') as decimal(15,2)) AS `estimated_fine` FROM ((((`loan_items` `li` join `loans` `l` on(`l`.`id` = `li`.`loan_id`)) join `members` `m` on(`m`.`id` = `l`.`member_id`)) join `assets` `a` on(`a`.`id` = `li`.`asset_id`)) join `items` `i` on(`i`.`id` = `a`.`item_id`)) WHERE `li`.`return_status` = 'borrowed' AND `li`.`due_date` < curdate() ;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `fk_assets_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assets_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assets_location` FOREIGN KEY (`current_location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assets_shelf` FOREIGN KEY (`current_shelf_id`) REFERENCES `library_shelves` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assets_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assets_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `asset_shelf_history`
--
ALTER TABLE `asset_shelf_history`
  ADD CONSTRAINT `fk_shelf_history_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_shelf_history_new_shelf` FOREIGN KEY (`new_shelf_id`) REFERENCES `library_shelves` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_shelf_history_old_shelf` FOREIGN KEY (`old_shelf_id`) REFERENCES `library_shelves` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_shelf_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `book_authors`
--
ALTER TABLE `book_authors`
  ADD CONSTRAINT `fk_book_authors_author` FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_book_authors_item` FOREIGN KEY (`item_id`) REFERENCES `book_details` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `book_details`
--
ALTER TABLE `book_details`
  ADD CONSTRAINT `fk_book_details_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_book_details_publisher` FOREIGN KEY (`publisher_id`) REFERENCES `publishers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_book_details_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `disposals`
--
ALTER TABLE `disposals`
  ADD CONSTRAINT `fk_disposals_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_disposals_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_disposals_proposed_by` FOREIGN KEY (`proposed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `email_delivery_logs`
--
ALTER TABLE `email_delivery_logs`
  ADD CONSTRAINT `fk_email_delivery_logs_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_email_delivery_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `fine_payments`
--
ALTER TABLE `fine_payments`
  ADD CONSTRAINT `fk_fine_payments_loan_item` FOREIGN KEY (`loan_item_id`) REFERENCES `loan_items` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fine_payments_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `fk_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_items_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_items_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `library_shelves`
--
ALTER TABLE `library_shelves`
  ADD CONSTRAINT `fk_library_shelves_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_library_shelves_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `fk_loans_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_loans_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `loan_items`
--
ALTER TABLE `loan_items`
  ADD CONSTRAINT `fk_loan_items_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_loan_items_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_loan_items_returned_by` FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `loan_requests`
--
ALTER TABLE `loan_requests`
  ADD CONSTRAINT `fk_loan_requests_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_loan_requests_processor` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `loan_request_items`
--
ALTER TABLE `loan_request_items`
  ADD CONSTRAINT `fk_loan_request_items_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_loan_request_items_item` FOREIGN KEY (`item_id`) REFERENCES `book_details` (`item_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_loan_request_items_request` FOREIGN KEY (`loan_request_id`) REFERENCES `loan_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `locations`
--
ALTER TABLE `locations`
  ADD CONSTRAINT `fk_locations_parent` FOREIGN KEY (`parent_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `maintenance_records`
--
ALTER TABLE `maintenance_records`
  ADD CONSTRAINT `fk_maintenance_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_maintenance_handled_by` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_maintenance_reported_by` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `fk_members_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `member_notifications`
--
ALTER TABLE `member_notifications`
  ADD CONSTRAINT `fk_member_notifications_loan_item` FOREIGN KEY (`loan_item_id`) REFERENCES `loan_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_member_notifications_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `public_contact_messages`
--
ALTER TABLE `public_contact_messages`
  ADD CONSTRAINT `fk_public_contact_messages_handler` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `public_damage_reports`
--
ALTER TABLE `public_damage_reports`
  ADD CONSTRAINT `fk_public_damage_reports_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_public_damage_reports_handler` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_public_damage_reports_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_public_damage_reports_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_reservations_item` FOREIGN KEY (`item_id`) REFERENCES `book_details` (`item_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reservations_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reservations_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `stock_balances`
--
ALTER TABLE `stock_balances`
  ADD CONSTRAINT `fk_stock_balances_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_balances_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stock_movements_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_movements_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_movements_from_location` FOREIGN KEY (`from_location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_movements_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_movements_to_location` FOREIGN KEY (`to_location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `stock_opnames`
--
ALTER TABLE `stock_opnames`
  ADD CONSTRAINT `fk_stock_opnames_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_opnames_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_opnames_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `stock_opname_items`
--
ALTER TABLE `stock_opname_items`
  ADD CONSTRAINT `fk_stock_opname_items_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_opname_items_checked_by` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_opname_items_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_opname_items_opname` FOREIGN KEY (`stock_opname_id`) REFERENCES `stock_opnames` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `fk_system_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
