<?php
require_once 'auth_check.php';
checkAuth(['admin', 'manager', 'spv', 'trainer', 'leader', 'foreman']);
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Handle skill level updates
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_skill') {
    $npk = $_POST['npk'];
    $process = $_POST['process'];
    $skill_level = $_POST['skill_level'];
    
    try {
        // Check if record exists
        $checkStmt = $conn->prepare("SELECT id FROM skill_matrix_comp_assy WHERE npk = ? AND process = ?");
        $checkStmt->execute([$npk, $process]);
        
        if ($checkStmt->rowCount() > 0) {
            // Update existing record
            $updateStmt = $conn->prepare("UPDATE skill_matrix_comp_assy SET skill_level = ?, updated_at = NOW() WHERE npk = ? AND process = ?");
            $updateStmt->execute([$skill_level, $npk, $process]);
        } else {
            // Insert new record
            $insertStmt = $conn->prepare("INSERT INTO skill_matrix_comp_assy (npk, process, skill_level, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $insertStmt->execute([$npk, $process, $skill_level]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Skill level berhasil disimpan']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Build WHERE clause for filtering
$whereClause = "WHERE e.Status = 'Aktif' AND e.Section = 'Comp Assy'";
$params = [];

// Add Line filter if provided
if (isset($_GET['filter_line']) && !empty($_GET['filter_line'])) {
    $whereClause .= " AND e.Line = :line";
    $params[':line'] = $_GET['filter_line'];
}

// Get unique lines for filter options
$linesStmt = $conn->query("SELECT DISTINCT Line FROM employees WHERE Status = 'Aktif' AND Section = 'Comp Assy' ORDER BY Line");
$lines = $linesStmt->fetchAll(PDO::FETCH_COLUMN);

// Get employees data with their current processes
$compAssyQuery = "
    SELECT DISTINCT 
        e.NPK,
        e.Nama,
        e.Section,
        e.Line,
        e.Leader,
        GROUP_CONCAT(DISTINCT ed.namaPos ORDER BY ed.dateEdukasi DESC SEPARATOR ', ') as proses_saat_ini
    FROM employees e
    LEFT JOIN education ed ON e.NPK = ed.npk
    $whereClause
    GROUP BY e.NPK, e.Nama, e.Section, e.Line, e.Leader
    ORDER BY e.Line, e.NPK
";

$stmt = $conn->prepare($compAssyQuery);
$stmt->execute($params);
$compAssyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing skill levels
$skillLevelsQuery = "SELECT npk, process, skill_level FROM skill_matrix_comp_assy";
$skillLevelsData = $conn->query($skillLevelsQuery)->fetchAll(PDO::FETCH_ASSOC);
$skillLevels = [];
foreach ($skillLevelsData as $skill) {
    $skillLevels[$skill['npk']][$skill['process']] = $skill['skill_level'];
}

// Define processes for Comp Assy
$compAssyProcesses = [
    'Mizusumashi Towing', 'Mizusumashi Shaft', 'Pre Check', 'Part Washing Big Part (IN)', 'Part Washing Inner Part (IN)', 'Pass Room (Prepare Piston)', 'Pass Room (Prepare Gasket)', 'Prepare Thrust Bearing', 'Prepare Oring PRV', 'Bearing Assy',
    'Bushing Assy', 'Mizusumashi Assy', 'Proses 13', 'Proses 14', 'Proses 15', 'Proses 16', 'Proses 17', 'Proses 18', 'Proses 19', 'Proses 20',
    'Proses 21', 'Proses 22', 'Proses 23', 'Proses 24', 'Proses 25', 'Proses 26', 'Proses 27', 'Proses 28', 'Proses 29', 'Proses 30',
    'Proses 31', 'Proses 32', 'Proses 33', 'Proses 34', 'Proses 35', 'Proses 36', 'Proses 37', 'Proses 38', 'Proses 39', 'Proses 40',
    'Proses 41', 'Proses 42', 'Proses 43', 'Proses 44', 'Proses 45', 'Proses 46', 'Proses 47'
];
?>

<!DOCTYPE html>
<html>
<head>
  <title>Skill Matrix Comp Assy - MP Development</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="css/sidebar.css" rel="stylesheet">
  <link href="css/employees.css" rel="stylesheet">
  <style>
    .skill-matrix-table {
      font-size: 14px;
      border-collapse: separate;
      border-spacing: 0;
    }
    .skill-matrix-table th {
      writing-mode: horizontal-tb;
      text-orientation: mixed;
      min-width: 60px;
      padding: 12px 8px;
      background-color: #343a40;
      color: white;
      font-weight: bold;
      text-align: center;
      vertical-align: middle;
      border: 1px solid #dee2e6;
      position: sticky;
      top: 0;
      z-index: 20;
    }
    .skill-matrix-table td {
      text-align: center;
      padding: 8px;
      border: 1px solid #dee2e6;
      vertical-align: middle;
      background-color: white;
    }
    
    .skill-level {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      display: inline-block;
      border: 2px solid #000;
      position: relative;
      background: white;
      margin: 3px;
    }
    
    .level-0 { background: white; }
    .level-1 { background: conic-gradient(from 0deg, #000 0deg 90deg, white 90deg 360deg); }
    .level-2 { background: conic-gradient(from 0deg, #000 0deg 180deg, white 180deg 360deg); }
    .level-3 { background: conic-gradient(from 0deg, #000 0deg 270deg, white 270deg 360deg); }
    .level-4 { background: #000; }
    
    .skill-selector {
      width: 60px;
      font-size: 13px;
      padding: 4px;
      border: 1px solid #ced4da;
      border-radius: 4px;
    }
    
    /* Freeze columns hanya untuk informasi karyawan (tidak termasuk proses skill matrix) */
    .employee-info {
      position: sticky;
      background: white;
      z-index: 15;
      font-weight: bold;
      border-right: 2px solid #343a40;
      box-shadow: 2px 0 3px rgba(0,0,0,0.1);
    }
    
    /* Posisi sticky untuk setiap kolom informasi karyawan */
    .employee-info:nth-child(1) { /* NPK */
      left: 0;
      min-width: 80px;
    }
    .employee-info:nth-child(2) { /* Nama */
      left: 80px;
      min-width: 150px;
    }
    .employee-info:nth-child(3) { /* Section */
      left: 230px;
      min-width: 100px;
    }
    .employee-info:nth-child(4) { /* Line */
      left: 330px;
      min-width: 80px;
    }
    .employee-info:nth-child(5) { /* Leader */
      left: 410px;
      min-width: 120px;
    }
    .employee-info:nth-child(6) { /* Proses Saat Ini */
      left: 530px;
      min-width: 150px;
      border-right: 4px solid #dc3545; /* Border merah untuk penanda akhir freeze */
      cursor: pointer; /* Menunjukkan bahwa kolom ini bisa diklik */
    }
    
    /* Hover effect untuk kolom Proses Saat Ini */
    .employee-info:nth-child(6):hover {
      background-color: #e3f2fd !important;
      color: #1976d2;
    }
    
    /* Header sticky untuk kolom employee info */
    .employee-header {
      position: sticky;
      top: 0;
      background-color: #343a40 !important;
      color: white !important;
      z-index: 25;
      border-bottom: 2px solid #495057;
    }
    
    .employee-header:nth-child(1) { left: 0; }
    .employee-header:nth-child(2) { left: 80px; }
    .employee-header:nth-child(3) { left: 230px; }
    .employee-header:nth-child(4) { left: 330px; }
    .employee-header:nth-child(5) { left: 410px; }
    .employee-header:nth-child(6) { left: 530px; }
    
    .table-container {
      overflow: auto;
      max-height: 700px;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      position: relative;
      scroll-behavior: smooth; /* Smooth scrolling untuk auto-scroll */
    }
    
    .skill-cell {
      position: relative;
      min-width: 80px;
    }
    
    .skill-indicator {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      border: 2px solid #000;
      margin: 0 auto 8px;
      display: block;
    }
    
    /* Kotak biru besar untuk proses saat ini */
    .current-process-indicator {
      position: absolute;
      top: -5px;
      left: -5px;
      right: -5px;
      bottom: -5px;
      border: 4px solid #2196F3;
      border-radius: 8px;
      background-color: rgba(33, 150, 243, 0.1);
      z-index: 5;
      pointer-events: none;
    }
    
    .current-process-cell {
      position: relative;
      background-color: rgba(33, 150, 243, 0.05) !important;
    }
    
    /* Styling tambahan untuk header proses */
    .process-header {
      background-color: #495057 !important;
      color: white !important;
      font-size: 13px;
      font-weight: bold;
      text-align: center;
      vertical-align: middle;
      min-width: 80px;
      max-width: 120px;
      word-wrap: break-word;
      hyphens: auto;
    }
    
    /* Highlight untuk header proses saat ini */
    .current-process-header {
      background-color: #2196F3 !important;
      color: white !important;
      box-shadow: 0 0 10px rgba(33, 150, 243, 0.5);
    }
    
    /* Styling untuk baris employee */
    .employee-row:nth-child(even) .employee-info {
      background-color: #f8f9fa;
    }
    
    .employee-row:hover .employee-info {
      background-color: #e9ecef;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .skill-matrix-table {
        font-size: 12px;
      }
      .skill-selector {
        width: 50px;
        font-size: 11px;
      }
      /* Adjust sticky positions for mobile */
      .employee-info:nth-child(2) { left: 60px; min-width: 120px; }
      .employee-info:nth-child(3) { left: 180px; min-width: 80px; }
      .employee-info:nth-child(4) { left: 260px; min-width: 60px; }
      .employee-info:nth-child(5) { left: 320px; min-width: 100px; }
      .employee-info:nth-child(6) { left: 420px; min-width: 120px; }
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
      <a href="sk_comp_assy.php" class="active">📊 SK_CompAssy</a>
      <a href="sk_wclutch.php">📊 SK_WClutch</a>
      <a href="overtime.php">⏰ Overtime</a>
    </div>
  </div>
  <div>
    <hr class="bg-white">
    <a href="settings.php" class="btn btn-light w-100">⚙️ Settings</a>
    <a href="logout.php" class="btn btn-outline-light mt-2 w-100">🚪 Logout</a>
  </div>
</div>

<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3>📊 Skill Matrix - Comp Assy (47 Proses)</h3>
    <div>
      <button class="btn btn-success" onclick="exportToExcel()">📊 Export Excel</button>
      <button class="btn btn-info" onclick="printMatrix()">🖨️ Print</button>
    </div>
  </div>
  
  <div class="alert alert-info">
    <strong>Info:</strong> Skill Matrix untuk evaluasi kemampuan karyawan Comp Assy berdasarkan 47 proses kerja.
    <br><strong>Kode Level Skill:</strong><br>
    <span class="skill-level level-0"></span> <strong>0</strong> = Tidak mampu menjalankan tugas<br>
    <span class="skill-level level-1"></span> <strong>1</strong> = Mampu menyelesaikan tugas<br>
    <span class="skill-level level-2"></span> <strong>2</strong> = Mampu menyelesaikan tugas sesuai dengan standar<br>
    <span class="skill-level level-3"></span> <strong>3</strong> = Mampu menyelesaikan tugas sesuai dengan standar di dalam waktu yang ditetapkan<br>
    <span class="skill-level level-4"></span> <strong>4</strong> = Mampu melakukan poin 4 dan melatih orang lain
  </div>

  <!-- Filter Section -->
  <div class="card mb-3">
    <div class="card-body">
      <h6 class="card-title">🔍 Filter Data</h6>
      <form method="GET" class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Line</label>
          <select name="filter_line" class="form-select">
            <option value="">Semua Line</option>
            <?php foreach($lines as $line): ?>
            <option value="<?= $line ?>" <?= (isset($_GET['filter_line']) && $_GET['filter_line'] == $line) ? 'selected' : '' ?>>
              <?= $line ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <button type="submit" class="btn btn-primary me-2">🔍 Filter</button>
          <a href="sk_comp_assy.php" class="btn btn-secondary">🔄 Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="table-container mt-3">
    <table class="table table-bordered skill-matrix-table">
      <thead class="table-dark">
        <tr>
          <th class="employee-info employee-header">NPK</th>
          <th class="employee-info employee-header">Nama</th>
          <th class="employee-info employee-header">Section</th>
          <th class="employee-info employee-header">Line</th>
          <th class="employee-info employee-header">Leader</th>
          <th class="employee-info employee-header">Proses Saat Ini</th>
          <?php foreach ($compAssyProcesses as $index => $process): ?>
            <th class="process-header" id="process-header-<?= $index ?>"><?= $process ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($compAssyData as $employee): ?>
        <tr class="employee-row">
          <td class="employee-info"><?= $employee['NPK'] ?></td>
          <td class="employee-info"><?= $employee['Nama'] ?></td>
          <td class="employee-info"><?= $employee['Section'] ?></td>
          <td class="employee-info"><?= $employee['Line'] ?></td>
          <td class="employee-info"><?= $employee['Leader'] ?></td>
          <td class="employee-info" onclick="scrollToCurrentProcess('<?= $employee['NPK'] ?>', '<?= $employee['proses_saat_ini'] ?>')">
            <?= $employee['proses_saat_ini'] ?: 'Belum Ada' ?>
          </td>
          <?php foreach ($compAssyProcesses as $index => $process): ?>
            <?php 
            $currentLevel = isset($skillLevels[$employee['NPK']][$process]) ? $skillLevels[$employee['NPK']][$process] : 0;
            $isCurrentProcess = false;
            if ($employee['proses_saat_ini']) {
                $currentProcesses = explode(', ', $employee['proses_saat_ini']);
                $isCurrentProcess = in_array($process, $currentProcesses);
            }
            ?>
            <td class="skill-cell <?= $isCurrentProcess ? 'current-process-cell' : '' ?>" id="skill-cell-<?= $employee['NPK'] ?>-<?= $index ?>">
              <?php if ($isCurrentProcess): ?>
                <div class="current-process-indicator"></div>
              <?php endif; ?>
              <div class="skill-indicator level-<?= $currentLevel ?>" id="indicator-<?= $employee['NPK'] ?>-<?= $index ?>"></div>
              <select class="form-select form-select-sm skill-selector" 
                      data-npk="<?= $employee['NPK'] ?>" 
                      data-process="<?= $process ?>"
                      data-index="<?= $index ?>"
                      onchange="updateSkillLevel(this)">
                <option value="0" <?= $currentLevel == 0 ? 'selected' : '' ?>>0</option>
                <option value="1" <?= $currentLevel == 1 ? 'selected' : '' ?>>1</option>
                <option value="2" <?= $currentLevel == 2 ? 'selected' : '' ?>>2</option>
                <option value="3" <?= $currentLevel == 3 ? 'selected' : '' ?>>3</option>
                <option value="4" <?= $currentLevel == 4 ? 'selected' : '' ?>>4</option>
              </select>
            </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
// Fungsi untuk scroll ke proses saat ini
function scrollToCurrentProcess(npk, currentProcesses) {
    if (!currentProcesses || currentProcesses === 'Belum Ada') {
        alert('Karyawan ini belum memiliki proses saat ini');
        return;
    }
    
    // Ambil proses pertama jika ada multiple proses
    const processArray = currentProcesses.split(', ');
    const firstProcess = processArray[0];
    
    // Cari index proses dalam array compAssyProcesses
    const compAssyProcesses = <?= json_encode($compAssyProcesses) ?>;
    const processIndex = compAssyProcesses.indexOf(firstProcess);
    
    if (processIndex !== -1) {
        // Scroll ke kolom proses yang sesuai
        const targetCell = document.getElementById(`skill-cell-${npk}-${processIndex}`);
        const targetHeader = document.getElementById(`process-header-${processIndex}`);
        
        if (targetCell) {
            // Highlight header proses sementara
            if (targetHeader) {
                targetHeader.classList.add('current-process-header');
                setTimeout(() => {
                    targetHeader.classList.remove('current-process-header');
                }, 3000);
            }
            
            // Scroll ke posisi cell
            targetCell.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
                inline: 'center'
            });
            
            // Tambahkan efek highlight sementara
            targetCell.style.boxShadow = '0 0 15px rgba(33, 150, 243, 0.8)';
            setTimeout(() => {
                targetCell.style.boxShadow = '';
            }, 3000);
        }
    } else {
        alert(`Proses "${firstProcess}" tidak ditemukan dalam daftar proses Comp Assy`);
    }
}

function updateSkillLevel(selectElement) {
    const npk = selectElement.dataset.npk;
    const process = selectElement.dataset.process;
    const level = selectElement.value;
    const index = selectElement.dataset.index;
    
    // Update visual indicator
    const indicator = document.getElementById(`indicator-${npk}-${index}`);
    if (indicator) {
        indicator.className = `skill-indicator level-${level}`;
    }
    
    // Save to database via AJAX
    $.ajax({
        url: 'sk_comp_assy.php',
        method: 'POST',
        data: {
            action: 'update_skill',
            npk: npk,
            process: process,
            skill_level: level
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                console.log('Skill level saved successfully');
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Error saving skill level');
        }
    });
}

function exportToExcel() {
    const table = document.querySelector('table');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    
    XLSX.utils.book_append_sheet(wb, ws, 'Comp Assy Skill Matrix');
    
    const fileName = `skill_matrix_comp_assy_${new Date().toISOString().split('T')[0]}.xlsx`;
    XLSX.writeFile(wb, fileName);
}

function printMatrix() {
    window.print();
}
</script>

<style media="print">
.sidebar { display: none; }
.main-content { margin-left: 0; }
.btn { display: none; }
</style>

</body>
</html>