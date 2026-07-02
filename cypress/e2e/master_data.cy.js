describe('Master Data CRUD Scenarios', () => {
  beforeEach(() => {
    cy.resetDb();
    cy.login('admin', 'admin');
  });

  it('should create, edit, and bulk delete Branches', () => {
    cy.visit('/branches.php');

    // 1. Create Branch
    cy.get('button').contains('Tambah Cabang').click();
    cy.get('#addModal').should('be.visible');
    cy.get('#addModal input[name="name"]').type('Bandung');
    cy.get('#addModal button[type="submit"]').click();
    
    // Verify toast success & listing
    cy.get('.swal2-container').should('contain', 'Cabang berhasil ditambahkan!');
    cy.get('tbody').should('contain', 'Bandung');

    // 2. Edit Branch
    cy.get('tbody tr').contains('Bandung').parents('tr').find('button').contains('Edit').click();
    cy.get('#editModal').should('be.visible');
    cy.get('#edit_branch_name').clear().type('Bandung Super');
    cy.get('#editModal button[type="submit"]').click();

    // Verify toast success & listing
    cy.get('.swal2-container').should('contain', 'Cabang berhasil diperbarui!');
    cy.get('tbody').should('contain', 'Bandung Super');

    // 3. Test Bulk Delete (Ctrl+A or multi check deletion check)
    // Select the row
    cy.get('tbody tr').contains('Bandung Super').parents('tr').click();
    cy.get('#btnBulkDelete').should('be.visible').click();
    cy.get('.swal2-confirm').click();

    // Verify deletion success
    cy.get('.swal2-container').should('contain', 'Cabang berhasil dihapus');
    cy.get('tbody').should('not.contain', 'Bandung Super');
  });

  it('should create, edit, and delete Divisions', () => {
    cy.visit('/divisions.php');

    // 1. Create Division
    cy.get('button').contains('Tambah Divisi').click();
    cy.get('#addModal input[name="name"]').type('Marketing');
    cy.get('#addModal button[type="submit"]').click();

    cy.get('.swal2-container').should('contain', 'Divisi berhasil ditambahkan!');
    cy.get('tbody').should('contain', 'Marketing');

    // 2. Edit Division
    cy.get('tbody tr').contains('Marketing').parents('tr').find('button').contains('Edit').click();
    cy.get('#edit_division_name').clear().type('Marketing Digital');
    cy.get('#editModal button[type="submit"]').click();

    cy.get('.swal2-container').should('contain', 'Divisi berhasil diperbarui!');
    cy.get('tbody').should('contain', 'Marketing Digital');
  });

  it('should create, edit, and delete Rooms', () => {
    cy.visit('/rooms.php');

    // 1. Create Room
    cy.get('button').contains('Tambah Ruangan').click();
    cy.get('#addModal input[name="name"]').type('Ruang Melati');
    cy.get('#addModal button[type="submit"]').click();

    cy.get('.swal2-container').should('contain', 'Ruangan berhasil ditambahkan!');
    cy.get('tbody').should('contain', 'Ruang Melati');

    // 2. Edit Room
    cy.get('tbody tr').contains('Ruang Melati').parents('tr').find('button').contains('Edit').click();
    cy.get('#edit_room_name').clear().type('Ruang Anggrek');
    cy.get('#editModal button[type="submit"]').click();

    cy.get('.swal2-container').should('contain', 'Ruangan berhasil diperbarui!');
    cy.get('tbody').should('contain', 'Ruang Anggrek');
  });

  it('should manage Employees and handle validations', () => {
    cy.visit('/employees.php');

    // 1. Create Employee
    cy.get('button').contains('Tambah Karyawan').click();
    cy.get('#addModal input[name="name"]').type('Andi Wijaya');
    cy.get('#addModal input[name="username"]').type('andiwijaya');
    cy.get('#addModal input[name="password"]').type('andi1234');
    cy.get('#addModal select[name="division"]').select('IT', { force: true });
    cy.get('#addModal button[type="submit"]').click();

    cy.get('.swal2-container').should('contain', 'Karyawan berhasil ditambahkan!');
    cy.get('tbody').should('contain', 'Andi Wijaya');

    // 2. Edit Employee
    cy.get('tbody tr').contains('Andi Wijaya').parents('tr').find('button').contains('Edit').click();
    cy.get('#edit_emp_name').clear().type('Andi Wijaya Sukses');
    cy.get('#editModal button[type="submit"]').click();

    cy.get('.swal2-container').should('contain', 'Data karyawan berhasil diperbarui!');
    cy.get('tbody').should('contain', 'Andi Wijaya Sukses');
  });

  it('should manage Templates and check Select2 dropdown exclusions', () => {
    cy.visit('/templates.php');

    // 1. Open template modal
    cy.get('button').contains('Tambah Template').click();

    // 2. Input template name
    cy.get('#form_name').type('Daily Sync Scrum');

    // Log options for debugging
    cy.get('#form_pic_id').then(($select) => {
      const options = Array.from($select.find('option')).map(o => `${o.value}: ${o.text}`);
      cy.log('Available PIC options:', JSON.stringify(options));
    });

    // PIC: Rizqi (User ID: 3)
    cy.get('#form_pic_id').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Rizqi').click();

    // Participants: Since Rizqi is PIC, he should be disabled in participants
    cy.get('#form_participants').find('option[value="3"]').should('be.disabled');
    
    // Choose Firzi (ID 2) and Fathi (ID 4) instead
    cy.get('#form_participants').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Firzi').click();

    cy.get('#form_participants').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Fathi').click();

    // Submit Template Form
    cy.get('#btnSubmit').click();

    // Verify template created
    cy.get('.swal2-container').should('contain', 'Template berhasil ditambahkan!');
    cy.get('tbody').should('contain', 'Daily Sync Scrum');
  });

  it('should prevent creating a Branch with special characters and show validation error', () => {
    cy.visit('/branches.php');
    cy.get('button').contains('Tambah Cabang').click();
    cy.get('#addModal input[name="name"]').type('Bandung@#$');
    cy.get('#addModal button[type="submit"]').click();
    cy.get('.swal2-container').should('contain', 'Input tidak boleh mengandung simbol khusus.');
  });

  it('should prevent creating a Division with special characters and show validation error', () => {
    cy.visit('/divisions.php');
    cy.get('button').contains('Tambah Divisi').click();
    cy.get('#addModal input[name="name"]').type('Marketing!%');
    cy.get('#addModal button[type="submit"]').click();
    cy.get('.swal2-container').should('contain', 'Input tidak boleh mengandung simbol khusus.');
  });

  it('should prevent creating a Room with special characters and show validation error', () => {
    cy.visit('/rooms.php');
    cy.get('button').contains('Tambah Ruangan').click();
    cy.get('#addModal input[name="name"]').type('Room^&');
    cy.get('#addModal button[type="submit"]').click();
    cy.get('.swal2-container').should('contain', 'Input tidak boleh mengandung simbol khusus.');
  });
});
