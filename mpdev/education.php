<?php
require_once 'auth_check.php';
checkAuth(['admin', 'manager', 'spv', 'trainer', 'leader', 'foreman']);
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Define processes for different sections
$compAssyProcesses = [
    'Mizusumashi Towing', 'Mizusumashi Shaft', 'Pre Check', 'Part Washing Big Part (IN)', 'Part Washing Inner Part (IN)', 'Pass Room (Prepare Piston)', 'Pass Room (Prepare Gasket)', 'Prepare Thrust Bearing', 'Prepare Oring PRV', 'Bearing Assy',
    'Bushing Assy', 'Mizusumashi Assy', 'Proses 13', 'Proses 14', 'Proses 15', 'Proses 16', 'Proses 17', 'Proses 18', 'Proses 19', 'Proses 20',
    'Proses 21', 'Proses 22', 'Proses 23', 'Proses 24', 'Proses 25', 'Proses 26', 'Proses 27', 'Proses 28', 'Proses 29', 'Proses 30',
    'Proses 31', 'Proses 32', 'Proses 33', 'Proses 34', 'Proses 35', 'Proses 36', 'Proses 37', 'Proses 38', 'Proses 39', 'Proses 40',
    'Proses 41', 'Proses 42', 'Proses 43', 'Proses 44', 'Proses 45', 'Proses 46', 'Proses 47'
];

$compWClutchProcesses = [
    'Pre Stator', 'Stator Assy', 'Rotor Assy', 'Washer Selection', 'Bracket Assy', 'Final Check WClutch', 'Packaging', 'Mizusumashi WClutch', 'Repair Man WClutch', 'Prepare Bracket'
];

// Handle AJAX request for getting processes by section
if (isset($_GET['action']) && $_GET['action'] === 'get_processes') {
    $section = $_GET['section'] ?? '';
    $processes = [];
    
    if ($section === 'Comp Assy') {
        $processes = $compAssyProcesses;
    } elseif ($section === 'Comp WClutch') {
        $processes = $compWClutchProcesses;
    }
    
    header('Content-Type: application/json');
    echo json_encode($processes);
    exit;
}

// Handle date filters
$whereClause = "";
$params = [];

if (isset($_GET['filter_month']) && !empty($_GET['filter_month'])) {
    $whereClause .= " AND MONTH(dateEdukasi) = :month";
    $params[':month'] = $_GET['filter_month'];
}

if (isset($_GET['filter_year']) && !empty($_GET['filter_year'])) {
    $whereClause .= " AND YEAR(dateEdukasi) = :year";
    $params[':year'] = $_GET['filter_year'];
}

// Get employees data for auto-fill
$employeesStmt = $conn->query("SELECT * FROM employees ORDER BY NPK ASC");
$employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        // Handle file upload
        $raportPath = null;
        if (isset($_FILES['raport']) && $_FILES['raport']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/raport/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = time() . '_' . $_FILES['raport']['name'];
            $raportPath = $uploadDir . $fileName;
            move_uploaded_file($_FILES['raport']['tmp_name'], $raportPath);
        }
        
        // Calculate datePlanning (6 months after dateEdukasi)
        $dateEdukasi = new DateTime($_POST['dateEdukasi']);
        $datePlanning = $dateEdukasi->add(new DateInterval('P6M'))->format('Y-m-d');
        
        $sql = "INSERT INTO education 
            (npk, name, section, line, leader, namaPos, category, dateEdukasi, datePlanning, raport, status) 
            VALUES (:npk, :name, :section, :line, :leader, :namaPos, :category, :dateEdukasi, :datePlanning, :raport, :status)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':npk' => $_POST['npk'],
            ':name' => $_POST['name'],
            ':section' => $_POST['section'],
            ':line' => $_POST['line'],
            ':leader' => $_POST['leader'],
            ':namaPos' => $_POST['namaPos'],
            ':category' => $_POST['category'],
            ':dateEdukasi' => $_POST['dateEdukasi'],
            ':datePlanning' => $datePlanning,
            ':raport' => $raportPath,
            ':status' => $_POST['status']
        ]);
        
        header("Location: education.php?success=1");
        exit();
    }
}

$sql = "SELECT * FROM education WHERE 1=1" . $whereClause . " ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Edukasi - MP Development</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <!-- Add Select2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
  <link href="css/sidebar.css" rel="stylesheet">
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
      <a href="education.php" class="active">🎓 Edukasi</a>
      <a href="education_schedule.php">📆 Jadwal Edukasi</a>
      <a href="mapping.php">🗺️ Mapping MP</a>
      <a href="sk_comp_assy.php">📊 SK_CompAssy</a>
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
  <h3 class="mb-4">🎓 Data Edukasi</h3>
  
  <?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    Data edukasi berhasil ditambahkan!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>
  
  <!-- Filter Section -->
  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title">🔍 Filter Data</h5>
      <form method="GET" class="row g-3">
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
        <div class="col-md-3">
          <label class="form-label">Tahun</label>
          <select name="filter_year" class="form-select">
            <option value="">Semua Tahun</option>
            <?php 
            $currentYear = date('Y');
            for($year = $currentYear - 2; $year <= $currentYear + 2; $year++): 
            ?>
              <option value="<?= $year ?>" <?= (isset($_GET['filter_year']) && $_GET['filter_year'] == $year) ? 'selected' : '' ?>>
                <?= $year ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" class="btn btn-primary me-2">Filter</button>
          <a href="education.php" class="btn btn-secondary">Reset</a>
        </div>
      </form>
    </div>
  </div>
  
  <div class="d-flex justify-content-between align-items-center mb-3">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formModal">+ Tambah Edukasi</button>
  </div>
  
  <table id="educationTable" class="table table-bordered table-striped">
    <thead class="table-dark">
      <tr>
        <th>No</th>
        <th>NPK</th>
        <th>Name</th>
        <th>Section</th>
        <th>Line</th>
        <th>Leader</th>
        <th>Nama Pos</th>
        <th>Category</th>
        <th>Date Education</th>
        <th>Date Planning</th>
        <th>Raport</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($data as $index => $row): ?>
      <tr>
        <td><?= $index + 1 ?></td>
        <td><?= htmlspecialchars($row['npk']) ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= htmlspecialchars($row['section']) ?></td>
        <td><?= htmlspecialchars($row['line']) ?></td>
        <td><?= htmlspecialchars($row['leader']) ?></td>
        <td><?= htmlspecialchars($row['namaPos']) ?></td>
        <td><?= htmlspecialchars($row['category']) ?></td>
        <td><?= htmlspecialchars($row['dateEdukasi']) ?></td>
        <td><?= htmlspecialchars($row['datePlanning']) ?></td>
        <td>
          <?php if ($row['raport']): ?>
            <a href="<?= htmlspecialchars($row['raport']) ?>" target="_blank" class="btn btn-sm btn-info">📄 View PDF</a>
          <?php else: ?>
            <span class="text-muted">No file</span>
          <?php endif; ?>
        </td>
        <td>
          <span class="badge bg-<?= $row['status'] === 'Selesai' ? 'success' : ($row['status'] === 'Berlangsung' ? 'warning' : 'secondary') ?>">
            <?= htmlspecialchars($row['status']) ?>
          </span>
        </td>
        <td>
          <button class="btn btn-sm btn-warning">Edit</button>
          <button class="btn btn-sm btn-danger">Delete</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Modal Form -->
<div class="modal fade" id="formModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Tambah Data Edukasi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Pilih dari Database MP</label>
                <select class="form-select" id="selectEmployee" onchange="fillEmployeeData()">
                  <option value="">-- Pilih Karyawan --</option>
                  <?php foreach ($employees as $emp): ?>
                  <option value="<?= $emp['NPK'] ?>" 
                    data-name="<?= htmlspecialchars($emp['Nama']) ?>"
                    data-section="<?= htmlspecialchars($emp['Section']) ?>"
                    data-line="<?= htmlspecialchars($emp['Line']) ?>"
                    data-leader="<?= htmlspecialchars($emp['Leader']) ?>">
                    <?= $emp['NPK'] ?> - <?= htmlspecialchars($emp['Nama']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div class="row">
                <div class="col-md-6">
                  <label class="form-label">NPK</label>
                  <input type="text" name="npk" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Name</label>
                  <input type="text" name="name" class="form-control" required>
                </div>
              </div>
              
              <div class="row">
                <div class="col-md-4">
                  <label class="form-label">Section</label>
                  <input type="text" name="section" id="sectionInput" class="form-control" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Line</label>
                  <input type="text" name="line" class="form-control" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Leader</label>
                  <input type="text" name="leader" class="form-control" required>
                </div>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Nama Pos</label>
                <select name="namaPos" id="namaPosSelect" class="form-select" required>
                  <option value="">Pilih Nama Pos</option>
                </select>
                <small class="text-muted">Nama pos akan otomatis muncul berdasarkan section yang dipilih</small>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Category</label>
                <select name="category" class="form-select" required>
                  <option value="">Pilih Category</option>
                  <option value="New MP">New MP</option>
                  <option value="Refresh MP">Refresh MP</option>
                  <option value="Skill Up MP">Skill Up MP</option>
                </select>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Date Education</label>
                <input type="date" name="dateEdukasi" class="form-control" required>
                <small class="text-muted">Date Planning akan otomatis 6 bulan setelah tanggal ini</small>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                  <option value="">Pilih Status</option>
                  <option value="Planned">Planned</option>
                  <option value="Berlangsung">Berlangsung</option>
                  <option value="Selesai">Selesai</option>
                </select>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Raport (PDF)</label>
                <input type="file" name="raport" class="form-control" accept=".pdf">
                <small class="text-muted">Upload file PDF untuk raport</small>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="add" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<!-- Add Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
  $(document).ready(function() {
    $('#educationTable').DataTable({
      responsive: true,
      language: {
        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
      }
    });
  });
  
  // Initialize Select2 when modal is shown
  $('#formModal').on('shown.bs.modal', function () {
    // Initialize Select2 for searchable dropdown
    $('#selectEmployee').select2({
      theme: 'bootstrap-5',
      placeholder: '-- Pilih Karyawan --',
      allowClear: true,
      width: '100%',
      dropdownParent: $('#formModal'), // Important: Set parent to modal
      language: {
        noResults: function() {
          return "Tidak ada data yang ditemukan";
        },
        searching: function() {
          return "Mencari...";
        }
      }
    });
    
    // Handle change event for Select2
    $('#selectEmployee').on('change', function() {
      fillEmployeeData();
    });
  });
  
  // Destroy Select2 when modal is hidden to prevent conflicts
  $('#formModal').on('hidden.bs.modal', function () {
    if ($('#selectEmployee').hasClass('select2-hidden-accessible')) {
      $('#selectEmployee').select2('destroy');
    }
  });
  
  function fillEmployeeData() {
    const select = document.getElementById('selectEmployee');
    const option = select.options[select.selectedIndex];
    
    if (option.value) {
      document.querySelector('input[name="npk"]').value = option.value;
      document.querySelector('input[name="name"]').value = option.dataset.name;
      document.querySelector('input[name="section"]').value = option.dataset.section;
      document.querySelector('input[name="line"]').value = option.dataset.line;
      document.querySelector('input[name="leader"]').value = option.dataset.leader;
      
      // Load processes based on section
      loadProcessesBySection(option.dataset.section);
    } else {
      document.querySelector('input[name="npk"]').value = '';
      document.querySelector('input[name="name"]').value = '';
      document.querySelector('input[name="section"]').value = '';
      document.querySelector('input[name="line"]').value = '';
      document.querySelector('input[name="leader"]').value = '';
      
      // Clear nama pos dropdown
      const namaPosSelect = document.getElementById('namaPosSelect');
      namaPosSelect.innerHTML = '<option value="">Pilih Nama Pos</option>';
    }
  }
  
  function loadProcessesBySection(section) {
    if (!section) return;
    
    // Make AJAX request to get processes
    fetch(`education.php?action=get_processes&section=${encodeURIComponent(section)}`)
      .then(response => response.json())
      .then(processes => {
        const namaPosSelect = document.getElementById('namaPosSelect');
        namaPosSelect.innerHTML = '<option value="">Pilih Nama Pos</option>';
        
        processes.forEach(process => {
          const option = document.createElement('option');
          option.value = process;
          option.textContent = process;
          namaPosSelect.appendChild(option);
        });
      })
      .catch(error => {
        console.error('Error loading processes:', error);
      });
  }
  
  // Also add event listener for manual section input changes
  document.getElementById('sectionInput').addEventListener('input', function() {
    loadProcessesBySection(this.value);
  });
</script>
</body>
</html>