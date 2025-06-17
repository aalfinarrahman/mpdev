<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once '../backend/database.php';
    require_once 'auth_check.php';
    
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    exit;
}

// Build WHERE conditions for filtering
$whereConditions = [];
$params = [];

if (!empty($_GET['filter_date'])) {
    $whereConditions[] = "DATE(o.tanggal) = ?";
    $params[] = $_GET['filter_date'];
}

if (!empty($_GET['filter_month'])) {
    $whereConditions[] = "MONTH(o.tanggal) = ?";
    $params[] = $_GET['filter_month'];
}

if (!empty($_GET['filter_year'])) {
    $whereConditions[] = "YEAR(o.tanggal) = ?";
    $params[] = $_GET['filter_year'];
}

if (!empty($_GET['filter_section'])) {
    $whereConditions[] = "e.section = ?";
    $params[] = $_GET['filter_section'];
}

if (!empty($_GET['filter_line'])) {
    $whereConditions[] = "e.line LIKE ?";
    $params[] = '%' . $_GET['filter_line'] . '%';
}

if (!empty($_GET['search'])) {
    $whereConditions[] = "(e.npk LIKE ? OR e.nama LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_overtime'])) {
        $sql = "INSERT INTO overtime (npk, nama, section, line, tanggal, jenis_ot, jam_mulai, jam_selesai, total_jam, keterangan, created_by) 
                VALUES (:npk, :nama, :section, :line, :tanggal, :jenis_ot, :jam_mulai, :jam_selesai, :total_jam, :keterangan, :created_by)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':npk' => $_POST['npk'],
            ':nama' => $_POST['nama'],
            ':section' => $_POST['section'],
            ':line' => $_POST['line'],
            ':tanggal' => $_POST['tanggal'],
            ':jenis_ot' => $_POST['jenis_ot'],
            ':jam_mulai' => $_POST['jam_mulai'],
            ':jam_selesai' => $_POST['jam_selesai'],
            ':total_jam' => $_POST['total_jam'],
            ':keterangan' => $_POST['keterangan'],
            ':created_by' => $_SESSION['username']
        ]);
        
        header("Location: overtime.php?success=added");
        exit();
    }
    
    if (isset($_POST['add_bulk_overtime'])) {
        $selectedEmployees = $_POST['selected_employees'];
        foreach ($selectedEmployees as $npk) {
            $employeeStmt = $conn->prepare("SELECT nama, section, line FROM employees WHERE npk = ?");
            $employeeStmt->execute([$npk]);
            $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($employee) {
                $sql = "INSERT INTO overtime (npk, nama, section, line, tanggal, jenis_ot, jam_mulai, jam_selesai, total_jam, keterangan, created_by) 
                        VALUES (:npk, :nama, :section, :line, :tanggal, :jenis_ot, :jam_mulai, :jam_selesai, :total_jam, :keterangan, :created_by)";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':npk' => $npk,
                    ':nama' => $employee['nama'],
                    ':section' => $employee['section'],
                    ':line' => $employee['line'],
                    ':tanggal' => $_POST['tanggal'],
                    ':jenis_ot' => $_POST['jenis_ot'],
                    ':jam_mulai' => $_POST['jam_mulai'],
                    ':jam_selesai' => $_POST['jam_selesai'],
                    ':total_jam' => $_POST['total_jam'],
                    ':keterangan' => $_POST['keterangan'],
                    ':created_by' => $_SESSION['username']
                ]);
            }
        }
        
        header("Location: overtime.php?success=bulk_added");
        exit();
    }
    
    // Handle delete request
    if (isset($_GET['delete'])) {
        $deleteStmt = $conn->prepare("DELETE FROM overtime WHERE id = ?");
        $deleteStmt->execute([$_GET['delete']]);
        header("Location: overtime.php?success=deleted");
        exit();
    }
}

// Get employees for dropdown
$employeesStmt = $conn->query("SELECT npk, nama, section, line FROM employees WHERE status = 'Aktif' ORDER BY section, line, nama");
$employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get overtime data with filters
$overtimeQuery = "
    SELECT o.*, e.nama, e.section, e.line 
    FROM overtime o 
    JOIN employees e ON o.npk = e.npk 
    $whereClause 
    ORDER BY o.tanggal DESC, o.jam_mulai DESC
";

$overtimeStmt = $conn->prepare($overtimeQuery);
$overtimeStmt->execute($params);
$overtimeData = $overtimeStmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary data for charts
$currentMonth = date('m');
$currentYear = date('Y');

// Monthly summary with average calculation
$monthlySummaryQuery = "
    SELECT 
        e.section,
        e.line,
        o.jenis_ot,
        SUM(o.total_jam) as total_jam_ot,
        COUNT(DISTINCT o.npk) as total_mp,
        ROUND(SUM(o.total_jam) / COUNT(DISTINCT o.npk), 2) as avg_jam_per_mp
    FROM overtime o 
    JOIN employees e ON o.npk = e.npk 
    WHERE MONTH(o.tanggal) = MONTH(CURDATE()) AND YEAR(o.tanggal) = YEAR(CURDATE())
    GROUP BY e.section, e.line, o.jenis_ot
    ORDER BY e.section, e.line
";

$monthlySummaryStmt = $conn->prepare($monthlySummaryQuery);
$monthlySummaryStmt->execute();
$monthlySummary = $monthlySummaryStmt->fetchAll(PDO::FETCH_ASSOC);

// Individual MP OT summary
$individualSummaryStmt = $conn->prepare("
    SELECT 
        npk,
        nama,
        section,
        line,
        jenis_ot,
        SUM(total_jam) as total_jam_ot,
        COUNT(*) as total_hari_ot
    FROM overtime 
    WHERE MONTH(tanggal) = :month AND YEAR(tanggal) = :year
    GROUP BY npk, nama, section, line, jenis_ot
    ORDER BY section, line, nama, jenis_ot
");
$individualSummaryStmt->execute([':month' => $currentMonth, ':year' => $currentYear]);
$individualSummary = $individualSummaryStmt->fetchAll(PDO::FETCH_ASSOC);

// Process data for charts
$chartLineLabels = [];
$chartOtProduksiData = [];
$chartOtNonProduksiData = [];

// Process monthly summary data for line chart
foreach ($monthlySummary as $row) {
    $lineKey = $row['section'] . ' - ' . $row['line'];
    if (!in_array($lineKey, $chartLineLabels)) {
        $chartLineLabels[] = $lineKey;
        $chartOtProduksiData[$lineKey] = 0;
        $chartOtNonProduksiData[$lineKey] = 0;
    }
    
    if ($row['jenis_ot'] === 'OT Produksi') {
        $chartOtProduksiData[$lineKey] = floatval($row['avg_jam_per_mp']);
    } else {
        $chartOtNonProduksiData[$lineKey] = floatval($row['avg_jam_per_mp']);
    }
}

// Process individual summary for MP chart
$mpOtTotals = [];
foreach ($individualSummary as $row) {
    $mpKey = $row['nama'] . ' (' . $row['line'] . ')';
    if (!isset($mpOtTotals[$mpKey])) {
        $mpOtTotals[$mpKey] = ['produksi' => 0, 'nonProduksi' => 0, 'total' => 0];
    }
    
    if ($row['jenis_ot'] === 'OT Produksi') {
        $mpOtTotals[$mpKey]['produksi'] = floatval($row['total_jam_ot']);
    } else {
        $mpOtTotals[$mpKey]['nonProduksi'] = floatval($row['total_jam_ot']);
    }
    $mpOtTotals[$mpKey]['total'] = $mpOtTotals[$mpKey]['produksi'] + $mpOtTotals[$mpKey]['nonProduksi'];
}

// Sort by total and get top 10
uasort($mpOtTotals, function($a, $b) {
    return $b['total'] <=> $a['total'];
});
$topMpData = array_slice($mpOtTotals, 0, 10, true);

$chartMpLabels = array_keys($topMpData);
$chartMpProduksiData = array_column($topMpData, 'produksi');
$chartMpNonProduksiData = array_column($topMpData, 'nonProduksi');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime Management - MP System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="css/sidebar.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
</head>
<body>
    <!-- Sidebar -->
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
                <a href="overtime.php" class="active">⏰ Overtime</a>
            </div>
        </div>
        <div>
            <hr class="bg-white">
            <a href="settings.php" class="btn btn-light w-100">⚙️ Settings</a>
            <a href="logout.php" class="btn btn-outline-light mt-2 w-100">🚪 Logout</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h2><i class="fas fa-clock"></i> Overtime Management</h2>
                </div>
            </div>
            
            <!-- Success Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php if ($_GET['success'] == 'added'): ?>
                        Data overtime berhasil ditambahkan!
                    <?php elseif ($_GET['success'] == 'bulk_added'): ?>
                        Data overtime bulk berhasil ditambahkan!
                    <?php elseif ($_GET['success'] == 'deleted'): ?>
                        Data overtime berhasil dihapus!
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Filters untuk Grafik -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6><i class="fas fa-filter"></i> Filter Grafik</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Tahun</label>
                                    <select name="filter_year" class="form-select">
                                        <option value="">Semua Tahun</option>
                                        <?php for($year = date('Y') - 2; $year <= date('Y') + 1; $year++): ?>
                                            <option value="<?= $year ?>" <?= (isset($_GET['filter_year']) && $_GET['filter_year'] == $year) ? 'selected' : '' ?>>
                                                <?= $year ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Bulan</label>
                                    <select name="filter_month" class="form-select">
                                        <option value="">Semua Bulan</option>
                                        <?php for($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?= $i ?>" <?= (isset($_GET['filter_month']) && $_GET['filter_month'] == $i) ? 'selected' : '' ?>>
                                                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Section</label>
                                    <select name="filter_section" class="form-select">
                                        <option value="">Semua Section</option>
                                        <option value="Comp Assy" <?= (isset($_GET['filter_section']) && $_GET['filter_section'] == 'Comp Assy') ? 'selected' : '' ?>>Comp Assy</option>
                                        <option value="Comp WClutch" <?= (isset($_GET['filter_section']) && $_GET['filter_section'] == 'Comp WClutch') ? 'selected' : '' ?>>Comp WClutch</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary d-block"><i class="fas fa-search"></i> Filter</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-bar"></i> Average OT per MP per Line (Bulan Ini)</h5>
                            <small class="text-muted">Garis merah menunjukkan batas 60 jam</small>
                        </div>
                        <div class="card-body">
                            <canvas id="otPerLineChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-bar"></i> OT per MP (Top 10)</h5>
                            <small class="text-muted">Merah = >60 jam, Hijau = ≤60 jam</small>
                        </div>
                        <div class="card-body">
                            <canvas id="otPerMpChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modul Input dengan Icon + -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6><i class="fas fa-plus-circle"></i> Input Overtime</h6>
                            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#inputSection" aria-expanded="false">
                                <i class="fas fa-plus"></i> Tambah OT
                            </button>
                        </div>
                        <div class="collapse" id="inputSection">
                            <div class="card-body">
                                <ul class="nav nav-tabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#manual-input">Input Manual</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#bulk-input">Input Bulk (Per Line)</a>
                                    </li>
                                </ul>
                                <div class="tab-content mt-3">
                                    <!-- Manual Input Tab -->
                                    <div class="tab-pane fade show active" id="manual-input">
                                        <form method="POST" class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label">NPK</label>
                                                <select name="npk" class="form-select" required onchange="updateEmployeeInfo(this)">
                                                    <option value="">Pilih NPK</option>
                                                    <?php foreach($employees as $emp): ?>
                                                        <option value="<?= $emp['npk'] ?>" data-nama="<?= $emp['nama'] ?>" data-section="<?= $emp['section'] ?>" data-line="<?= $emp['line'] ?>">
                                                            <?= $emp['npk'] ?> - <?= $emp['nama'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Nama</label>
                                                <input type="text" name="nama" class="form-control" readonly>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Section</label>
                                                <input type="text" name="section" class="form-control" readonly>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Line</label>
                                                <input type="text" name="line" class="form-control" readonly>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Tanggal</label>
                                                <input type="date" name="tanggal" class="form-control" required value="<?= date('Y-m-d') ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Jenis OT</label>
                                                <select name="jenis_ot" class="form-select" required>
                                                    <option value="OT Produksi">OT Produksi</option>
                                                    <option value="OT Non Produksi">OT Non Produksi</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Jam Mulai</label>
                                                <input type="time" name="jam_mulai" class="form-control" required onchange="calculateTotalHours()">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Jam Selesai</label>
                                                <input type="time" name="jam_selesai" class="form-control" required onchange="calculateTotalHours()">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Total Jam</label>
                                                <input type="number" name="total_jam" class="form-control" step="0.5" readonly>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Keterangan</label>
                                                <textarea name="keterangan" class="form-control" rows="1" placeholder="Keterangan OT"></textarea>
                                            </div>
                                            <div class="col-12">
                                                <button type="submit" name="add_overtime" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> Simpan OT
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <!-- Bulk Input Tab -->
                                    <div class="tab-pane fade" id="bulk-input">
                                        <form method="POST" class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label">Section</label>
                                                <select name="bulk_section" id="bulk_section" class="form-select" required onchange="filterEmployeesBySection()">
                                                    <option value="">Pilih Section</option>
                                                    <option value="Comp Assy">Comp Assy</option>
                                                    <option value="Comp WClutch">Comp WClutch</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Line</label>
                                                <select name="bulk_line" id="bulk_line" class="form-select" required onchange="filterEmployeesByLine()">
                                                    <option value="">Pilih Line</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Tanggal</label>
                                                <input type="date" name="tanggal" class="form-control" required value="<?= date('Y-m-d') ?>">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Jenis OT</label>
                                                <select name="jenis_ot" class="form-select" required>
                                                    <option value="OT Produksi">OT Produksi</option>
                                                    <option value="OT Non Produksi">OT Non Produksi</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Jam Mulai</label>
                                                <input type="time" name="jam_mulai" id="bulk_jam_mulai" class="form-control" required onchange="calculateBulkTotalHours()">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Jam Selesai</label>
                                                <input type="time" name="jam_selesai" id="bulk_jam_selesai" class="form-control" required onchange="calculateBulkTotalHours()">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Total Jam</label>
                                                <input type="number" name="total_jam" id="bulk_total_jam" class="form-control" step="0.5" readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Keterangan</label>
                                                <textarea name="keterangan" class="form-control" rows="1" placeholder="Keterangan OT"></textarea>
                                            </div>
                                            
                                            <div class="col-12">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Pilih MP untuk OT:</label>
                                                        <div id="employee-selection" class="border p-3 rounded" style="max-height: 300px; overflow-y: auto;">
                                                            <p class="text-muted">Pilih section dan line terlebih dahulu</p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Daftar MP Terpilih:</label>
                                                        <div id="selected-employees-list" class="list-group border p-3 rounded bg-light" style="max-height: 300px; overflow-y: auto;">
                                                            <p class="text-muted">Belum ada MP yang dipilih</p>
                                                        </div>
                                                        <div class="mt-2">
                                                            <small class="text-muted">Total MP: <span id="selected-count">0</span></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-12">
                                                <button type="submit" name="add_bulk_overtime" class="btn btn-success">
                                                    <i class="fas fa-save"></i> Simpan Bulk OT
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Data Overtime dengan Search dan Filter Tanggal di Header -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-table"></i> Data Overtime</h5>
                            <div class="row g-2 mt-2">
                                <div class="col-md-8">
                                    <form method="GET" class="d-flex gap-2">
                                        <!-- Preserve chart filters -->
                                        <?php if(isset($_GET['filter_month'])): ?>
                                            <input type="hidden" name="filter_month" value="<?= $_GET['filter_month'] ?>">
                                        <?php endif; ?>
                                        <?php if(isset($_GET['filter_year'])): ?>
                                            <input type="hidden" name="filter_year" value="<?= $_GET['filter_year'] ?>">
                                        <?php endif; ?>
                                        <?php if(isset($_GET['filter_section'])): ?>
                                            <input type="hidden" name="filter_section" value="<?= $_GET['filter_section'] ?>">
                                        <?php endif; ?>
                                        
                                        <input type="date" name="filter_date" class="form-control form-control-sm" placeholder="Filter Tanggal" value="<?= $_GET['filter_date'] ?? '' ?>" style="width: 150px;">
                                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari NPK/Nama/Line..." value="<?= $_GET['search'] ?? '' ?>" style="width: 200px;">
                                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
                                        <a href="overtime.php" class="btn btn-secondary btn-sm"><i class="fas fa-refresh"></i></a>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>NPK</th>
                                            <th>Nama</th>
                                            <th>Section</th>
                                            <th>Line</th>
                                            <th>Jenis OT</th>
                                            <th>Jam</th>
                                            <th>Total Jam</th>
                                            <th>Keterangan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($overtimeData)): ?>
                                            <?php foreach($overtimeData as $ot): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($ot['tanggal'])) ?></td>
                                                <td><?= $ot['npk'] ?></td>
                                                <td><?= $ot['nama'] ?></td>
                                                <td><?= $ot['section'] ?></td>
                                                <td><?= $ot['line'] ?></td>
                                                <td>
                                                    <span class="badge <?= $ot['jenis_ot'] == 'OT Produksi' ? 'bg-primary' : 'bg-warning' ?>">
                                                        <?= $ot['jenis_ot'] ?>
                                                    </span>
                                                </td>
                                                <td><?= $ot['jam_mulai'] ?> - <?= $ot['jam_selesai'] ?></td>
                                                <td><?= $ot['total_jam'] ?> jam</td>
                                                <td><?= $ot['keterangan'] ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteOT(<?= $ot['id'] ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="10" class="text-center text-muted">Tidak ada data overtime</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Chart.js configuration
    Chart.register(ChartDataLabels);
    
    // Data untuk charts
    const lineLabels = <?= json_encode($chartLineLabels) ?>;
    const avgOtProduksiData = <?= json_encode(array_values($chartOtProduksiData)) ?>;
    const avgOtNonProduksiData = <?= json_encode(array_values($chartOtNonProduksiData)) ?>;
    const mpLabels = <?= json_encode($chartMpLabels) ?>;
    const mpProduksiData = <?= json_encode($chartMpProduksiData) ?>;
    const mpNonProduksiData = <?= json_encode($chartMpNonProduksiData) ?>;
    
    // Chart 1: OT per Line (Average) - HORIZONTAL
    const otPerLineCtx = document.getElementById('otPerLineChart').getContext('2d');
    new Chart(otPerLineCtx, {
        type: 'bar',
        data: {
            labels: lineLabels,
            datasets: [
                {
                    label: 'Avg OT Produksi per MP',
                    data: avgOtProduksiData,
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Avg OT Non Produksi per MP',
                    data: avgOtNonProduksiData,
                    backgroundColor: 'rgba(255, 99, 132, 0.8)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                },
                datalabels: {
                    anchor: 'end',
                    align: 'right',
                    formatter: function(value) {
                        return value.toFixed(1);
                    },
                    font: {
                        size: 10
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    max: 100,
                    grid: {
                        color: function(context) {
                            if (context.tick.value === 60) {
                                return 'red';
                            }
                            return 'rgba(0, 0, 0, 0.1)';
                        },
                        lineWidth: function(context) {
                            if (context.tick.value === 60) {
                                return 2;
                            }
                            return 1;
                        }
                    }
                },
                y: {
                    beginAtZero: true
                }
            }
        },
        plugins: [ChartDataLabels]
    });

    // Chart 2: OT per MP - HORIZONTAL
    const otPerMpCtx = document.getElementById('otPerMpChart').getContext('2d');
    new Chart(otPerMpCtx, {
        type: 'bar',
        data: {
            labels: mpLabels,
            datasets: [
                {
                    label: 'OT Produksi',
                    data: mpProduksiData,
                    backgroundColor: function(context) {
                        const total = mpProduksiData[context.dataIndex] + mpNonProduksiData[context.dataIndex];
                        return total > 60 ? 'rgba(255, 99, 132, 0.8)' : 'rgba(75, 192, 192, 0.8)';
                    },
                    borderColor: function(context) {
                        const total = mpProduksiData[context.dataIndex] + mpNonProduksiData[context.dataIndex];
                        return total > 60 ? 'rgba(255, 99, 132, 1)' : 'rgba(75, 192, 192, 1)';
                    },
                    borderWidth: 1
                },
                {
                    label: 'OT Non Produksi',
                    data: mpNonProduksiData,
                    backgroundColor: function(context) {
                        const total = mpProduksiData[context.dataIndex] + mpNonProduksiData[context.dataIndex];
                        return total > 60 ? 'rgba(255, 159, 164, 0.8)' : 'rgba(153, 255, 153, 0.8)';
                    },
                    borderColor: function(context) {
                        const total = mpProduksiData[context.dataIndex] + mpNonProduksiData[context.dataIndex];
                        return total > 60 ? 'rgba(255, 159, 164, 1)' : 'rgba(153, 255, 153, 1)';
                    },
                    borderWidth: 1
                }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    stacked: true,
                    beginAtZero: true,
                    max: 100
                },
                y: {
                    stacked: true
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                },
                datalabels: {
                    display: function(context) {
                        return context.dataset.data[context.dataIndex] > 0;
                    },
                    formatter: function(value, context) {
                        const total = mpProduksiData[context.dataIndex] + mpNonProduksiData[context.dataIndex];
                        return context.datasetIndex === 1 ? total.toFixed(1) : '';
                    },
                    anchor: 'end',
                    align: 'right',
                    font: {
                        size: 10,
                        weight: 'bold'
                    }
                }
            }
        },
        plugins: [ChartDataLabels]
    });
    
    // Helper functions
    function updateEmployeeInfo(select) {
        const option = select.options[select.selectedIndex];
        if (option.value) {
            document.querySelector('input[name="nama"]').value = option.dataset.nama;
            document.querySelector('input[name="section"]').value = option.dataset.section;
            document.querySelector('input[name="line"]').value = option.dataset.line;
        } else {
            document.querySelector('input[name="nama"]').value = '';
            document.querySelector('input[name="section"]').value = '';
            document.querySelector('input[name="line"]').value = '';
        }
    }
    
    function calculateTotalHours() {
        const startTime = document.querySelector('input[name="jam_mulai"]').value;
        const endTime = document.querySelector('input[name="jam_selesai"]').value;
        
        if (startTime && endTime) {
            const start = new Date('2000-01-01 ' + startTime);
            const end = new Date('2000-01-01 ' + endTime);
            
            if (end < start) {
                end.setDate(end.getDate() + 1);
            }
            
            const diffMs = end - start;
            const diffHours = diffMs / (1000 * 60 * 60);
            
            document.querySelector('input[name="total_jam"]').value = diffHours.toFixed(1);
        }
    }
    
    function calculateBulkTotalHours() {
        const startTime = document.querySelector('#bulk_jam_mulai').value;
        const endTime = document.querySelector('#bulk_jam_selesai').value;
        
        if (startTime && endTime) {
            const start = new Date('2000-01-01 ' + startTime);
            const end = new Date('2000-01-01 ' + endTime);
            
            if (end < start) {
                end.setDate(end.getDate() + 1);
            }
            
            const diffMs = end - start;
            const diffHours = diffMs / (1000 * 60 * 60);
            
            document.querySelector('#bulk_total_jam').value = diffHours.toFixed(1);
        }
    }
    
    function filterEmployeesBySection() {
        const section = document.getElementById('bulk_section').value;
        const lineSelect = document.getElementById('bulk_line');
        
        // Clear line options
        lineSelect.innerHTML = '<option value="">Pilih Line</option>';
        
        if (section) {
            // Get unique lines for selected section
            const employees = <?= json_encode($employees) ?>;
            const lines = [...new Set(employees.filter(emp => emp.section === section).map(emp => emp.line))];
            
            lines.forEach(line => {
                const option = document.createElement('option');
                option.value = line;
                option.textContent = line;
                lineSelect.appendChild(option);
            });
        }
        
        // Clear employee list
        document.getElementById('selected-employees-list').innerHTML = '<p class="text-muted">Belum ada MP yang dipilih</p>';
        document.getElementById('selected-count').textContent = '0';
    }
    
    function filterEmployeesByLine() {
        const section = document.getElementById('bulk_section').value;
        const line = document.getElementById('bulk_line').value;
        const employeeList = document.getElementById('selected-employees-list');
        
        // Clear previous list
        employeeList.innerHTML = '';
        document.getElementById('selected-count').textContent = '0';
        
        if (section && line) {
            const employees = <?= json_encode($employees) ?>;
            const filteredEmployees = employees.filter(emp => emp.section === section && emp.line === line);
            
            filteredEmployees.forEach(emp => {
                const listItem = document.createElement('div');
                listItem.className = 'list-group-item d-flex justify-content-between align-items-center';
                listItem.innerHTML = `
                    <span>${emp.npk} - ${emp.nama}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEmployee('${emp.npk}')">
                        <i class="fas fa-times"></i>
                    </button>
                    <input type="hidden" name="selected_employees[]" value="${emp.npk}">
                `;
                employeeList.appendChild(listItem);
            });
            
            document.getElementById('selected-count').textContent = filteredEmployees.length;
        }
    }
    
    function removeEmployee(npk) {
        const employeeList = document.getElementById('selected-employees-list');
        const items = employeeList.querySelectorAll('.list-group-item');
        
        items.forEach(item => {
            const hiddenInput = item.querySelector('input[type="hidden"]');
            if (hiddenInput && hiddenInput.value === npk) {
                item.remove();
            }
        });
        
        // Update count
        const remainingCount = employeeList.querySelectorAll('.list-group-item').length;
        document.getElementById('selected-count').textContent = remainingCount;
    }
    
    function deleteOT(id) {
        if (confirm('Yakin ingin menghapus data OT ini?')) {
            window.location.href = `overtime.php?delete=${id}`;
        }
    }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>