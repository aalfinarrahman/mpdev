<?php
require_once 'auth_check.php';
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Handle filters
$whereClause = "WHERE 1=1";
$params = [];

if (isset($_GET['filter_section']) && !empty($_GET['filter_section'])) {
    $whereClause .= " AND Section = :section";
    $params[':section'] = $_GET['filter_section'];
}

if (isset($_GET['filter_line']) && !empty($_GET['filter_line'])) {
    $whereClause .= " AND Line = :line";
    $params[':line'] = $_GET['filter_line'];
}

if (isset($_GET['filter_status']) && !empty($_GET['filter_status'])) {
    $whereClause .= " AND Status = :status";
    $params[':status'] = $_GET['filter_status'];
}

// Get unique sections and lines for filter options
$sectionsStmt = $conn->query("SELECT DISTINCT Section FROM employees ORDER BY Section");
$sections = $sectionsStmt->fetchAll(PDO::FETCH_COLUMN);

$linesStmt = $conn->query("SELECT DISTINCT Line FROM employees ORDER BY Line");
$lines = $linesStmt->fetchAll(PDO::FETCH_COLUMN);

// Get filtered data
$sql = "SELECT * FROM employees " . $whereClause . " ORDER BY Section, Line, NPK";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Mapping MP - MP Development</title>
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
      <a href="replacement.php">🔁 Replacement</a>
      <a href="education.php">🎓 Edukasi</a>
      <a href="education_schedule.php">📆 Jadwal Edukasi</a>
      <a href="mapping.php" class="active">🗺️ Mapping MP</a>
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
  <h3 class="mb-4">🗺️ Mapping Manpower</h3>
  
  <!-- Filter Section -->
  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title">🔍 Filter Data</h5>
      <form method="GET" class="row g-3">
        <div class="col-md-3">
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
        <div class="col-md-3">
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
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="filter_status" class="form-select">
            <option value="">Semua Status</option>
            <option value="Aktif" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Aktif') ? 'selected' : '' ?>>Aktif</option>
            <option value="Tidak Aktif" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Tidak Aktif') ? 'selected' : '' ?>>Tidak Aktif</option>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" class="btn btn-primary me-2">Filter</button>
          <a href="mapping.php" class="btn btn-secondary">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card bg-primary text-white">
        <div class="card-body">
          <h5 class="card-title">Total Karyawan</h5>
          <h2><?= count($data) ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-success text-white">
        <div class="card-body">
          <h5 class="card-title">Aktif</h5>
          <h2><?= count(array_filter($data, function($emp) { return $emp['Status'] == 'Aktif'; })) ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-secondary text-white">
        <div class="card-body">
          <h5 class="card-title">Tidak Aktif</h5>
          <h2><?= count(array_filter($data, function($emp) { return $emp['Status'] == 'Tidak Aktif'; })) ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-info text-white">
        <div class="card-body">
          <h5 class="card-title">Total Section</h5>
          <h2><?= count(array_unique(array_column($data, 'Section'))) ?></h2>
        </div>
      </div>
    </div>
  </div>

  <!-- Chart Section -->
  <div class="row mb-4">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <h5>📊 Mapping MP Comp Assy</h5>
        </div>
        <div class="card-body">
          <canvas id="sectionChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <h5>📊 Mapping MP Comp WClutch</h5>
        </div>
        <div class="card-body">
          <canvas id="lineChart"></canvas>
        </div>
      </div>
    </div>
  </div>
  
  <table id="mappingTable" class="table table-bordered table-striped">
    <thead class="table-dark">
      <tr>
        <th>NPK</th>
        <th>Nama</th>
        <th>Section</th>
        <th>Line</th>
        <th>Leader</th>
        <th>Function</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($data as $row): ?>
      <tr>
        <td><?= $row['NPK'] ?></td>
        <td><?= $row['Nama'] ?></td>
        <td><?= $row['Section'] ?></td>
        <td><?= $row['Line'] ?></td>
        <td><?= $row['Leader'] ?></td>
        <td><?= $row['Function'] ?></td>
        <td>
          <span class="badge <?= $row['Status'] == 'Aktif' ? 'bg-success' : 'bg-secondary' ?>">
            <?= $row['Status'] ?>
          </span>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function() {
  $('#mappingTable').DataTable({
    responsive: true,
    language: {
      url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
    }
  });
});

// Chart data preparation
<?php
// Get data for charts - filter by specific sections
$compAssyData = array_filter($data, function($emp) { 
    return $emp['Section'] == 'Comp Assy'; 
});
$compWClutchData = array_filter($data, function($emp) { 
    return $emp['Section'] == 'Comp WClutch'; 
});

$compAssyLineCounts = array_count_values(array_column($compAssyData, 'Line'));
$compWClutchLineCounts = array_count_values(array_column($compWClutchData, 'Line'));
?>

// Comp Assy Chart (previously Section Chart)
const sectionCtx = document.getElementById('sectionChart').getContext('2d');
new Chart(sectionCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($compAssyLineCounts)) ?>,
        datasets: [{
            label: 'Jumlah Karyawan Comp Assy',
            data: <?= json_encode(array_values($compAssyLineCounts)) ?>,
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

// Comp WClutch Chart (previously Line Chart)
const lineCtx = document.getElementById('lineChart').getContext('2d');
new Chart(lineCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($compWClutchLineCounts)) ?>,
        datasets: [{
            label: 'Jumlah Karyawan Comp WClutch',
            data: <?= json_encode(array_values($compWClutchLineCounts)) ?>,
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
</body>
</html>