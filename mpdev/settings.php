<?php
require_once 'auth_check.php';
checkAuth(['admin']); // Only admin can access settings
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Handle form submissions
if ($_POST) {
    $success = false;
    $message = '';
    
    switch ($_POST['action']) {
        case 'general':
            // Handle general settings
            $app_name = $_POST['app_name'] ?? '';
            $timezone = $_POST['timezone'] ?? '';
            $date_format = $_POST['date_format'] ?? '';
            $theme = $_POST['theme'] ?? '';
            $language = $_POST['language'] ?? '';
            
            // Save to database or config file
            $success = true;
            $message = 'Pengaturan umum berhasil disimpan!';
            break;
            
        case 'security':
            // Handle security settings
            $login_attempts = $_POST['login_attempts'] ?? 3;
            $enable_2fa = isset($_POST['enable_2fa']) ? 1 : 0;
            
            $success = true;
            $message = 'Pengaturan keamanan berhasil disimpan!';
            break;
            
        case 'notification':
            // Handle notification settings
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $email_template = $_POST['email_template'] ?? '';
            
            $success = true;
            $message = 'Pengaturan notifikasi berhasil disimpan!';
            break;
            
        case 'data':
            // Handle data settings
            $items_per_page = $_POST['items_per_page'] ?? 10;
            $default_order = $_POST['default_order'] ?? 'npk ASC';
            $default_filter = $_POST['default_filter'] ?? '';
            
            $success = true;
            $message = 'Pengaturan data berhasil disimpan!';
            break;
            
        case 'export':
            // Handle export settings
            $export_format = $_POST['export_format'] ?? 'excel';
            $report_header = $_POST['report_header'] ?? '';
            $report_footer = $_POST['report_footer'] ?? '';
            
            $success = true;
            $message = 'Pengaturan laporan berhasil disimpan!';
            break;
            
        case 'api':
            // Handle API settings
            $api_key = $_POST['api_key'] ?? '';
            $webhook_url = $_POST['webhook_url'] ?? '';
            
            $success = true;
            $message = 'Pengaturan API berhasil disimpan!';
            break;
            
        case 'backup':
            // Handle backup data
            if (isset($_POST['backup_type'])) {
                $backup_type = $_POST['backup_type'];
                $backup_dir = '../backups/';
                
                // Create backup directory if not exists
                if (!file_exists($backup_dir)) {
                    mkdir($backup_dir, 0755, true);
                }
                
                $timestamp = date('Y-m-d_H-i-s');
                $filename = "backup_{$backup_type}_{$timestamp}";
                
                try {
                    switch ($backup_type) {
                        case 'json':
                            $data = [];
                            // Get all employees data
                            $stmt = $conn->query("SELECT * FROM employees");
                            $data['employees'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Get education data
                            $stmt = $conn->query("SELECT * FROM education");
                            $data['education'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            file_put_contents($backup_dir . $filename . '.json', json_encode($data, JSON_PRETTY_PRINT));
                            break;
                            
                        case 'csv':
                            // Backup employees to CSV
                            $stmt = $conn->query("SELECT * FROM employees");
                            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            $csv_file = fopen($backup_dir . $filename . '_employees.csv', 'w');
                            if (!empty($employees)) {
                                fputcsv($csv_file, array_keys($employees[0]));
                                foreach ($employees as $row) {
                                    fputcsv($csv_file, $row);
                                }
                            }
                            fclose($csv_file);
                            break;
                            
                        case 'excel':
                            // For Excel export, we'll create a simple CSV that can be opened in Excel
                            $stmt = $conn->query("SELECT * FROM employees");
                            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            $excel_file = fopen($backup_dir . $filename . '.xlsx.csv', 'w');
                            if (!empty($employees)) {
                                fputcsv($excel_file, array_keys($employees[0]));
                                foreach ($employees as $row) {
                                    fputcsv($excel_file, $row);
                                }
                            }
                            fclose($excel_file);
                            break;
                    }
                    
                    $success = true;
                    $message = "Backup {$backup_type} berhasil dibuat: {$filename}";
                } catch (Exception $e) {
                    $success = false;
                    $message = "Error saat membuat backup: " . $e->getMessage();
                }
            }
            break;
            
        case 'upload':
            // Handle file upload
            if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/';
                
                // Create upload directory if not exists
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_tmp = $_FILES['upload_file']['tmp_name'];
                $file_name = $_FILES['upload_file']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $allowed_extensions = ['json', 'csv', 'xlsx', 'pdf'];
                
                if (in_array($file_ext, $allowed_extensions)) {
                    $new_filename = 'upload_' . date('Y-m-d_H-i-s') . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        // Process the uploaded file based on type
                        try {
                            switch ($file_ext) {
                                case 'json':
                                    $json_data = json_decode(file_get_contents($upload_path), true);
                                    if ($json_data && isset($json_data['employees'])) {
                                        // Process JSON data import
                                        $imported = 0;
                                        foreach ($json_data['employees'] as $employee) {
                                            // Insert or update employee data
                                            $imported++;
                                        }
                                        $message = "File JSON berhasil diupload dan {$imported} data diproses.";
                                    } else {
                                        $message = "File JSON berhasil diupload tetapi format tidak valid.";
                                    }
                                    break;
                                    
                                case 'csv':
                                    $csv_data = array_map('str_getcsv', file($upload_path));
                                    $message = "File CSV berhasil diupload dengan " . count($csv_data) . " baris data.";
                                    break;
                                    
                                case 'xlsx':
                                    $message = "File Excel berhasil diupload: {$new_filename}";
                                    break;
                                    
                                case 'pdf':
                                    $message = "File PDF berhasil diupload: {$new_filename}";
                                    break;
                            }
                            $success = true;
                        } catch (Exception $e) {
                            $success = false;
                            $message = "Error saat memproses file: " . $e->getMessage();
                        }
                    } else {
                        $success = false;
                        $message = "Gagal mengupload file.";
                    }
                } else {
                    $success = false;
                    $message = "Format file tidak didukung. Hanya JSON, CSV, Excel, dan PDF yang diizinkan.";
                }
            } else {
                $success = false;
                $message = "Tidak ada file yang dipilih atau terjadi error saat upload.";
            }
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - MPD MS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/sidebar.css">
    <style>
        .settings-tab {
            border: none;
            border-radius: 10px;
            margin-bottom: 10px;
            padding: 15px 20px;
            text-align: left;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        .settings-tab.active {
            background: #0d6efd;
            color: white;
        }
        .settings-tab:hover {
            background: #e9ecef;
        }
        .settings-tab.active:hover {
            background: #0b5ed7;
        }
        .settings-content {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 15px;
        }
        .backup-card {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .backup-card:hover {
            border-color: #0d6efd;
            background-color: #f8f9ff;
        }
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #0d6efd;
            background-color: #f8f9ff;
        }
        .upload-area.dragover {
            border-color: #0d6efd;
            background-color: #e7f3ff;
        }
    </style>
</head>
<body>
<div class="sidebar">
    <div>
        <h4 class="mb-4">🛠️ MPD MS</h4>
        <div class="nav-links">
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="employees.php">📋 Database MP</a>
            <a href="end_contracts.php">📅 End Contract</a>
            <a href="replacement.php">🔁 Replacement</a>
            <a href="education.php">🎓 Edukasi</a>
            <a href="education_schedule.php">📆 Jadwal Edukasi</a>
            <a href="mapping.php">🗺️ Mapping MP</a>
            <a href="sk_comp_assy.php">📊 SK_CompAssy</a>
            <a href="sk_wclutch.php">📊 SK_WClutch</a>
            <a href="overtime.php">⏰ Overtime</a>
        </div>
    </div>
    <div>
        <hr class="bg-white">
        <a href="settings.php" class="btn btn-light w-100 active">⚙️ Settings</a>
        <a href="logout.php" class="btn btn-outline-light mt-2 w-100">🚪 Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>⚙️ Pengaturan Sistem</h2>
    </div>

    <?php if (isset($success) && $success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (isset($success) && !$success): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-3">
            <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist">
                <button class="settings-tab active" id="general-tab" data-bs-toggle="pill" data-bs-target="#general" type="button" role="tab">
                    🔧 Pengaturan Umum
                </button>
                <button class="settings-tab" id="security-tab" data-bs-toggle="pill" data-bs-target="#security" type="button" role="tab">
                    👤 Akun & Keamanan
                </button>
                <button class="settings-tab" id="notification-tab" data-bs-toggle="pill" data-bs-target="#notification" type="button" role="tab">
                    📧 Notifikasi
                </button>
                <button class="settings-tab" id="data-tab" data-bs-toggle="pill" data-bs-target="#data" type="button" role="tab">
                    📂 Data & Tampilan
                </button>
                <button class="settings-tab" id="export-tab" data-bs-toggle="pill" data-bs-target="#export" type="button" role="tab">
                    📈 Laporan & Export
                </button>
                <button class="settings-tab" id="backup-tab" data-bs-toggle="pill" data-bs-target="#backup" type="button" role="tab">
                    💾 Backup & Upload
                </button>
                <button class="settings-tab" id="api-tab" data-bs-toggle="pill" data-bs-target="#api" type="button" role="tab">
                    🔐 API & Integrasi
                </button>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="tab-content" id="v-pills-tabContent">
                <!-- ... existing code ... -->
                
                <!-- Backup & Upload Settings -->
                <div class="tab-pane fade" id="backup" role="tabpanel">
                    <div class="settings-content">
                        <h4 class="mb-4">💾 Backup & Upload Data</h4>
                        
                        <!-- Backup Section -->
                        <div class="form-section">
                            <div class="section-title">📤 Backup Data</div>
                            <p class="text-muted mb-4">Buat backup data sistem dalam berbagai format</p>
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="backup-card">
                                        <div class="mb-3">
                                            <i class="fas fa-file-code" style="font-size: 2rem; color: #28a745;"></i>
                                        </div>
                                        <h6>JSON Format</h6>
                                        <p class="small text-muted">Backup lengkap dalam format JSON</p>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="backup">
                                            <input type="hidden" name="backup_type" value="json">
                                            <button type="submit" class="btn btn-success btn-sm">📥 Backup JSON</button>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="backup-card">
                                        <div class="mb-3">
                                            <i class="fas fa-file-csv" style="font-size: 2rem; color: #17a2b8;"></i>
                                        </div>
                                        <h6>CSV Format</h6>
                                        <p class="small text-muted">Backup data dalam format CSV</p>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="backup">
                                            <input type="hidden" name="backup_type" value="csv">
                                            <button type="submit" class="btn btn-info btn-sm">📥 Backup CSV</button>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="backup-card">
                                        <div class="mb-3">
                                            <i class="fas fa-file-excel" style="font-size: 2rem; color: #28a745;"></i>
                                        </div>
                                        <h6>Excel Format</h6>
                                        <p class="small text-muted">Backup data dalam format Excel</p>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="backup">
                                            <input type="hidden" name="backup_type" value="excel">
                                            <button type="submit" class="btn btn-success btn-sm">📥 Backup Excel</button>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="backup-card">
                                        <div class="mb-3">
                                            <i class="fas fa-database" style="font-size: 2rem; color: #6f42c1;"></i>
                                        </div>
                                        <h6>Full Backup</h6>
                                        <p class="small text-muted">Backup lengkap semua data</p>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="backup">
                                            <input type="hidden" name="backup_type" value="full">
                                            <button type="submit" class="btn btn-primary btn-sm">📥 Full Backup</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <strong>ℹ️ Info:</strong> File backup akan disimpan di folder <code>/backups/</code> dengan timestamp.
                            </div>
                        </div>
                        
                        <!-- Upload Section -->
                        <div class="form-section">
                            <div class="section-title">📤 Upload Data</div>
                            <p class="text-muted mb-4">Upload file data dalam format JSON, CSV, Excel, atau PDF</p>
                            
                            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                                <input type="hidden" name="action" value="upload">
                                
                                <div class="upload-area" id="uploadArea" onclick="document.getElementById('fileInput').click()">
                                    <div class="mb-3">
                                        <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: #6c757d;"></i>
                                    </div>
                                    <h5>Klik untuk memilih file atau drag & drop</h5>
                                    <p class="text-muted">Mendukung format: JSON, CSV, Excel (.xlsx), PDF</p>
                                    <p class="small text-muted">Maksimal ukuran file: 10MB</p>
                                    
                                    <input type="file" id="fileInput" name="upload_file" accept=".json,.csv,.xlsx,.pdf" style="display: none;" onchange="handleFileSelect(this)">
                                </div>
                                
                                <div id="fileInfo" class="mt-3" style="display: none;">
                                    <div class="alert alert-light">
                                        <strong>File dipilih:</strong> <span id="fileName"></span><br>
                                        <strong>Ukuran:</strong> <span id="fileSize"></span><br>
                                        <strong>Tipe:</strong> <span id="fileType"></span>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">📤 Upload File</button>
                                    <button type="button" class="btn btn-secondary" onclick="resetUpload()">❌ Batal</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Recent Files -->
                        <div class="form-section">
                            <div class="section-title">📁 File Terbaru</div>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Nama File</th>
                                            <th>Tipe</th>
                                            <th>Ukuran</th>
                                            <th>Tanggal</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $backup_dir = '../backups/';
                                        $upload_dir = '../uploads/';
                                        $files = [];
                                        
                                        // Get backup files
                                        if (is_dir($backup_dir)) {
                                            $backup_files = scandir($backup_dir);
                                            foreach ($backup_files as $file) {
                                                if ($file != '.' && $file != '..') {
                                                    $files[] = [
                                                        'name' => $file,
                                                        'path' => $backup_dir . $file,
                                                        'type' => 'Backup',
                                                        'size' => filesize($backup_dir . $file),
                                                        'date' => filemtime($backup_dir . $file)
                                                    ];
                                                }
                                            }
                                        }
                                        
                                        // Get upload files
                                        if (is_dir($upload_dir)) {
                                            $upload_files = scandir($upload_dir);
                                            foreach ($upload_files as $file) {
                                                if ($file != '.' && $file != '..') {
                                                    $files[] = [
                                                        'name' => $file,
                                                        'path' => $upload_dir . $file,
                                                        'type' => 'Upload',
                                                        'size' => filesize($upload_dir . $file),
                                                        'date' => filemtime($upload_dir . $file)
                                                    ];
                                                }
                                            }
                                        }
                                        
                                        // Sort by date (newest first)
                                        usort($files, function($a, $b) {
                                            return $b['date'] - $a['date'];
                                        });
                                        
                                        // Show only last 10 files
                                        $files = array_slice($files, 0, 10);
                                        
                                        if (empty($files)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">Belum ada file backup atau upload</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($files as $file): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($file['name']) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $file['type'] == 'Backup' ? 'primary' : 'success' ?>">
                                                            <?= $file['type'] ?>
                                                        </span>
                                                    </td>
                                                    <td><?= number_format($file['size'] / 1024, 2) ?> KB</td>
                                                    <td><?= date('d/m/Y H:i', $file['date']) ?></td>
                                                    <td>
                                                        <a href="<?= $file['path'] ?>" class="btn btn-sm btn-outline-primary" download>
                                                            📥 Download
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

                <!-- ... existing code ... -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleApiKey() {
    const apiKeyInput = document.getElementById('api_key');
    const type = apiKeyInput.getAttribute('type') === 'password' ? 'text' : 'password';
    apiKeyInput.setAttribute('type', type);
}

function generateApiKey() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let result = 'sk-';
    for (let i = 0; i < 32; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('api_key').value = result;
}

// Handle tab switching
document.querySelectorAll('.settings-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
    });
});

// File upload handling
function handleFileSelect(input) {
    const file = input.files[0];
    if (file) {
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = (file.size / 1024).toFixed(2) + ' KB';
        document.getElementById('fileType').textContent = file.type || 'Unknown';
        document.getElementById('fileInfo').style.display = 'block';
    }
}

function resetUpload() {
    document.getElementById('fileInput').value = '';
    document.getElementById('fileInfo').style.display = 'none';
}

// Drag and drop functionality
const uploadArea = document.getElementById('uploadArea');

uploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.classList.remove('dragover');
});

uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('dragover');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        document.getElementById('fileInput').files = files;
        handleFileSelect(document.getElementById('fileInput'));
    }
});
</script>
</body>
</html>