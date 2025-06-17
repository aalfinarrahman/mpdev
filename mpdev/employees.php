<?php
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Handle date filters
$whereClause = "";
$params = [];

if (isset($_GET['filter_month']) && !empty($_GET['filter_month'])) {
    $whereClause .= " AND MONTH(DateIn) = :month";
    $params[':month'] = $_GET['filter_month'];
}

if (isset($_GET['filter_year']) && !empty($_GET['filter_year'])) {
    $whereClause .= " AND YEAR(DateIn) = :year";
    $params[':year'] = $_GET['filter_year'];
}

$sql = "SELECT * FROM employees WHERE 1=1" . $whereClause . " ORDER BY NPK ASC";
$employeeStmt = $conn->prepare($sql);
$employeeStmt->execute($params);
$employees = $employeeStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if employee already exists
    $checkStmt = $conn->prepare("SELECT NPK FROM employees WHERE NPK = ?");
    $checkStmt->execute([$_POST['npk']]);
    $existingEmployee = $checkStmt->fetch();
    
    if ($existingEmployee || isset($_POST['edit'])) {
        // Update existing employee
        $sql = "UPDATE employees SET 
            `Nama` = :name, `Gender` = :gender, `Section` = :section, `Line` = :line, `Leader` = :leader, 
            `DateIn` = :dateIn, `Status` = :status, `Function` = :functionRole, `Tipe` = :tipe, `Durasi` = :durasi
            WHERE `NPK` = :npk";
    } else {
        // Insert new employee
        $sql = "INSERT INTO employees 
            (`NPK`, `Nama`, `Gender`, `Section`, `Line`, `Leader`, `DateIn`, `Status`, `Function`, `Tipe`, `Durasi`) 
            VALUES (:npk, :name, :gender, :section, :line, :leader, :dateIn, :status, :functionRole, :tipe, :durasi)";
    }
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':npk' => $_POST['npk'],
            ':name' => $_POST['name'],
            ':gender' => $_POST['gender'],
            ':section' => $_POST['section'],
            ':line' => $_POST['line'],
            ':leader' => $_POST['leader'],
            ':dateIn' => $_POST['dateIn'],
            ':status' => $_POST['status'],
            ':functionRole' => $_POST['functionrole'],
            ':tipe' => $_POST['tipe'],
            ':durasi' => ($_POST['tipe'] === 'Kontrak') ? $_POST['durasi'] : null
        ]);
        
        $message = $existingEmployee ? "Data karyawan berhasil diupdate!" : "Karyawan baru berhasil ditambahkan!";
        echo "<script>alert('$message'); window.location.href='employees.php';</script>";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo "<script>alert('Error: NPK sudah ada di database!'); window.history.back();</script>";
        } else {
            echo "<script>alert('Error: " . $e->getMessage() . "'); window.history.back();</script>";
        }
    }
}

if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM employees WHERE `NPK` = :npk");
    $stmt->execute([':npk' => $_GET['delete']]);
    header("Location: employees.php?deleted=1");
    exit();
}

$sql = "SELECT * FROM employees WHERE 1=1" . $whereClause . " ORDER BY NPK DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Database MP - MP Development</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
  <link href="css/sidebar.css" rel="stylesheet">
  <link href="css/employees.css" rel="stylesheet">
</head>
<body>
<div class="sidebar">
  <div>
    <h4 class="mb-4">🛠️ MPD MS</h4>
    <div class="nav-links">
      <a href="dashboard.php">🏠 Dashboard</a>
      <a href="employees.php" class="active">📋 Database MP</a>
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
    <a href="settings.php" class="btn btn-light w-100">⚙️ Settings</a>
    <a href="logout.php" class="btn btn-outline-light mt-2 w-100">🚪 Logout</a>
  </div>
</div>

<div class="main-content">
  <h3 class="mb-4">📊 Data Karyawan</h3>

  <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Data berhasil disimpan.</div>
  <?php elseif (isset($_GET['deleted'])): ?>
    <div class="alert alert-danger">Data berhasil dihapus.</div>
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
            for($year = $currentYear - 5; $year <= $currentYear + 1; $year++): 
            ?>
              <option value="<?= $year ?>" <?= (isset($_GET['filter_year']) && $_GET['filter_year'] == $year) ? 'selected' : '' ?>>
                <?= $year ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" class="btn btn-primary me-2">Filter</button>
          <a href="employees.php" class="btn btn-secondary">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="d-flex justify-content-between mb-3">
    <button class="btn btn-primary mb-3" id="btnTambah">+ Tambah</button>
  </div>

  <table id="employeeTable" class="table table-hover table-bordered">
    <thead>
      <tr>
        <th>NPK</th><th>Nama</th><th>Gender</th><th>Section</th><th>Line</th>
        <th>Leader</th><th>DateIn</th><th>Status</th><th>Tipe</th><th>Function</th><th>Durasi</th><th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($data as $row): ?>
      <tr>
        <td><?= $row['NPK'] ?></td>
        <td><?= $row['Nama'] ?></td>
        <td><?= $row['Gender'] ?></td>
        <td><?= $row['Section'] ?></td>
        <td><?= $row['Line'] ?></td>
        <td><?= $row['Leader'] ?></td>
        <td><?= $row['DateIn'] ?></td>
        <td><span class="badge bg-success"><?= $row['Status'] ?></span></td>
        <td><span class="badge bg-info text-dark"><?= $row['Tipe'] ?></span></td>
        <td><?= $row['Function'] ?></td>
        <td><?= $row['Durasi'] ?></td>
        <td>
        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#formModal" data-emp='<?= json_encode($row) ?>'>Edit</button>
        <a href="?delete=<?= $row['NPK'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')">Delete</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="formModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" class="p-3">
        <div class="modal-header">
          <h5 class="modal-title">Form Data MP</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body row g-3">
          <div class="col-md-3">
            <label>NPK</label>
            <input type="number" name="npk" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label>Nama</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label>Gender</label>
            <select name="gender" class="form-select" required>
              <option value="">Pilih Gender</option>
              <option value="Pria">Pria</option>
              <option value="Wanita">Wanita</option>
            </select>
          </div>
          <div class="col-md-3">
            <label>Section</label>
            <select name="section" class="form-select" required>
              <option value="">Pilih Section</option>
              <option value="Comp Assy">Comp Assy</option>
              <option value="Comp WClutch">Comp WClutch</option>
            </select>
          </div>
          <div class="col-md-3">
            <label>Line</label>
            <select name="line" class="form-select" required>
              <option value="">Pilih Line</option>
              <option value="1A">1A</option>
              <option value="1B">1B</option>
              <option value="2A">2A</option>
              <option value="2B">2B</option>
              <option value="3">3</option>
              <option value="4A">4A</option>
              <option value="4B">4B</option>
            </select>
          </div>
          <div class="col-md-3">
            <label>Leader</label>
            <select name="leader" class="form-select" required>
              <option value="">Pilih Leader</option>
              <option value="Agus S">Agus S</option>
              <option value="Isya A">Isya A </option>
              <option value="Bogar B">Bogar B</option>
              <option value="Ramdan S">Ramdan S</option>
              <option value="Sudarno">Sudarno</option>
              <option value="Romadiyanto">Romadiyanto</option>
              <option value="Doni M">Doni M</option>
              <option value="Momon A">Momon A</option>
              <option value="Ujang D">Ujang D</option>
              <option value="Daris P">Daris P</option>
              <option value="Ahmad H">Ahmad H</option>
              <option value="Maman K">Maman K</option>
              <option value="Wahyudi">Wahyudi</option>
              <option value="Asep M">Asep M</option>
              <option value="Bagus A">Bagus A</option>
              <option value="Eko B">Eko B</option>
              <option value="Winanda">Winanda</option>
              <option value="Kirwanto">Kirwanto</option>
            </select>
          </div>
          <div class="col-md-3">
            <label>Date In</label>
            <input type="date" name="dateIn" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label>Status</label>
            <select name="status" class="form-select">
              <option value="">Pilih Status</option>
              <option value="Aktif">Aktif</option>
              <option value="Nonaktif">Nonaktif</option>
            </select>
          </div>
          <div class="col-md-3">
            <label>Function</label>
            <select name="functionrole" class="form-select">
              <option value="">Pilih Function</option>
              <option value="Operator">Operator</option>
              <option value="Mizusumashi">Mizusmashi</option>
              <option value="Inspection">Inspection</option>
              <option value="Repair">Repair</option>
            </select>
          </div>
          <div class="col-md-3">
            <label>Tipe</label>
            <select name="tipe" class="form-select" required onchange="toggleDurasi(this.value)">
              <option value="">Pilih Tipe</option>
              <option value="Tetap">Tetap</option>
              <option value="Kontrak">Kontrak</option>
            </select>
          </div>
          <div class="col-md-3" id="durasiField" style="display:none">
            <label>Durasi (bulan)</label>
            <select name="durasi" class="form-select">
              <option value="">Pilih Durasi</option>
              <option value="12">12</option>
              <option value="24">24</option>
              <option value="36">36</option>
              <option value="48">48</option>
              <option value="60">60</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
  $('#employeeTable').DataTable();

  $('#editModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget);
    var data = button.data('emp');
    var modal = $(this);
    for (let key in data) {
      modal.find('[name="'+key+'"]').val(data[key]);
    }
  });
});

document.addEventListener('DOMContentLoaded', function () {
  const formModal = new bootstrap.Modal(document.getElementById('formModal'));
  const form = document.querySelector('#formModal form');

  document.querySelector('#btnTambah')?.addEventListener('click', () => {
    form.reset();
    form.npk.readOnly = false;
    document.getElementById('durasiField').style.display = 'none';
    const editInput = form.querySelector('[name=edit]');
    if (editInput) editInput.remove();
    formModal.show();
  });

  document.querySelectorAll('button[data-emp]').forEach(button => {
    button.addEventListener('click', () => {
      const emp = JSON.parse(button.getAttribute('data-emp'));
      form.npk.value = emp.NPK;
      form.npk.readOnly = true;
      form.name.value = emp.Nama;
      form.gender.value = emp.Gender;
      form.section.value = emp.Section;
      form.line.value = emp.Line;
      form.leader.value = emp.Leader;
      form.dateIn.value = emp.DateIn;
      form.status.value = emp.Status;
      form.functionRole.value = emp.functionRole;
      form.tipe.value = emp.Tipe;
      form.durasi.value = emp.Durasi || '';

      document.getElementById('durasiField').style.display = (emp.Tipe === 'Kontrak') ? 'block' : 'none';

      if (!form.querySelector('[name=edit]')) {
        const editInput = document.createElement('input');
        editInput.type = 'hidden';
        editInput.name = 'edit';
        editInput.value = '1';
        form.appendChild(editInput);
      }
      formModal.show();
    });
  });
});

function toggleDurasi(tipe) {
  document.getElementById('durasiField').style.display = (tipe === 'Kontrak') ? 'block' : 'none';
}
</script>
</body>
</html>
