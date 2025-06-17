<?php
require_once 'auth_check.php';
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Handle date filters
$whereClause = "";
$params = [];

if (isset($_GET['filter_month']) && !empty($_GET['filter_month'])) {
    $whereClause .= " AND MONTH(ec.dateOut) = :month";
    $params[':month'] = $_GET['filter_month'];
}

if (isset($_GET['filter_year']) && !empty($_GET['filter_year'])) {
    $whereClause .= " AND YEAR(ec.dateOut) = :year";
    $params[':year'] = $_GET['filter_year'];
}

// Add mapping filters
if (isset($_GET['filter_section']) && !empty($_GET['filter_section'])) {
    $whereClause .= " AND ec.section = :section";
    $params[':section'] = $_GET['filter_section'];
}

if (isset($_GET['filter_line']) && !empty($_GET['filter_line'])) {
    $whereClause .= " AND ec.line = :line";
    $params[':line'] = $_GET['filter_line'];
}

// Get unique sections and lines for filter options
$sectionsStmt = $conn->query("SELECT DISTINCT section FROM end_contracts ORDER BY section");
$sections = $sectionsStmt->fetchAll(PDO::FETCH_COLUMN);

$linesStmt = $conn->query("SELECT DISTINCT line FROM end_contracts ORDER BY line");
$lines = $linesStmt->fetchAll(PDO::FETCH_COLUMN);

// Ambil semua karyawan kontrak aktif yang belum ada di end_contracts
$employeeStmt = $conn->query("
    SELECT e.* FROM employees e 
    WHERE e.Tipe = 'Kontrak' 
    AND e.Status = 'Aktif' 
    AND e.NPK NOT IN (SELECT npk FROM end_contracts)
    ORDER BY e.Section, e.Line, e.NPK
");
$availableEmployees = $employeeStmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua karyawan untuk tambah manual (urgent cases)
$allEmployeesStmt = $conn->query("SELECT * FROM employees WHERE Status = 'Aktif' ORDER BY NPK ASC");
$allEmployees = $allEmployeesStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_from_list'])) {
        // Proses multiple selection dari daftar otomatis
        if (!empty($_POST['selected_employees'])) {
            foreach ($_POST['selected_employees'] as $npk) {
                $emp = array_filter($availableEmployees, function($e) use ($npk) {
                    return $e['NPK'] == $npk;
                });
                $emp = reset($emp);
                
                if ($emp) {
                    // Hitung dateOut berdasarkan durasi
                    $durasi = (int)$emp['Durasi'];
                    $dateIn = new DateTime($emp['DateIn']);
                    $dateOut = clone $dateIn;
                    $dateOut->add(new DateInterval('P' . $durasi . 'M'));
                    
                    $sql = "INSERT INTO end_contracts 
                        (`npk`, `name`, `gender`, `section`, `line`, `leader`, `dateIn`, `dateOut`, `durasi`, `reason`) 
                        VALUES (:npk, :name, :gender, :section, :line, :leader, :dateIn, :dateOut, :durasi, :reason)";

                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        ':npk' => $emp['NPK'],
                        ':name' => $emp['Nama'],
                        ':gender' => $emp['Gender'],
                        ':section' => $emp['Section'],
                        ':line' => $emp['Line'],
                        ':leader' => $emp['Leader'],
                        ':dateIn' => $emp['DateIn'],
                        ':dateOut' => $dateOut->format('Y-m-d'),
                        ':durasi' => $emp['Durasi'],
                        ':reason' => $_POST['reason']
                    ]);
                }
            }
        }
        header("Location: end_contracts.php?success=1");
        exit();
    }
    
    // Existing manual add code...
    if (isset($_POST['add'])) {
        $sql = "INSERT INTO end_contracts 
            (`npk`, `name`, `gender`, `section`, `line`, `leader`, `dateIn`, `dateOut`, `durasi`, `reason`) 
            VALUES (:npk, :name, :gender, :section, :line, :leader, :dateIn, :dateOut, :durasi, :reason)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':npk' => $_POST['npk'],
            ':name' => $_POST['name'],
            ':gender' => $_POST['gender'],
            ':section' => $_POST['section'],
            ':line' => $_POST['line'],
            ':leader' => $_POST['leader'],
            ':dateIn' => $_POST['dateIn'],
            ':dateOut' => $_POST['dateOut'],
            ':durasi' => !empty($_POST['durasi']) ? (int)$_POST['durasi'] : 0,
            ':reason' => $_POST['reason']
        ]);

        header("Location: end_contracts.php?success=1");
        exit();
    }
    
    // Edit functionality...
    if (isset($_POST['edit'])) {
        $sql = "UPDATE end_contracts SET 
            `npk` = :npk, `name` = :name, `gender` = :gender, `section` = :section, 
            `line` = :line, `leader` = :leader, `dateIn` = :dateIn, `dateOut` = :dateOut, 
            `durasi` = :durasi, `reason` = :reason 
            WHERE id = :id";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $_POST['id'],
            ':npk' => $_POST['npk'],
            ':name' => $_POST['name'],
            ':gender' => $_POST['gender'],
            ':section' => $_POST['section'],
            ':line' => $_POST['line'],
            ':leader' => $_POST['leader'],
            ':dateIn' => $_POST['dateIn'],
            ':dateOut' => $_POST['dateOut'],
            ':durasi' => $_POST['durasi'],
            ':reason' => $_POST['reason']
        ]);

        header("Location: end_contracts.php?updated=1");
        exit();
    }
}

if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM end_contracts WHERE id = :id");
    $stmt->execute([':id' => $_GET['delete']]);
    header("Location: end_contracts.php?deleted=1");
    exit();
}

// Get filtered data
$sql = "SELECT * FROM end_contracts ec WHERE 1=1" . $whereClause . " ORDER BY ec.dateOut DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html
<head>
  <title>End Contract - MP Development</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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
        <a href="end_contracts.php" class="active">📅 End Contract</a>
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
      <a href="settings.php" class="btn btn-light w-100">⚙️ Settings</a>
      <a href="logout.php" class="btn btn-outline-light mt-2 w-100">🚪 Logout</a>
    </div>
  </div>

  <div class="main-content">
    <h3 class="mb-4">📅 Data End Contract</h3>

    <?php if (isset($_GET['success'])): ?>
      <?php if ($_GET['success'] == '1'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          Data end contract berhasil ditambahkan!
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
    <?php endif; ?>
    
    <?php if (isset($_GET['updated'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        Data berhasil diupdate!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['deleted'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        Data berhasil dihapus!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Filter Section - Moved to Top -->
    <div class="card mb-3">
      <div class="card-body">
        <h5 class="card-title">🔍 Filter Data</h5>
        <form method="GET" class="row g-3">
          <div class="col-md-2">
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
          <div class="col-md-2">
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
          <div class="col-md-2">
            <label class="form-label">Section</label>
            <select name="filter_section" class="form-select">
              <option value="">Semua Section</option>
              <?php foreach($sections as $section): ?>
                <option value="<?= $section ?>" <?= (isset($_GET['filter_section']) && $_GET['filter_section'] == $section) ? 'selected' : '' ?>>
                  <?= $section ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
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
          <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">Filter</button>
            <a href="end_contracts.php" class="btn btn-secondary">Reset</a>
          </div>
        </form>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
    
      <div>
        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#autoAddModal">📋 Proses Otomatis</button>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#manualModal" onclick="resetForm()">⚡ Tambah Manual (Urgent)</button>
      </div>
    </div>
    
    <div class="alert alert-info">
      <strong>Info:</strong> 
      <ul class="mb-0">
        <li><strong>Proses Otomatis:</strong> Pilih dari daftar karyawan kontrak yang akan berakhir</li>
        <li><strong>Tambah Manual:</strong> Untuk kasus urgent/khusus (resign, mutasi, dll)</li>
      </ul>
    </div>

    <!-- Tabel Karyawan Kontrak yang Tersedia -->
    <?php if (!empty($availableEmployees)): ?>
    <div class="card mb-4">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0">📋 Daftar Karyawan Kontrak Aktif (<?= count($availableEmployees) ?> orang)</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>NPK</th>
                <th>Nama</th>
                <th>Section</th>
                <th>Line</th>
                <th>Leader</th>
                <th>Date In</th>
                <th>Durasi (Bulan)</th>
                <th>Perkiraan End</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($availableEmployees as $emp): 
                $durasi = (int)$emp['Durasi'];
                $dateIn = new DateTime($emp['DateIn']);
                $estimatedEnd = clone $dateIn;
                $estimatedEnd->add(new DateInterval('P' . $durasi . 'M'));
              ?>
              <tr>
                <td><?= $emp['NPK'] ?></td>
                <td><?= $emp['Nama'] ?></td>
                <td><?= $emp['Section'] ?></td>
                <td><?= $emp['Line'] ?></td>
                <td><?= $emp['Leader'] ?></td>
                <td><?= $emp['DateIn'] ?></td>
                <td><?= $emp['Durasi'] ?></td>
                <td><?= $estimatedEnd->format('Y-m-d') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>
      
    <table id="endContractTable" class="table table-bordered table-striped">
      <thead class="table-dark">
        <tr>
          <th>NPK</th>
          <th>Nama</th>
          <th>Gender</th>
          <th>Section</th>
          <th>Line</th>
          <th>Leader</th>
          <th>Date In</th>
          <th>Date Out</th>
          <th>Durasi</th>
          <th>Reason</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($data as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['npk']) ?></td>
          <td><?= htmlspecialchars($row['name']) ?></td>
          <td><?= htmlspecialchars($row['gender']) ?></td>
          <td><?= htmlspecialchars($row['section']) ?></td>
          <td><?= htmlspecialchars($row['line']) ?></td>
          <td><?= htmlspecialchars($row['leader']) ?></td>
          <td><?= htmlspecialchars($row['dateIn']) ?></td>
          <td><?= htmlspecialchars($row['dateOut']) ?></td>
          <td><?= htmlspecialchars($row['durasi']) ?></td>
          <td><?= htmlspecialchars($row['reason']) ?></td>
          <td>
            <button class="btn btn-sm btn-warning me-1" 
                    onclick="editData(<?= htmlspecialchars(json_encode($row)) ?>)" 
                    data-bs-toggle="modal" data-bs-target="#manualModal">Edit</button>
            <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" 
               onclick="return confirm('Hapus data ini?')">Delete</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Modal Proses Otomatis -->
  <div class="modal fade" id="autoAddModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <form method="POST" class="p-3">
          <div class="modal-header">
            <h5 class="modal-title">📋 Proses End Contract Otomatis</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <?php if (!empty($availableEmployees)): ?>
            <div class="row">
              <div class="col-md-8">
                <label class="form-label">Pilih Karyawan yang akan End Contract:</label>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                  <table class="table table-sm table-striped">
                    <thead class="sticky-top bg-light">
                      <tr>
                        <th><input type="checkbox" id="selectAll" onchange="toggleAll()"></th>
                        <th>NPK</th>
                        <th>Nama</th>
                        <th>Section</th>
                        <th>Line</th>
                        <th>Perkiraan End</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($availableEmployees as $emp): 
                        $durasi = (int)$emp['Durasi'];
                        $dateIn = new DateTime($emp['DateIn']);
                        $estimatedEnd = clone $dateIn;
                        $estimatedEnd->add(new DateInterval('P' . $durasi . 'M'));
                      ?>
                      <tr>
                        <td><input type="checkbox" name="selected_employees[]" value="<?= $emp['NPK'] ?>" class="employee-checkbox"></td>
                        <td><?= $emp['NPK'] ?></td>
                        <td><?= $emp['Nama'] ?></td>
                        <td><?= $emp['Section'] ?></td>
                        <td><?= $emp['Line'] ?></td>
                        <td><?= $estimatedEnd->format('Y-m-d') ?></td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Reason untuk semua yang dipilih:</label>
                <select name="reason" class="form-select" required>
                  <option value="">Pilih Alasan</option>
                  <option>Kontrak Habis</option>
                  <option>Resign</option>
                  <option>Mutasi</option>
                  <option>Pensiun</option>
                </select>
              </div>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
              Tidak ada karyawan kontrak yang tersedia untuk diproses.
            </div>
            <?php endif; ?>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <?php if (!empty($availableEmployees)): ?>
            <button type="submit" name="add_from_list" class="btn btn-success">Proses yang Dipilih</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Manual (Urgent) -->
  <div class="modal fade" id="manualModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form method="POST" class="p-3" id="endContractForm">
          <input type="hidden" name="id" id="editId">
          <div class="modal-header">
            <h5 class="modal-title" id="modalTitle">⚡ Tambah Manual (Urgent)</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body row g-3">
            <div class="col-md-3">
              <label>NPK</label>
              <select name="npk" id="npkSelect" class="form-select" onchange="autofillEmployeeData()" required>
                <option value="">Pilih NPK</option>
                <?php foreach ($allEmployees as $e): ?>
                <option value="<?= $e['NPK'] ?>"><?= $e['NPK'] ?> - <?= $e['Nama'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3"><label>Nama</label><input name="name" class="form-control" readonly></div>
            <div class="col-md-2"><label>Gender</label><input name="gender" class="form-control" readonly></div>
            <div class="col-md-2"><label>Section</label><input name="section" class="form-control" readonly></div>
            <div class="col-md-2"><label>Line</label><input name="line" class="form-control" readonly></div>
            <div class="col-md-3"><label>Leader</label><input name="leader" class="form-control" readonly></div>
            <div class="col-md-2"><label>Date In</label><input name="dateIn" class="form-control" readonly></div>
            <div class="col-md-2"><label>Date Out</label><input name="dateOut" class="form-control" type="date" required></div>
            <div class="col-md-2"><label>Durasi</label><input name="durasi" class="form-control" readonly></div>
            <div class="col-md-3">
              <label>Reason</label>
              <select name="reason" class="form-select" required>
                <option value="">Pilih Alasan</option>
                <option>Kontrak Habis</option>
                <option>Resign</option>
                <option>Mutasi</option>
                <option>Pensiun</option>
                <option>PHK</option>
                <option>Lainnya</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" name="add" id="submitBtn" class="btn btn-warning">Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  
  <script>
  const allEmployees = <?= json_encode($allEmployees) ?>;
  
  function toggleAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
  }
  
  function autofillEmployeeData() {
    const npk = document.querySelector('[name=npk]').value;
    const emp = allEmployees.find(e => e.NPK == npk);
    if (!emp) return;
    
    document.querySelector('[name=name]').value = emp.Nama;
    document.querySelector('[name=gender]').value = emp.Gender;
    document.querySelector('[name=section]').value = emp.Section;
    document.querySelector('[name=line]').value = emp.Line;
    document.querySelector('[name=leader]').value = emp.Leader;
    document.querySelector('[name=dateIn]').value = emp.DateIn;
    document.querySelector('[name=durasi]').value = emp.Durasi || '';
  }
  
  function resetForm() {
    document.getElementById('endContractForm').reset();
    document.getElementById('editId').value = '';
    document.getElementById('modalTitle').textContent = '⚡ Tambah Manual (Urgent)';
    document.getElementById('submitBtn').name = 'add';
    document.getElementById('submitBtn').textContent = 'Simpan';
  }
  
  function editData(data) {
    document.getElementById('editId').value = data.id;
    document.querySelector('[name=npk]').value = data.npk;
    document.querySelector('[name=name]').value = data.name;
    document.querySelector('[name=gender]').value = data.gender;
    document.querySelector('[name=section]').value = data.section;
    document.querySelector('[name=line]').value = data.line;
    document.querySelector('[name=leader]').value = data.leader;
    document.querySelector('[name=dateIn]').value = data.dateIn;
    document.querySelector('[name=dateOut]').value = data.dateOut;
    document.querySelector('[name=durasi]').value = data.durasi;
    document.querySelector('[name=reason]').value = data.reason;
    
    document.getElementById('modalTitle').textContent = 'Edit End Contract';
    document.getElementById('submitBtn').name = 'edit';
    document.getElementById('submitBtn').textContent = 'Update';
  }
  
  // Initialize DataTable and Select2
  $(document).ready(function() {
    $('#endContractTable').DataTable({
      responsive: true,
      language: {
        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
      }
    });
  });
  
  // Initialize Select2 when modal is shown
  $('#manualModal').on('shown.bs.modal', function () {
    // Initialize Select2 for searchable NPK dropdown
    $('#npkSelect').select2({
      theme: 'bootstrap-5',
      dropdownParent: $('#manualModal'),
      placeholder: 'Cari NPK atau Nama...',
      allowClear: true,
      width: '100%'
    });
  });
  
  // Destroy Select2 when modal is hidden to prevent conflicts
  $('#manualModal').on('hidden.bs.modal', function () {
    if ($('#npkSelect').hasClass('select2-hidden-accessible')) {
      $('#npkSelect').select2('destroy');
    }
  });
  </script>
</body>
</html>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart data preparation
<?php
// Get data for charts
$sectionData = $conn->query("SELECT section, COUNT(*) as count FROM end_contracts GROUP BY section")->fetchAll(PDO::FETCH_ASSOC);
$monthData = $conn->query("SELECT MONTH(dateOut) as month, COUNT(*) as count FROM end_contracts GROUP BY MONTH(dateOut) ORDER BY month")->fetchAll(PDO::FETCH_ASSOC);
?>

// Section Chart
const sectionCtx = document.getElementById('sectionChart').getContext('2d');
new Chart(sectionCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($sectionData, 'section')) ?>,
        datasets: [{
            label: 'Jumlah End Contract',
            data: <?= json_encode(array_column($sectionData, 'count')) ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.8)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Month Chart
const monthCtx = document.getElementById('monthChart').getContext('2d');
const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
new Chart(monthCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(function($item) use ($monthNames) { return $monthNames[$item['month']-1]; }, $monthData)) ?>,
        datasets: [{
            label: 'Jumlah End Contract',
            data: <?= json_encode(array_column($monthData, 'count')) ?>,
            backgroundColor: 'rgba(255, 99, 132, 0.8)',
            borderColor: 'rgba(255, 99, 132, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>
