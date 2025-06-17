CREATE DATABASE IF NOT EXISTS mp_development;
USE mp_development;

-- Employees table
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npk VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    gender ENUM('Pria', 'Wanita') NOT NULL,
    section VARCHAR(50) NOT NULL,
    line VARCHAR(50) NOT NULL,
    leader VARCHAR(100) NOT NULL,
    dateIn DATE NOT NULL,
    status ENUM('Aktif', 'Tidak Aktif') DEFAULT 'Aktif',
    tipe ENUM('Tetap', 'Kontrak') NOT NULL,
    functionRole VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- End contracts table
CREATE TABLE end_contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npk VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    gender ENUM('Pria', 'Wanita') NOT NULL,
    section VARCHAR(50) NOT NULL,
    line VARCHAR(50) NOT NULL,
    leader VARCHAR(100) NOT NULL,
    dateIn DATE NOT NULL,
    dateOut DATE NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Replacement table
CREATE TABLE recruitment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npk_keluar VARCHAR(20),
    nama_keluar VARCHAR(100),
    date_out DATE,
    npk_pengganti VARCHAR(20),
    nama_pengganti VARCHAR(100),
    gender ENUM('Pria', 'Wanita') NOT NULL,
    section VARCHAR(50) NOT NULL,
    line VARCHAR(50) NOT NULL,
    leader VARCHAR(100) NOT NULL,
    date_in DATE,
    rekomendasi TEXT,
    status ENUM('On Time', 'Delay','Over' ) DEFAULT 'On Time',
    dateCreated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Education table
CREATE TABLE education (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npk VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    section VARCHAR(50) NOT NULL,
    line VARCHAR(50) NOT NULL,
    leader VARCHAR(100) NOT NULL,
    namaPos VARCHAR(100) NOT NULL,
    category ENUM('New MP', 'Refresh MP', 'Skill Up MP') NOT NULL,
    dateEdukasi DATE NOT NULL,
    datePlanning DATE,
    raport VARCHAR(255), -- For PDF file path
    status ENUM('Planned', 'Berlangsung', 'Selesai') DEFAULT 'Planned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users table for authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'trainer', 'leader', 'foreman', 'spv', 'manager') NOT NULL DEFAULT 'trainer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- Education Schedule table
CREATE TABLE education_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    education_id INT NOT NULL,
    npk VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    section VARCHAR(50) NOT NULL,
    line VARCHAR(50) NOT NULL,
    leader VARCHAR(100) NOT NULL,
    category ENUM('New MP', 'Refresh MP', 'Skill Up MP') NOT NULL,
    namaPos VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    time ENUM('08:00-11:45', '13:00-16:30', '16:40-20:00') NOT NULL,
    status ENUM('Belum ditentukan','Hadir', 'Tidak Hadir') DEFAULT NULL,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (education_id) REFERENCES education(id) ON DELETE CASCADE
);

-- Tabel untuk Skill Matrix Comp Assy
CREATE TABLE IF NOT EXISTS skill_matrix_comp_assy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npk VARCHAR(20) NOT NULL,
    process VARCHAR(50) NOT NULL,
    skill_level TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_npk_process (npk, process),
    INDEX idx_npk (npk),
    INDEX idx_process (process)
);

-- Tabel untuk Skill Matrix WClutch
CREATE TABLE IF NOT EXISTS skill_matrix_wclutch (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npk VARCHAR(20) NOT NULL,
    process VARCHAR(50) NOT NULL,
    skill_level TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_npk_process (npk, process),
    INDEX idx_npk (npk),
    INDEX idx_process (process)
);
-- Insert default admin user
INSERT INTO users (username, password, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- Password: password

CREATE TABLE overtime (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npk VARCHAR(20) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    section VARCHAR(50) NOT NULL,
    line VARCHAR(50) NOT NULL,
    tanggal DATE NOT NULL,
    jenis_ot ENUM('OT Produksi', 'OT Non Produksi') NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    total_jam DECIMAL(4,2) NOT NULL,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(50),
    INDEX idx_npk (npk),
    INDEX idx_section_line (section, line),
    INDEX idx_tanggal (tanggal)
);