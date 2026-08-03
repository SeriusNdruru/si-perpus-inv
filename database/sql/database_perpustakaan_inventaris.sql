-- ============================================================
-- DATABASE TERINTEGRASI INVENTARIS DAN PERPUSTAKAAN
-- Target: MySQL 8.0+ / MariaDB 10.6+
-- Database: db_perpustakaan_inventaris
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS db_perpustakaan_inventaris
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE db_perpustakaan_inventaris;

-- Hapus view terlebih dahulu agar skrip dapat dijalankan ulang.
DROP VIEW IF EXISTS vw_overdue_loans;
DROP VIEW IF EXISTS vw_library_copies;
DROP VIEW IF EXISTS vw_library_books;
DROP VIEW IF EXISTS vw_inventory_summary;

-- Hapus tabel dari tabel anak ke tabel induk.
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS disposals;
DROP TABLE IF EXISTS stock_opname_items;
DROP TABLE IF EXISTS stock_opnames;
DROP TABLE IF EXISTS maintenance_records;
DROP TABLE IF EXISTS fine_payments;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS loan_items;
DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS members;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS stock_balances;
DROP TABLE IF EXISTS asset_shelf_history;
DROP TABLE IF EXISTS assets;
DROP TABLE IF EXISTS library_shelves;
DROP TABLE IF EXISTS book_authors;
DROP TABLE IF EXISTS book_details;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS authors;
DROP TABLE IF EXISTS publishers;
DROP TABLE IF EXISTS locations;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS units;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 1. AUTENTIKASI DAN HAK AKSES
-- ============================================================

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_code VARCHAR(50) NOT NULL UNIQUE,
    role_name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    email VARCHAR(150) NULL UNIQUE,
    email_verified_at DATETIME NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NULL,
    status ENUM('active', 'inactive', 'locked') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_status (status)
) ENGINE=InnoDB;

CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 2. DATA MASTER BERSAMA
-- ============================================================

CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    category_code VARCHAR(50) NOT NULL UNIQUE,
    category_name VARCHAR(150) NOT NULL,
    scope ENUM('inventory', 'library', 'both') NOT NULL DEFAULT 'both',
    description VARCHAR(255) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_categories_parent
        FOREIGN KEY (parent_id) REFERENCES categories(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_categories_parent (parent_id),
    INDEX idx_categories_scope_status (scope, status)
) ENGINE=InnoDB;

CREATE TABLE units (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unit_code VARCHAR(30) NOT NULL UNIQUE,
    unit_name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE suppliers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_code VARCHAR(50) NOT NULL UNIQUE,
    supplier_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    address TEXT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_suppliers_name (supplier_name)
) ENGINE=InnoDB;

CREATE TABLE locations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    location_code VARCHAR(50) NOT NULL UNIQUE,
    location_name VARCHAR(150) NOT NULL,
    location_type ENUM('building', 'floor', 'room', 'warehouse', 'cabinet', 'other') NOT NULL DEFAULT 'room',
    description VARCHAR(255) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_locations_parent
        FOREIGN KEY (parent_id) REFERENCES locations(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_locations_parent (parent_id),
    INDEX idx_locations_type_status (location_type, status)
) ENGINE=InnoDB;

CREATE TABLE publishers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    publisher_name VARCHAR(180) NOT NULL UNIQUE,
    city VARCHAR(100) NULL,
    address TEXT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE authors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_name VARCHAR(180) NOT NULL,
    biography TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_authors_name (author_name)
) ENGINE=InnoDB;

-- ============================================================
-- 3. BARANG UTAMA DAN DETAIL BUKU
-- ============================================================

CREATE TABLE items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(60) NOT NULL UNIQUE,
    item_name VARCHAR(220) NOT NULL,
    item_type ENUM('book', 'equipment', 'electronic', 'furniture', 'consumable', 'other') NOT NULL,
    tracking_type ENUM('asset', 'quantity') NOT NULL DEFAULT 'asset',
    category_id BIGINT UNSIGNED NULL,
    unit_id BIGINT UNSIGNED NOT NULL,
    description TEXT NULL,
    image_path VARCHAR(255) NULL,
    minimum_stock DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_items_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_items_unit
        FOREIGN KEY (unit_id) REFERENCES units(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_items_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_items_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_books_use_asset_tracking
        CHECK (item_type <> 'book' OR tracking_type = 'asset'),
    INDEX idx_items_name (item_name),
    INDEX idx_items_type_status (item_type, status),
    INDEX idx_items_category (category_id)
) ENGINE=InnoDB;

CREATE TABLE book_details (
    item_id BIGINT UNSIGNED PRIMARY KEY,
    isbn_10 VARCHAR(20) NULL UNIQUE,
    isbn_13 VARCHAR(20) NULL UNIQUE,
    publisher_id BIGINT UNSIGNED NULL,
    publication_year SMALLINT UNSIGNED NULL,
    grade_level VARCHAR(20) NOT NULL DEFAULT 'umum',
    edition VARCHAR(80) NULL,
    language VARCHAR(50) NULL DEFAULT 'Indonesia',
    page_count INT UNSIGNED NULL,
    classification_code VARCHAR(50) NULL,
    call_number VARCHAR(80) NULL,
    cover_path VARCHAR(255) NULL,
    catalog_notes TEXT NULL,
    completion_status ENUM('incomplete', 'complete', 'verified') NOT NULL DEFAULT 'incomplete',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_book_details_item
        FOREIGN KEY (item_id) REFERENCES items(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_book_details_publisher
        FOREIGN KEY (publisher_id) REFERENCES publishers(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_book_details_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_publication_year
        CHECK (publication_year IS NULL OR publication_year BETWEEN 1000 AND 2200),
    CONSTRAINT chk_book_grade_level
        CHECK (grade_level IN ('umum', 'kelas_1', 'kelas_2', 'kelas_3', 'kelas_4', 'kelas_5', 'kelas_6')),
    INDEX idx_book_details_publisher (publisher_id),
    INDEX idx_book_details_completion (completion_status),
    INDEX idx_book_details_grade_level (grade_level),
    INDEX idx_book_details_classification (classification_code)
) ENGINE=InnoDB;

CREATE TABLE book_authors (
    item_id BIGINT UNSIGNED NOT NULL,
    author_id BIGINT UNSIGNED NOT NULL,
    author_role ENUM('author', 'editor', 'translator', 'illustrator') NOT NULL DEFAULT 'author',
    author_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (item_id, author_id, author_role),
    CONSTRAINT fk_book_authors_item
        FOREIGN KEY (item_id) REFERENCES book_details(item_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_book_authors_author
        FOREIGN KEY (author_id) REFERENCES authors(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_book_authors_order (item_id, author_order)
) ENGINE=InnoDB;

-- ============================================================
-- 4. RAK PERPUSTAKAAN DAN UNIT FISIK BARANG
-- ============================================================

CREATE TABLE library_shelves (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shelf_code VARCHAR(50) NOT NULL UNIQUE,
    shelf_name VARCHAR(150) NOT NULL,
    location_id BIGINT UNSIGNED NULL,
    classification_range VARCHAR(100) NULL,
    capacity INT UNSIGNED NULL,
    description VARCHAR(255) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_library_shelves_location
        FOREIGN KEY (location_id) REFERENCES locations(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_library_shelves_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_shelves_location_status (location_id, status)
) ENGINE=InnoDB;

CREATE TABLE assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT UNSIGNED NOT NULL,
    asset_code VARCHAR(90) NOT NULL UNIQUE,
    barcode VARCHAR(100) NOT NULL UNIQUE,
    serial_number VARCHAR(120) NULL,
    condition_status ENUM('good', 'fair', 'damaged', 'lost') NOT NULL DEFAULT 'good',
    asset_status ENUM('unprocessed', 'available', 'borrowed', 'reserved', 'maintenance', 'damaged', 'lost', 'disposed') NOT NULL DEFAULT 'available',
    acquisition_date DATE NULL,
    acquisition_source ENUM('purchase', 'donation', 'grant', 'transfer', 'other') NOT NULL DEFAULT 'purchase',
    acquisition_price DECIMAL(15,2) UNSIGNED NULL,
    supplier_id BIGINT UNSIGNED NULL,
    current_location_id BIGINT UNSIGNED NULL,
    current_shelf_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_assets_item
        FOREIGN KEY (item_id) REFERENCES items(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_assets_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_assets_location
        FOREIGN KEY (current_location_id) REFERENCES locations(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_assets_shelf
        FOREIGN KEY (current_shelf_id) REFERENCES library_shelves(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_assets_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_assets_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_assets_item (item_id),
    INDEX idx_assets_status (asset_status),
    INDEX idx_assets_condition (condition_status),
    INDEX idx_assets_location (current_location_id),
    INDEX idx_assets_shelf (current_shelf_id)
) ENGINE=InnoDB;

CREATE TABLE asset_shelf_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id BIGINT UNSIGNED NOT NULL,
    old_shelf_id BIGINT UNSIGNED NULL,
    new_shelf_id BIGINT UNSIGNED NULL,
    changed_by BIGINT UNSIGNED NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes VARCHAR(255) NULL,
    CONSTRAINT fk_shelf_history_asset
        FOREIGN KEY (asset_id) REFERENCES assets(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_shelf_history_old_shelf
        FOREIGN KEY (old_shelf_id) REFERENCES library_shelves(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_shelf_history_new_shelf
        FOREIGN KEY (new_shelf_id) REFERENCES library_shelves(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_shelf_history_user
        FOREIGN KEY (changed_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_shelf_history_asset_date (asset_id, changed_at)
) ENGINE=InnoDB;

-- ============================================================
-- 5. STOK DAN PERGERAKAN INVENTARIS
-- ============================================================

CREATE TABLE stock_balances (
    item_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id, location_id),
    CONSTRAINT fk_stock_balances_item
        FOREIGN KEY (item_id) REFERENCES items(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_stock_balances_location
        FOREIGN KEY (location_id) REFERENCES locations(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    movement_code VARCHAR(70) NOT NULL UNIQUE,
    item_id BIGINT UNSIGNED NOT NULL,
    asset_id BIGINT UNSIGNED NULL,
    movement_type ENUM(
        'receipt', 'issue', 'transfer', 'adjustment_in', 'adjustment_out',
        'loan', 'return', 'maintenance_out', 'maintenance_in', 'disposal', 'opname'
    ) NOT NULL,
    quantity DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 1,
    from_location_id BIGINT UNSIGNED NULL,
    to_location_id BIGINT UNSIGNED NULL,
    reference_type VARCHAR(60) NULL,
    reference_id BIGINT UNSIGNED NULL,
    movement_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_movements_item
        FOREIGN KEY (item_id) REFERENCES items(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_stock_movements_asset
        FOREIGN KEY (asset_id) REFERENCES assets(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_stock_movements_from_location
        FOREIGN KEY (from_location_id) REFERENCES locations(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_stock_movements_to_location
        FOREIGN KEY (to_location_id) REFERENCES locations(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_stock_movements_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_stock_movement_quantity CHECK (quantity > 0),
    INDEX idx_stock_movements_item_date (item_id, movement_date),
    INDEX idx_stock_movements_asset_date (asset_id, movement_date),
    INDEX idx_stock_movements_reference (reference_type, reference_id)
) ENGINE=InnoDB;

-- ============================================================
-- 6. ANGGOTA DAN SIRKULASI PERPUSTAKAAN
-- ============================================================

CREATE TABLE members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_code VARCHAR(60) NOT NULL UNIQUE,
    user_id BIGINT UNSIGNED NULL UNIQUE,
    member_name VARCHAR(180) NOT NULL,
    member_type ENUM('student', 'teacher', 'staff', 'public') NOT NULL DEFAULT 'student',
    identity_number VARCHAR(80) NULL UNIQUE,
    department VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    address TEXT NULL,
    join_date DATE NOT NULL,
    expiry_date DATE NULL,
    status ENUM('active', 'suspended', 'inactive', 'expired') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_members_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_members_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_members_name (member_name),
    INDEX idx_members_type_status (member_type, status)
) ENGINE=InnoDB;

CREATE TABLE loans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_code VARCHAR(70) NOT NULL UNIQUE,
    member_id BIGINT UNSIGNED NOT NULL,
    loan_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    default_due_date DATE NOT NULL,
    status ENUM('active', 'completed', 'overdue', 'cancelled') NOT NULL DEFAULT 'active',
    processed_by BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_loans_member
        FOREIGN KEY (member_id) REFERENCES members(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_loans_processed_by
        FOREIGN KEY (processed_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_loans_member_date (member_id, loan_date),
    INDEX idx_loans_status_due (status, default_due_date)
) ENGINE=InnoDB;

CREATE TABLE loan_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_id BIGINT UNSIGNED NOT NULL,
    asset_id BIGINT UNSIGNED NOT NULL,
    borrowed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    due_date DATE NOT NULL,
    condition_out ENUM('good', 'fair', 'damaged') NOT NULL DEFAULT 'good',
    returned_at DATETIME NULL,
    condition_in ENUM('good', 'fair', 'damaged', 'lost') NULL,
    return_status ENUM('borrowed', 'returned', 'damaged', 'lost') NOT NULL DEFAULT 'borrowed',
    fine_amount DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    return_notes TEXT NULL,
    returned_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_loan_items_loan
        FOREIGN KEY (loan_id) REFERENCES loans(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_loan_items_asset
        FOREIGN KEY (asset_id) REFERENCES assets(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_loan_items_returned_by
        FOREIGN KEY (returned_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_loan_asset (loan_id, asset_id),
    INDEX idx_loan_items_asset_status (asset_id, return_status),
    INDEX idx_loan_items_due_status (due_date, return_status)
) ENGINE=InnoDB;

CREATE TABLE reservations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_code VARCHAR(70) NOT NULL UNIQUE,
    member_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    reservation_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    queue_number INT UNSIGNED NULL,
    status ENUM('waiting', 'ready', 'completed', 'cancelled', 'expired') NOT NULL DEFAULT 'waiting',
    processed_by BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reservations_member
        FOREIGN KEY (member_id) REFERENCES members(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reservations_item
        FOREIGN KEY (item_id) REFERENCES book_details(item_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reservations_processed_by
        FOREIGN KEY (processed_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_reservations_item_status (item_id, status),
    INDEX idx_reservations_member_status (member_id, status)
) ENGINE=InnoDB;

CREATE TABLE fine_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_code VARCHAR(70) NOT NULL UNIQUE,
    loan_item_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) UNSIGNED NOT NULL,
    payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    payment_method ENUM('cash', 'transfer', 'other') NOT NULL DEFAULT 'cash',
    received_by BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fine_payments_loan_item
        FOREIGN KEY (loan_item_id) REFERENCES loan_items(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fine_payments_received_by
        FOREIGN KEY (received_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_fine_payment_amount CHECK (amount > 0),
    INDEX idx_fine_payments_loan_item (loan_item_id),
    INDEX idx_fine_payments_date (payment_date)
) ENGINE=InnoDB;

-- ============================================================
-- 7. PEMELIHARAAN, OPNAME, DAN PENGHAPUSAN
-- ============================================================

CREATE TABLE maintenance_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    maintenance_code VARCHAR(70) NOT NULL UNIQUE,
    asset_id BIGINT UNSIGNED NOT NULL,
    reported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    issue_description TEXT NOT NULL,
    action_taken TEXT NULL,
    cost DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    vendor_name VARCHAR(180) NULL,
    status ENUM('reported', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'reported',
    reported_by BIGINT UNSIGNED NULL,
    handled_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_maintenance_asset
        FOREIGN KEY (asset_id) REFERENCES assets(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_reported_by
        FOREIGN KEY (reported_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_maintenance_handled_by
        FOREIGN KEY (handled_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_maintenance_asset_status (asset_id, status)
) ENGINE=InnoDB;

CREATE TABLE stock_opnames (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opname_code VARCHAR(70) NOT NULL UNIQUE,
    location_id BIGINT UNSIGNED NULL,
    opname_date DATE NOT NULL,
    status ENUM('draft', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_opnames_location
        FOREIGN KEY (location_id) REFERENCES locations(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_stock_opnames_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_stock_opnames_approved_by
        FOREIGN KEY (approved_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_stock_opnames_date_status (opname_date, status)
) ENGINE=InnoDB;

CREATE TABLE stock_opname_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_opname_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    asset_id BIGINT UNSIGNED NULL,
    expected_quantity DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    actual_quantity DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    difference_quantity DECIMAL(15,2) NOT NULL DEFAULT 0,
    finding_status ENUM('matched', 'surplus', 'shortage', 'damaged', 'missing') NOT NULL DEFAULT 'matched',
    notes TEXT NULL,
    checked_by BIGINT UNSIGNED NULL,
    checked_at DATETIME NULL,
    CONSTRAINT fk_stock_opname_items_opname
        FOREIGN KEY (stock_opname_id) REFERENCES stock_opnames(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_stock_opname_items_item
        FOREIGN KEY (item_id) REFERENCES items(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_stock_opname_items_asset
        FOREIGN KEY (asset_id) REFERENCES assets(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_stock_opname_items_checked_by
        FOREIGN KEY (checked_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_stock_opname_items_opname_item (stock_opname_id, item_id)
) ENGINE=InnoDB;

CREATE TABLE disposals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    disposal_code VARCHAR(70) NOT NULL UNIQUE,
    asset_id BIGINT UNSIGNED NOT NULL UNIQUE,
    reason TEXT NOT NULL,
    proposed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    disposed_at DATETIME NULL,
    disposal_method ENUM('destroyed', 'sold', 'donated', 'returned', 'other') NULL,
    status ENUM('proposed', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'proposed',
    proposed_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_disposals_asset
        FOREIGN KEY (asset_id) REFERENCES assets(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_disposals_proposed_by
        FOREIGN KEY (proposed_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_disposals_approved_by
        FOREIGN KEY (approved_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 8. PENGATURAN DAN AUDIT
-- ============================================================

CREATE TABLE system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    value_type ENUM('string', 'integer', 'decimal', 'boolean', 'json') NOT NULL DEFAULT 'string',
    description VARCHAR(255) NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_system_settings_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action ENUM('login', 'logout', 'insert', 'update', 'delete', 'approve', 'export', 'other') NOT NULL,
    module_name VARCHAR(80) NOT NULL,
    table_name VARCHAR(80) NULL,
    record_id BIGINT UNSIGNED NULL,
    old_data JSON NULL,
    new_data JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_audit_logs_user_date (user_id, created_at),
    INDEX idx_audit_logs_module_date (module_name, created_at),
    INDEX idx_audit_logs_record (table_name, record_id)
) ENGINE=InnoDB;

CREATE TABLE email_delivery_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    member_id BIGINT UNSIGNED NULL,
    recipient_email VARCHAR(150) NOT NULL,
    mail_type VARCHAR(80) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    delivery_status ENUM('sent', 'failed') NOT NULL,
    reference_type VARCHAR(80) NULL,
    reference_id BIGINT UNSIGNED NULL,
    error_message TEXT NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_email_delivery_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_email_delivery_logs_member
        FOREIGN KEY (member_id) REFERENCES members(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_email_delivery_status_date (delivery_status, created_at),
    INDEX idx_email_delivery_recipient (recipient_email, created_at),
    INDEX idx_email_delivery_reference (reference_type, reference_id)
) ENGINE=InnoDB;


-- ============================================================
-- 9. DATA AWAL
-- ============================================================

INSERT INTO roles (role_code, role_name, description) VALUES
('SUPER_ADMIN', 'Super Admin', 'Mengelola seluruh modul dan pengaturan sistem.'),
('INVENTORY_ADMIN', 'Admin Inventaris', 'Mengelola barang, unit aset, stok, opname, dan penghapusan.'),
('LIBRARY_ADMIN', 'Admin Perpustakaan', 'Mengelola katalog buku, rak, anggota, dan transaksi perpustakaan.'),
('MANAGER', 'Pimpinan', 'Melihat dashboard dan laporan.'),
('MEMBER', 'Anggota', 'Mengakses katalog dan data peminjaman pribadi.');

INSERT INTO units (unit_code, unit_name, description) VALUES
('PCS', 'Pcs', 'Satuan barang satuan.'),
('UNIT', 'Unit', 'Satuan unit inventaris.'),
('COPY', 'Eksemplar', 'Satuan untuk buku.'),
('BOX', 'Kotak', 'Satuan barang dalam kotak.'),
('SET', 'Set', 'Satuan perangkat dalam satu set.');

INSERT INTO categories (category_code, category_name, scope, description) VALUES
('BOOK', 'Buku', 'both', 'Kategori utama untuk buku perpustakaan.'),
('EQUIPMENT', 'Peralatan', 'inventory', 'Peralatan umum.'),
('ELECTRONIC', 'Elektronik', 'inventory', 'Barang elektronik.'),
('FURNITURE', 'Furnitur', 'inventory', 'Meja, kursi, lemari, dan furnitur lain.'),
('CONSUMABLE', 'Barang Habis Pakai', 'inventory', 'Barang yang dicatat berdasarkan jumlah stok.'),
('OTHER', 'Lainnya', 'both', 'Kategori umum lainnya.');

INSERT INTO system_settings (setting_key, setting_value, value_type, description) VALUES
('library.default_loan_days', '7', 'integer', 'Lama peminjaman standar dalam hari.'),
('library.max_active_loans', '3', 'integer', 'Jumlah maksimal buku yang dapat dipinjam anggota.'),
('library.fine_per_day', '1000', 'decimal', 'Denda keterlambatan per hari untuk setiap eksemplar.'),
('library.reservation_hold_days', '2', 'integer', 'Lama penyimpanan buku yang berstatus siap diambil dalam hari.'),
('library.max_active_reservations', '3', 'integer', 'Jumlah maksimal reservasi aktif untuk setiap anggota.'),
('library.allow_incomplete_book_loan', 'false', 'boolean', 'Izin peminjaman buku yang data katalognya belum lengkap.'),
('inventory.asset_code_separator', '-', 'string', 'Pemisah kode barang dan nomor eksemplar.'),
('application.name', 'Sistem Inventaris dan Perpustakaan', 'string', 'Nama aplikasi.');

-- ============================================================
-- 10. TRIGGER VALIDASI DAN OTOMATISASI
-- ============================================================

DELIMITER $$

DROP TRIGGER IF EXISTS trg_items_before_update$$
CREATE TRIGGER trg_items_before_update
BEFORE UPDATE ON items
FOR EACH ROW
BEGIN
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
END$$

DROP TRIGGER IF EXISTS trg_items_after_insert_book$$
CREATE TRIGGER trg_items_after_insert_book
AFTER INSERT ON items
FOR EACH ROW
BEGIN
    IF NEW.item_type = 'book' THEN
        INSERT INTO book_details (item_id, completion_status)
        VALUES (NEW.id, 'incomplete');
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_items_after_update_book$$
CREATE TRIGGER trg_items_after_update_book
AFTER UPDATE ON items
FOR EACH ROW
BEGIN
    IF NEW.item_type = 'book' AND OLD.item_type <> 'book' THEN
        INSERT IGNORE INTO book_details (item_id, completion_status)
        VALUES (NEW.id, 'incomplete');
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_book_details_before_insert$$
CREATE TRIGGER trg_book_details_before_insert
BEFORE INSERT ON book_details
FOR EACH ROW
BEGIN
    DECLARE v_item_type VARCHAR(30);

    SELECT item_type INTO v_item_type
    FROM items
    WHERE id = NEW.item_id;

    IF v_item_type IS NULL OR v_item_type <> 'book' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'book_details hanya boleh dibuat untuk item bertipe book.';
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_assets_before_insert$$
CREATE TRIGGER trg_assets_before_insert
BEFORE INSERT ON assets
FOR EACH ROW
BEGIN
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
END$$

DROP TRIGGER IF EXISTS trg_assets_before_update$$
CREATE TRIGGER trg_assets_before_update
BEFORE UPDATE ON assets
FOR EACH ROW
BEGIN
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
END$$

DROP TRIGGER IF EXISTS trg_assets_after_update_shelf$$
CREATE TRIGGER trg_assets_after_update_shelf
AFTER UPDATE ON assets
FOR EACH ROW
BEGIN
    IF NOT (OLD.current_shelf_id <=> NEW.current_shelf_id) THEN
        INSERT INTO asset_shelf_history (
            asset_id, old_shelf_id, new_shelf_id, changed_by, notes
        ) VALUES (
            NEW.id, OLD.current_shelf_id, NEW.current_shelf_id, @app_user_id,
            'Perubahan rak buku.'
        );
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_loan_items_before_insert$$
CREATE TRIGGER trg_loan_items_before_insert
BEFORE INSERT ON loan_items
FOR EACH ROW
BEGIN
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
END$$

DROP TRIGGER IF EXISTS trg_loan_items_after_insert$$
CREATE TRIGGER trg_loan_items_after_insert
AFTER INSERT ON loan_items
FOR EACH ROW
BEGIN
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
END$$

DROP TRIGGER IF EXISTS trg_loan_items_after_update$$
CREATE TRIGGER trg_loan_items_after_update
AFTER UPDATE ON loan_items
FOR EACH ROW
BEGIN
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
END$$

DELIMITER ;

-- ============================================================
-- 11. STORED PROCEDURE OPERASIONAL
-- ============================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_create_item_with_stock$$
CREATE PROCEDURE sp_create_item_with_stock(
    IN p_item_code VARCHAR(60),
    IN p_item_name VARCHAR(220),
    IN p_item_type VARCHAR(30),
    IN p_tracking_type VARCHAR(20),
    IN p_category_id BIGINT UNSIGNED,
    IN p_unit_id BIGINT UNSIGNED,
    IN p_description TEXT,
    IN p_quantity DECIMAL(15,2),
    IN p_acquisition_date DATE,
    IN p_acquisition_source VARCHAR(30),
    IN p_acquisition_price DECIMAL(15,2),
    IN p_supplier_id BIGINT UNSIGNED,
    IN p_location_id BIGINT UNSIGNED,
    IN p_created_by BIGINT UNSIGNED
)
BEGIN
    DECLARE v_item_id BIGINT UNSIGNED;
    DECLARE v_counter INT DEFAULT 1;
    DECLARE v_asset_code VARCHAR(90);
    DECLARE v_barcode VARCHAR(100);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF p_quantity IS NULL OR p_quantity <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Jumlah awal harus lebih besar dari nol.';
    END IF;

    IF p_item_type = 'book' AND p_tracking_type <> 'asset' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Buku wajib menggunakan tracking_type asset.';
    END IF;

    IF p_tracking_type = 'asset' AND p_quantity <> FLOOR(p_quantity) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Jumlah item berbasis aset harus berupa bilangan bulat.';
    END IF;

    START TRANSACTION;

    SET @app_user_id = p_created_by;

    INSERT INTO items (
        item_code, item_name, item_type, tracking_type,
        category_id, unit_id, description, created_by, updated_by
    ) VALUES (
        p_item_code, p_item_name, p_item_type, p_tracking_type,
        p_category_id, p_unit_id, p_description, p_created_by, p_created_by
    );

    SET v_item_id = LAST_INSERT_ID();

    IF p_tracking_type = 'asset' THEN
        WHILE v_counter <= p_quantity DO
            SET v_asset_code = CONCAT(p_item_code, '-', LPAD(v_counter, 3, '0'));
            SET v_barcode = v_asset_code;

            INSERT INTO assets (
                item_id, asset_code, barcode, acquisition_date,
                acquisition_source, acquisition_price, supplier_id,
                current_location_id, created_by, updated_by
            ) VALUES (
                v_item_id, v_asset_code, v_barcode, p_acquisition_date,
                p_acquisition_source, p_acquisition_price, p_supplier_id,
                p_location_id, p_created_by, p_created_by
            );

            INSERT INTO stock_movements (
                movement_code, item_id, asset_id, movement_type, quantity,
                to_location_id, reference_type, reference_id, created_by, notes
            ) VALUES (
                CONCAT('MOV-', REPLACE(UUID(), '-', '')),
                v_item_id, LAST_INSERT_ID(), 'receipt', 1,
                p_location_id, 'initial_stock', v_item_id, p_created_by,
                'Penerimaan unit aset awal.'
            );

            SET v_counter = v_counter + 1;
        END WHILE;
    ELSE
        IF p_location_id IS NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Lokasi wajib diisi untuk item berbasis jumlah.';
        END IF;

        INSERT INTO stock_balances (item_id, location_id, quantity)
        VALUES (v_item_id, p_location_id, p_quantity);

        INSERT INTO stock_movements (
            movement_code, item_id, movement_type, quantity,
            to_location_id, reference_type, reference_id, created_by, notes
        ) VALUES (
            CONCAT('MOV-', REPLACE(UUID(), '-', '')),
            v_item_id, 'receipt', p_quantity,
            p_location_id, 'initial_stock', v_item_id, p_created_by,
            'Penerimaan stok awal berbasis jumlah.'
        );
    END IF;

    COMMIT;

    SELECT v_item_id AS item_id;
END$$

DROP PROCEDURE IF EXISTS sp_add_stock$$
CREATE PROCEDURE sp_add_stock(
    IN p_item_id BIGINT UNSIGNED,
    IN p_quantity DECIMAL(15,2),
    IN p_location_id BIGINT UNSIGNED,
    IN p_acquisition_date DATE,
    IN p_acquisition_source VARCHAR(30),
    IN p_acquisition_price DECIMAL(15,2),
    IN p_supplier_id BIGINT UNSIGNED,
    IN p_created_by BIGINT UNSIGNED
)
BEGIN
    DECLARE v_tracking_type VARCHAR(20);
    DECLARE v_item_code VARCHAR(60);
    DECLARE v_last_sequence INT DEFAULT 0;
    DECLARE v_counter INT DEFAULT 1;
    DECLARE v_sequence INT;
    DECLARE v_asset_id BIGINT UNSIGNED;
    DECLARE v_asset_code VARCHAR(90);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    SELECT tracking_type, item_code
    INTO v_tracking_type, v_item_code
    FROM items
    WHERE id = p_item_id AND status = 'active';

    IF v_tracking_type IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Item aktif tidak ditemukan.';
    END IF;

    IF p_quantity IS NULL OR p_quantity <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Jumlah tambahan harus lebih besar dari nol.';
    END IF;

    IF v_tracking_type = 'asset' AND p_quantity <> FLOOR(p_quantity) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Jumlah aset tambahan harus berupa bilangan bulat.';
    END IF;

    START TRANSACTION;
    SET @app_user_id = p_created_by;

    IF v_tracking_type = 'asset' THEN
        SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(asset_code, '-', -1) AS UNSIGNED)), 0)
        INTO v_last_sequence
        FROM assets
        WHERE item_id = p_item_id;

        WHILE v_counter <= p_quantity DO
            SET v_sequence = v_last_sequence + v_counter;
            SET v_asset_code = CONCAT(v_item_code, '-', LPAD(v_sequence, 3, '0'));

            INSERT INTO assets (
                item_id, asset_code, barcode, acquisition_date,
                acquisition_source, acquisition_price, supplier_id,
                current_location_id, created_by, updated_by
            ) VALUES (
                p_item_id, v_asset_code, v_asset_code, p_acquisition_date,
                p_acquisition_source, p_acquisition_price, p_supplier_id,
                p_location_id, p_created_by, p_created_by
            );

            SET v_asset_id = LAST_INSERT_ID();

            INSERT INTO stock_movements (
                movement_code, item_id, asset_id, movement_type, quantity,
                to_location_id, reference_type, reference_id, created_by, notes
            ) VALUES (
                CONCAT('MOV-', REPLACE(UUID(), '-', '')),
                p_item_id, v_asset_id, 'receipt', 1,
                p_location_id, 'stock_addition', p_item_id, p_created_by,
                'Penambahan unit aset.'
            );

            SET v_counter = v_counter + 1;
        END WHILE;
    ELSE
        IF p_location_id IS NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Lokasi wajib diisi untuk stok berbasis jumlah.';
        END IF;

        INSERT INTO stock_balances (item_id, location_id, quantity)
        VALUES (p_item_id, p_location_id, p_quantity)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity);

        INSERT INTO stock_movements (
            movement_code, item_id, movement_type, quantity,
            to_location_id, reference_type, reference_id, created_by, notes
        ) VALUES (
            CONCAT('MOV-', REPLACE(UUID(), '-', '')),
            p_item_id, 'receipt', p_quantity,
            p_location_id, 'stock_addition', p_item_id, p_created_by,
            'Penambahan stok berbasis jumlah.'
        );
    END IF;

    COMMIT;
END$$

DROP PROCEDURE IF EXISTS sp_assign_book_shelf$$
CREATE PROCEDURE sp_assign_book_shelf(
    IN p_asset_id BIGINT UNSIGNED,
    IN p_shelf_id BIGINT UNSIGNED,
    IN p_user_id BIGINT UNSIGNED,
    IN p_notes VARCHAR(255)
)
BEGIN
    DECLARE v_item_type VARCHAR(30);
    DECLARE v_completion_status VARCHAR(30);
    DECLARE v_condition_status VARCHAR(30);
    DECLARE v_location_id BIGINT UNSIGNED;
    DECLARE v_shelf_count INT DEFAULT 0;

    SELECT i.item_type, bd.completion_status, a.condition_status
    INTO v_item_type, v_completion_status, v_condition_status
    FROM assets a
    JOIN items i ON i.id = a.item_id
    LEFT JOIN book_details bd ON bd.item_id = i.id
    WHERE a.id = p_asset_id;

    IF v_item_type IS NULL OR v_item_type <> 'book' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Rak perpustakaan hanya dapat diberikan kepada aset buku.';
    END IF;

    SELECT COUNT(*), MAX(location_id)
    INTO v_shelf_count, v_location_id
    FROM library_shelves
    WHERE id = p_shelf_id AND status = 'active';

    IF v_shelf_count = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Rak tidak ditemukan atau tidak aktif.';
    END IF;

    SET @app_user_id = p_user_id;

    UPDATE assets
    SET current_shelf_id = p_shelf_id,
        current_location_id = COALESCE(v_location_id, current_location_id),
        asset_status = CASE
            WHEN v_completion_status IN ('complete', 'verified')
                 AND v_condition_status IN ('good', 'fair')
            THEN 'available'
            ELSE 'unprocessed'
        END,
        updated_by = p_user_id,
        notes = CASE
            WHEN p_notes IS NULL OR p_notes = '' THEN notes
            WHEN notes IS NULL OR notes = '' THEN p_notes
            ELSE CONCAT(notes, '\n', p_notes)
        END
    WHERE id = p_asset_id;
END$$

DROP PROCEDURE IF EXISTS sp_create_loan$$
CREATE PROCEDURE sp_create_loan(
    IN p_member_id BIGINT UNSIGNED,
    IN p_processed_by BIGINT UNSIGNED,
    IN p_notes TEXT
)
BEGIN
    DECLARE v_member_status VARCHAR(30);
    DECLARE v_default_days INT DEFAULT 7;
    DECLARE v_loan_id BIGINT UNSIGNED;
    DECLARE v_loan_code VARCHAR(70);

    SELECT status INTO v_member_status
    FROM members
    WHERE id = p_member_id;

    IF v_member_status IS NULL OR v_member_status <> 'active' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Anggota tidak ditemukan atau tidak aktif.';
    END IF;

    SELECT CAST(setting_value AS UNSIGNED)
    INTO v_default_days
    FROM system_settings
    WHERE setting_key = 'library.default_loan_days';

    SET v_loan_code = CONCAT('LOAN-', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'), '-', LPAD(FLOOR(RAND() * 1000), 3, '0'));

    INSERT INTO loans (
        loan_code, member_id, loan_date, default_due_date,
        status, processed_by, notes
    ) VALUES (
        v_loan_code, p_member_id, NOW(), DATE_ADD(CURDATE(), INTERVAL v_default_days DAY),
        'active', p_processed_by, p_notes
    );

    SET v_loan_id = LAST_INSERT_ID();
    SELECT v_loan_id AS loan_id, v_loan_code AS loan_code;
END$$

DROP PROCEDURE IF EXISTS sp_add_loan_item$$
CREATE PROCEDURE sp_add_loan_item(
    IN p_loan_id BIGINT UNSIGNED,
    IN p_asset_id BIGINT UNSIGNED,
    IN p_condition_out VARCHAR(20),
    IN p_processed_by BIGINT UNSIGNED
)
BEGIN
    DECLARE v_due_date DATE;

    SELECT default_due_date INTO v_due_date
    FROM loans
    WHERE id = p_loan_id;

    IF v_due_date IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Transaksi peminjaman tidak ditemukan.';
    END IF;

    SET @app_user_id = p_processed_by;

    INSERT INTO loan_items (
        loan_id, asset_id, due_date, condition_out
    ) VALUES (
        p_loan_id, p_asset_id, v_due_date,
        COALESCE(NULLIF(p_condition_out, ''), 'good')
    );
END$$

DROP PROCEDURE IF EXISTS sp_return_book$$
CREATE PROCEDURE sp_return_book(
    IN p_loan_item_id BIGINT UNSIGNED,
    IN p_return_status VARCHAR(20),
    IN p_condition_in VARCHAR(20),
    IN p_returned_by BIGINT UNSIGNED,
    IN p_return_notes TEXT
)
BEGIN
    DECLARE v_due_date DATE;
    DECLARE v_current_status VARCHAR(20);
    DECLARE v_days_late INT DEFAULT 0;
    DECLARE v_fine_per_day DECIMAL(15,2) DEFAULT 0;
    DECLARE v_fine_amount DECIMAL(15,2) DEFAULT 0;

    SELECT due_date, return_status
    INTO v_due_date, v_current_status
    FROM loan_items
    WHERE id = p_loan_item_id;

    IF v_current_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Detail peminjaman tidak ditemukan.';
    END IF;

    IF v_current_status <> 'borrowed' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Buku ini sudah diproses pengembaliannya.';
    END IF;

    IF p_return_status NOT IN ('returned', 'damaged', 'lost') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Status pengembalian tidak valid.';
    END IF;

    SELECT CAST(setting_value AS DECIMAL(15,2))
    INTO v_fine_per_day
    FROM system_settings
    WHERE setting_key = 'library.fine_per_day';

    SET v_days_late = GREATEST(DATEDIFF(CURDATE(), v_due_date), 0);
    SET v_fine_amount = v_days_late * v_fine_per_day;

    SET @app_user_id = p_returned_by;

    UPDATE loan_items
    SET returned_at = NOW(),
        condition_in = CASE
            WHEN p_return_status = 'lost' THEN 'lost'
            WHEN p_return_status = 'damaged' THEN 'damaged'
            ELSE COALESCE(NULLIF(p_condition_in, ''), condition_out)
        END,
        return_status = p_return_status,
        fine_amount = v_fine_amount,
        return_notes = p_return_notes,
        returned_by = p_returned_by
    WHERE id = p_loan_item_id;

    SELECT v_days_late AS days_late, v_fine_amount AS fine_amount;
END$$

DELIMITER ;

-- ============================================================
-- 12. VIEW UNTUK DASHBOARD
-- ============================================================

CREATE OR REPLACE VIEW vw_library_books AS
SELECT
    i.id AS item_id,
    i.item_code,
    i.item_name AS title,
    i.category_id,
    c.category_name,
    bd.isbn_10,
    bd.isbn_13,
    bd.publisher_id,
    p.publisher_name,
    bd.publication_year,
    bd.grade_level,
    bd.edition,
    bd.language,
    bd.page_count,
    bd.classification_code,
    bd.call_number,
    bd.completion_status,
    i.status AS item_status,
    COUNT(a.id) AS total_copies,
    SUM(CASE WHEN a.asset_status = 'available' THEN 1 ELSE 0 END) AS available_copies,
    SUM(CASE WHEN a.asset_status = 'borrowed' THEN 1 ELSE 0 END) AS borrowed_copies,
    SUM(CASE WHEN a.asset_status = 'unprocessed' THEN 1 ELSE 0 END) AS unprocessed_copies,
    SUM(CASE WHEN a.asset_status = 'damaged' THEN 1 ELSE 0 END) AS damaged_copies,
    SUM(CASE WHEN a.asset_status = 'lost' THEN 1 ELSE 0 END) AS lost_copies,
    SUM(CASE WHEN a.id IS NOT NULL AND a.current_shelf_id IS NULL THEN 1 ELSE 0 END) AS copies_without_shelf
FROM items i
JOIN book_details bd ON bd.item_id = i.id
LEFT JOIN categories c ON c.id = i.category_id
LEFT JOIN publishers p ON p.id = bd.publisher_id
LEFT JOIN assets a ON a.item_id = i.id
WHERE i.item_type = 'book'
GROUP BY
    i.id, i.item_code, i.item_name, i.category_id, c.category_name,
    bd.isbn_10, bd.isbn_13, bd.publisher_id, p.publisher_name,
    bd.publication_year, bd.grade_level, bd.edition, bd.language, bd.page_count,
    bd.classification_code, bd.call_number, bd.completion_status, i.status;

CREATE OR REPLACE VIEW vw_library_copies AS
SELECT
    a.id AS asset_id,
    a.asset_code,
    a.barcode,
    i.id AS item_id,
    i.item_code,
    i.item_name AS title,
    bd.isbn_13,
    bd.classification_code,
    bd.call_number,
    a.condition_status,
    a.asset_status,
    a.current_location_id,
    l.location_name,
    a.current_shelf_id,
    s.shelf_code,
    s.shelf_name,
    a.acquisition_date,
    a.acquisition_price,
    a.created_at,
    a.updated_at
FROM assets a
JOIN items i ON i.id = a.item_id AND i.item_type = 'book'
JOIN book_details bd ON bd.item_id = i.id
LEFT JOIN locations l ON l.id = a.current_location_id
LEFT JOIN library_shelves s ON s.id = a.current_shelf_id;

CREATE OR REPLACE VIEW vw_inventory_summary AS
SELECT
    i.id AS item_id,
    i.item_code,
    i.item_name,
    i.item_type,
    i.tracking_type,
    c.category_name,
    u.unit_name,
    CASE
        WHEN i.tracking_type = 'asset' THEN COALESCE(aa.total_assets, 0)
        ELSE COALESCE(qs.total_stock, 0)
    END AS total_quantity,
    CASE
        WHEN i.tracking_type = 'asset' THEN COALESCE(aa.available_assets, 0)
        ELSE COALESCE(qs.total_stock, 0)
    END AS available_quantity,
    i.minimum_stock,
    i.status
FROM items i
LEFT JOIN categories c ON c.id = i.category_id
LEFT JOIN units u ON u.id = i.unit_id
LEFT JOIN (
    SELECT
        item_id,
        COUNT(*) AS total_assets,
        SUM(CASE WHEN asset_status = 'available' THEN 1 ELSE 0 END) AS available_assets
    FROM assets
    GROUP BY item_id
) aa ON aa.item_id = i.id
LEFT JOIN (
    SELECT item_id, SUM(quantity) AS total_stock
    FROM stock_balances
    GROUP BY item_id
) qs ON qs.item_id = i.id;

CREATE OR REPLACE VIEW vw_overdue_loans AS
SELECT
    li.id AS loan_item_id,
    l.id AS loan_id,
    l.loan_code,
    m.id AS member_id,
    m.member_code,
    m.member_name,
    a.id AS asset_id,
    a.asset_code,
    i.id AS item_id,
    i.item_name AS title,
    li.borrowed_at,
    li.due_date,
    DATEDIFF(CURDATE(), li.due_date) AS days_overdue,
    DATEDIFF(CURDATE(), li.due_date) *
        CAST((SELECT setting_value FROM system_settings WHERE setting_key = 'library.fine_per_day') AS DECIMAL(15,2))
        AS estimated_fine
FROM loan_items li
JOIN loans l ON l.id = li.loan_id
JOIN members m ON m.id = l.member_id
JOIN assets a ON a.id = li.asset_id
JOIN items i ON i.id = a.item_id
WHERE li.return_status = 'borrowed'
  AND li.due_date < CURDATE();

-- ============================================================
-- 13. CONTOH PEMAKAIAN
-- ============================================================
-- Sebelum memakai prosedur, buat minimal satu user aplikasi dan lokasi.
-- Password harus di-hash oleh backend menggunakan Argon2id atau bcrypt.
--
-- Contoh user sementara untuk pengembangan, ganti hash dengan hash asli:
-- INSERT INTO users (full_name, username, email, password_hash)
-- VALUES ('Administrator', 'admin', 'admin@example.com', '$2y$10$GANTI_DENGAN_HASH_ASLI');
--
-- INSERT INTO user_roles (user_id, role_id)
-- SELECT 1, id FROM roles WHERE role_code = 'SUPER_ADMIN';
--
-- INSERT INTO locations (location_code, location_name, location_type)
-- VALUES ('LIB-ROOM', 'Ruang Perpustakaan', 'room');
--
-- INSERT INTO library_shelves (shelf_code, shelf_name, location_id, classification_range, capacity, created_by)
-- VALUES ('RK-A01', 'Rak Teknologi A01', 1, '000-099', 100, 1);
--
-- Contoh input buku sebanyak 3 eksemplar dari dashboard inventaris:
-- CALL sp_create_item_with_stock(
--     'BK-0001',
--     'Pemrograman Web Dasar',
--     'book',
--     'asset',
--     (SELECT id FROM categories WHERE category_code = 'BOOK'),
--     (SELECT id FROM units WHERE unit_code = 'COPY'),
--     'Buku pembelajaran pemrograman web.',
--     3,
--     CURDATE(),
--     'purchase',
--     85000,
--     NULL,
--     1,
--     1
-- );
--
-- Buku langsung tampil pada view vw_library_books dan berstatus incomplete/unprocessed.
-- Petugas perpustakaan melengkapi book_details, penulis, lalu menetapkan rak.
--
-- UPDATE book_details
-- SET isbn_13 = '9780000000000',
--     publication_year = 2026,
--     language = 'Indonesia',
--     page_count = 250,
--     classification_code = '005.1',
--     call_number = '005.1 PEM',
--     completion_status = 'complete',
--     updated_by = 1
-- WHERE item_id = 1;
--
-- CALL sp_assign_book_shelf(1, 1, 1, 'Penempatan awal buku.');

-- ============================================================
-- SELESAI
-- ============================================================
