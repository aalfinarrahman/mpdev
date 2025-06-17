<?php
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Handle date filters
$whereClause = "";
$params = [];

if (isset($_GET['filter_month']) && !empty($_GET['filter_month'])) {
    $whereClause .= " AND MONTH(r.dateCreated) = :month";
    $params[':month'] = $_GET['filter_month'];
}

if (isset($_GET['filter_year']) && !empty($_GET['filter_year'])) {
    $whereClause .= " AND YEAR(r.dateCreated) = :year";
    $params[':year'] = $_GET['filter_year'];
}

// Query untuk data end contracts yang belum ada di replacement
$endContractsStmt = $conn->query("
    SELECT ec.npk, ec.name, ec.dateOut, ec.section, ec.line, ec.leader 
    FROM end_contracts ec 
    WHERE ec.npk NOT IN (SELECT npk_keluar FROM recruitment WHERE npk_keluar IS NOT NULL)
    AND ec.dateOut >= CURDATE() - INTERVAL 30 DAY
    ORDER BY ec.dateOut DESC
");
$endContracts = $endContractsStmt->fetchAll(PDO::FETCH_ASSOC);

// Query untuk data employees (untuk auto-fill pengganti)
$employeesStmt = $conn->query("SELECT * FROM employees WHERE Status = 'Aktif' ORDER BY NPK DESC");
$employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle POST request untuk tambah/edit replacement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_auto'])) {
        // Proses otomatis untuk multiple end contracts
        if (isset($_POST['selected_contracts']) && is_array($_POST['selected_contracts'])) {
            foreach ($_POST['selected_contracts'] as $npk) {
                // Cari data end contract
                $contractStmt = $conn->prepare("SELECT * FROM end_contracts WHERE npk = ?");
                $contractStmt->execute([$npk]);
                $contract = $contractStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($contract) {  
                    $sql = "INSERT INTO recruitment 
                        (`npk_keluar`, `nama_keluar`, `date_out`, `section`, `line`, `leader`, `status`, `dateCreated`) 
                        VALUES (:npk_keluar, :nama_keluar, :date_out, :section, :line, :leader, :status, NOW())";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        ':npk_keluar' => $contract['npk'],
                        ':nama_keluar' => $contract['name'],
                        ':date_out' => $contract['dateOut'],
                        ':section' => $contract['section'],
                        ':line' => $contract['line'],
                        ':leader' => $contract['leader'],
                        ':status' => 'On Time'
                    ]);
                }
            }
        }
        header("Location: replacement.php?success=auto");
        exit();
    }
    
    if (isset($_POST['add_manual'])) {
        $sql = "INSERT INTO recruitment 
            (`npk_keluar`, `nama_keluar`, `date_out`, `npk_pengganti`, `nama_pengganti`, `gender`, `section`, `line`, `leader`, `date_in`, `rekomendasi`, `status`, `dateCreated`) 
            VALUES (:npk_keluar, :nama_keluar, :date_out, :npk_pengganti, :nama_pengganti, :gender, :section, :line, :leader, :date_in, :rekomendasi, :status, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':npk_keluar' => $_POST['npk_keluar'],
            ':nama_keluar' => $_POST['nama_keluar'],
            ':date_out' => $_POST['date_out'],
            ':npk_pengganti' => $_POST['npk_pengganti'],
            ':nama_pengganti' => $_POST['nama_pengganti'],
            ':gender' => $_POST['gender'],
            ':section' => $_POST['section'],
            ':line' => $_POST['line'],
            ':leader' => $_POST['leader'],
            ':date_in' => $_POST['date_in'],
            ':rekomendasi' => $_POST['rekomendasi'],
            ':status' => $_POST['status']
        ]);
        
        header("Location: replacement.php?success=manual");
        exit();
    }
    
    if (isset($_POST['edit'])) {
        $sql = "UPDATE recruitment SET 
            npk_pengganti = :npk_pengganti,
            nama_pengganti = :nama_pengganti,
            gender = :gender,
            date_in = :date_in,
            rekomendasi = :rekomendasi,
            status = :status
            WHERE id = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':npk_pengganti' => $_POST['npk_pengganti'],
            ':nama_pengganti' => $_POST['nama_pengganti'],
            ':gender' => $_POST['gender'],
            ':date_in' => $_POST['date_in'],
            ':rekomendasi' => $_POST['rekomendasi'],
            ':status' => $_POST['status'],
            ':id' => $_POST['id']
        ]);
        
        header("Location: replacement.php?success=edit");
        exit();
    }
}

// Handle GET request untuk delete
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM recruitment WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: replacement.php?success=delete");
    exit();
}

// Get filtered replacement data
$sql = "SELECT * FROM recruitment r WHERE 1=1" . $whereClause . " ORDER BY r.dateCreated DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Replacement - MP Development</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="css/sidebar.css" rel="stylesheet">
  <link href="css/employees.css" rel="stylesheet">
</head>
<body>
<div class="sidebar">
  <div>
    <h4 class="mb-4">🛠️ MPD MS</h4>
    <div class="nav-links">
      <a href="dashboard.php">🏠 Dashboard</a>
      <a href="employees.php">📋 Database MP</a>
      <a href="end_contracts.php">📅 End Contract</a>
      <a href="replacement.php" class="active">🔁 Replacement</a>
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
    <a href="settings.php" class="btn btn-light w-100">⚙️ Settings</a>
    <a href="logout.php" class="btn btn-outline-light mt-2 w-100">🚪 Logout</a>
  </div>
</div>

<div class="main-content">
  <h3 class="mb-4">🔁 Data Replacement</h3>

  <?php if (isset($_GET['success'])): ?>
    <?php if ($_GET['success'] == 'auto'): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        Data replacement otomatis berhasil ditambahkan!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php elseif ($_GET['success'] == 'manual'): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        Data replacement manual berhasil ditambahkan!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php elseif ($_GET['success'] == 'edit'): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        Data replacement berhasil diupdate!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php elseif ($_GET['success'] == 'delete'): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        Data replacement berhasil dihapus!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
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
            for($year = $currentYear - 2; $year <= $currentYear + 1; $year++): 
            ?>
              <option value="<?= $year ?>" <?= (isset($_GET['filter_year']) && $_GET['filter_year'] == $year) ? 'selected' : '' ?>>
                <?= $year ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" class="btn btn-primary me-2">Filter</button>
          <a href="replacement.php" class="btn btn-secondary">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Auto Add Section -->
  <?php if (!empty($endContracts)): ?>
  <div class="card mb-4">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0">📋 End Contracts Tersedia (30 Hari Terakhir)</h5>
    </div>
    <div class="card-body">
      <form method="POST">
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th><input type="checkbox" id="selectAll"></th>
                <th>NPK</th>
                <th>Nama</th>
                <th>Date Out</th>
                <th>Section</th>
                <th>Line</th>
                <th>Leader</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($endContracts as $contract): ?>
              <tr>
                <td><input type="checkbox" name="selected_contracts[]" value="<?= $contract['npk'] ?>"></td>
                <td><?= $contract['npk'] ?></td>
                <td><?= $contract['name'] ?></td>
                <td><?= $contract['dateOut'] ?></td>
                <td><?= $contract['section'] ?></td>
                <td><?= $contract['line'] ?></td>
                <td><?= $contract['leader'] ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button type="submit" name="add_auto" class="btn btn-success">+ Tambah Terpilih ke Replacement</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Manual Add Button -->
  <div class="d-flex justify-content-between mb-3">
    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#manualModal">+ Tambah Manual (Urgent)</button>
  </div>

  <!-- Replacement Data Table -->
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">📊 Data Replacement</h5>
    </div>
    <div class="card-body">
      <table id="replacementTable" class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>NPK Keluar</th>
            <th>Nama Keluar</th>
            <th>Date Out</th>
            <th>NPK Pengganti</th>
            <th>Nama Pengganti</th>
            <th>Section</th>
            <th>Line</th>
            <th>Rekomendasi</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($data as $row): ?>
          <tr>
            <td><?= $row['npk_keluar'] ?></td>
            <td><?= $row['nama_keluar'] ?></td>
            <td><?= $row['date_out'] ?></td>
            <td><?= $row['npk_pengganti'] ?? '-' ?></td>
            <td><?= $row['nama_pengganti'] ?? '-' ?></td>
            <td><?= $row['section'] ?></td>
            <td><?= $row['line'] ?></td>
            <td><?= $row['rekomendasi'] ?? '-' ?></td>
            <td>
              <?php if ($row['status'] == 'On Time'): ?>
                <span class="badge bg-success"><?= $row['status'] ?></span>
              <?php elseif ($row['status'] == 'Delay'): ?>
                <span class="badge bg-warning"><?= $row['status'] ?></span>
              <?php else: ?>
                <span class="badge bg-danger"><?= $row['status'] ?></span>
              <?php endif; ?>
            </td>
            <td>
              <button class="btn btn-sm btn-primary" onclick="editReplacement(<?= htmlspecialchars(json_encode($row)) ?>)">Edit</button>
              <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus?')">Delete</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Manual Add Modal -->
<div class="modal fade" id="manualModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Tambah Replacement Manual</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <h6>Data Karyawan Keluar</h6>
              <div class="mb-3">
                <label class="form-label">End Contract</label>
                <select class="form-select" id="endContractSelect" onchange="fillEndContractData()">
                  <option value="">Pilih End Contract</option>
                  <?php 
                  $allEndContracts = $conn->query("SELECT * FROM end_contracts ORDER BY dateOut DESC")->fetchAll(PDO::FETCH_ASSOC);
                  foreach ($allEndContracts as $ec): 
                  ?>
                    <option value='<?= json_encode($ec) ?>'><?= $ec['npk'] ?> - <?= $ec['name'] ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">NPK Keluar</label>
                <input type="text" class="form-control" name="npk_keluar" id="npk_keluar" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Nama Keluar</label>
                <input type="text" class="form-control" name="nama_keluar" id="nama_keluar" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Date Out</label>
                <input type="date" class="form-control" name="date_out" id="date_out" required>
              </div>
            </div>
            <div class="col-md-6">
              <h6>Data Karyawan Pengganti</h6>
              <div class="mb-3">
                <label class="form-label">Employee Database</label>
                <select class="form-select" id="employeeSelect" onchange="fillEmployeeData()">
                  <option value="">Pilih Employee</option>
                  <?php foreach ($employees as $emp): ?>
                    <option value='<?= json_encode($emp) ?>'><?= $emp['NPK'] ?> - <?= $emp['Nama'] ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">NPK Pengganti</label>
                <input type="text" class="form-control" name="npk_pengganti" id="npk_pengganti">
              </div>
              <div class="mb-3">
                <label class="form-label">Nama Pengganti</label>
                <input type="text" class="form-control" name="nama_pengganti" id="nama_pengganti">
              </div>
              <div class="mb-3">
                <label class="form-label">Gender</label>
                <input type="text" class="form-control" name="gender" id="gender" readonly>
              </div>
              <div class="mb-3">
                <label class="form-label">Section</label>
                <input type="text" class="form-control" name="section" id="section" readonly>
              </div>
              <div class="mb-3">
                <label class="form-label">Line</label>
                <input type="text" class="form-control" name="line" id="line" readonly>
              </div>
              <div class="mb-3">
                <label class="form-label">Leader</label>
                <input type="text" class="form-control" name="leader" id="leader" readonly>
              </div>
              <div class="mb-3">
                <label class="form-label">Date In</label>
                <input type="date" class="form-control" name="date_in" id="date_in" readonly>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Rekomendasi</label>
            <textarea class="form-control" name="rekomendasi" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status" required>
              <option value="On Time">On Time</option>
              <option value="Delay">Delay</option>
              <option value="Over">Over</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="add_manual" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Replacement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="id" id="edit_id">
          <div class="mb-3">
            <label class="form-label">Employee Database</label>
            <select class="form-select" id="editEmployeeSelect" onchange="fillEditEmployeeData()">
              <option value="">Pilih Employee</option>
              <?php foreach ($employees as $emp): ?>
                <option value='<?= json_encode($emp) ?>'><?= $emp['NPK'] ?> - <?= $emp['Nama'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">NPK Pengganti</label>
            <input type="text" class="form-control" name="npk_pengganti" id="edit_npk_pengganti">
          </div>
          <div class="mb-3">
            <label class="form-label">Nama Pengganti</label>
            <input type="text" class="form-control" name="nama_pengganti" id="edit_nama_pengganti">
          </div>
          <div class="mb-3">
            <label class="form-label">Gender</label>
            <input type="text" class="form-control" name="gender" id="edit_gender" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Date In</label>
            <input type="date" class="form-control" name="date_in" id="edit_date_in" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Rekomendasi</label>
            <textarea class="form-control" name="rekomendasi" id="edit_rekomendasi" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status" id="edit_status" required>
              <option value="On Time">On Time</option>
              <option value="Delay">Delay</option>
              <option value="Over">Over</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="edit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
  $('#replacementTable').DataTable({
    language: {
      url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
    }
  });
  
  // Select all checkbox
  $('#selectAll').change(function() {
    $('input[name="selected_contracts[]"]').prop('checked', this.checked);
  });
});

function fillEndContractData() {
  const select = document.getElementById('endContractSelect');
  const data = JSON.parse(select.value || '{}');
  
  document.getElementById('npk_keluar').value = data.npk || '';
  document.getElementById('nama_keluar').value = data.name || '';
  document.getElementById('date_out').value = data.dateOut || '';
}

function fillEmployeeData() {
  const select = document.getElementById('employeeSelect');
  const data = JSON.parse(select.value || '{}');
  
  document.getElementById('npk_pengganti').value = data.NPK || '';
  document.getElementById('nama_pengganti').value = data.Nama || '';
  document.getElementById('gender').value = data.Gender || '';
  document.getElementById('section').value = data.Section || '';
  document.getElementById('line').value = data.Line || '';
  document.getElementById('leader').value = data.Leader || '';
  document.getElementById('date_in').value = data.DateIn || '';
}

function fillEditEmployeeData() {
  const select = document.getElementById('editEmployeeSelect');
  const data = JSON.parse(select.value || '{}');
  
  document.getElementById('edit_npk_pengganti').value = data.NPK || '';
  document.getElementById('edit_nama_pengganti').value = data.Nama || '';
  document.getElementById('edit_gender').value = data.Gender || '';
  document.getElementById('edit_date_in').value = data.DateIn || '';
}

function editReplacement(data) {
  document.getElementById('edit_id').value = data.id;
  document.getElementById('edit_npk_pengganti').value = data.npk_pengganti || '';
  document.getElementById('edit_nama_pengganti').value = data.nama_pengganti || '';
  document.getElementById('edit_gender').value = data.gender || '';
  document.getElementById('edit_date_in').value = data.date_in || '';
  document.getElementById('edit_rekomendasi').value = data.rekomendasi || '';
  document.getElementById('edit_status').value = data.status || '';
  
  new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
</body>
</html>