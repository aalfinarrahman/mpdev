$(document).ready(function () {
  if (!$.fn.DataTable.isDataTable('#employeeTable')) {
    $('#employeeTable').DataTable({
      dom: 'Bfrtip',
      buttons: [
        {
          extend: 'csvHtml5',
          text: 'Export CSV',
          className: 'btn btn-success mb-3'
        }
      ]
    });
  }

  $('#editModal').on('show.bs.modal', function (event) {
    let button = $(event.relatedTarget);
    let data = button.data('emp');
    let modal = $(this);
    for (let key in data) {
      modal.find('[name="'+key+'"]').val(data[key]);
    }
    if (data['Tipe'] === 'Kontrak') {
      modal.find('#durasiField').show();
    } else {
      modal.find('#durasiField').hide();
    }
  });
});