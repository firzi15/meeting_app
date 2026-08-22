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

  it('should manage Employee Groups (Master Group)', () => {
    cy.visit('/groups.php');

    // 1. Create Group
    cy.get('button').contains('Tambah Group').click();
    cy.get('#addModal').should('be.visible');
    cy.get('#addModal input[name="name"]').type('Supervisor');
    cy.get('#addModal textarea[name="description"]').type('Pengawas operasional lapangan');
    cy.get('#addModal button[type="submit"]').click();

    // Verify toast & listing
    cy.get('.swal2-container').should('contain', 'Grup karyawan berhasil ditambahkan!');
    cy.get('tbody').should('contain', 'Supervisor');

    // 2. Edit Group
    cy.get('tbody tr').contains('Supervisor').parents('tr').find('button').contains('Edit').click();
    cy.get('#editModal').should('be.visible');
    cy.get('#edit_group_name').clear().type('Supervisor Senior');
    cy.get('#editModal button[type="submit"]').click();

    // Verify toast & listing
    cy.get('.swal2-container').should('contain', 'Grup karyawan berhasil diperbarui!');
    cy.get('tbody').should('contain', 'Supervisor Senior');

    // 3. Delete Group
    cy.get('tbody tr').contains('Supervisor Senior').parents('tr').find('button').contains('Hapus').click();
    cy.get('.swal2-confirm').click();

    // Verify deletion
    cy.get('.swal2-container').should('contain', 'Grup karyawan berhasil dihapus!');
    cy.get('tbody').should('not.contain', 'Supervisor Senior');
  });

  it('should manage Templates and verify dynamic PIC from selected participants', () => {
    cy.visit('/templates.php');

    // 1. Open template modal
    cy.get('button').contains('Tambah Template').click();

    // 2. Input template name
    cy.get('#form_name').type('Daily Sync Scrum');

    // 3. Verify PIC is initially disabled before participants are selected
    cy.get('#form_pic_id').should('be.disabled');

    // 4. Select Participants first
    cy.get('#form_participants').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Firzi').click();

    cy.get('#form_participants').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Fathi').click();

    // 5. PIC should now be enabled and contain selected participants
    cy.get('#form_pic_id').should('not.be.disabled');
    cy.get('#form_pic_id').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Firzi').click();

    // 6. Submit Template Form
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
