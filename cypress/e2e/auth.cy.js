describe('Authentication Scenarios', () => {
  beforeEach(() => {
    // Reset database to initial dump state
    cy.resetDb();
  });

  it('should display error message on invalid credentials', () => {
    cy.visit('/login.php');
    cy.get('input[name="username"]').type('nonexistent_user');
    cy.get('input[name="password"]').type('wrong_password');
    cy.get('button[type="submit"]').click();

    cy.get('.swal2-container').should('be.visible');
    cy.get('.swal2-title').should('contain', 'salah');
  });

  it('should successfully login as Super Admin and show all management menu options', () => {
    // Password default dari dump adalah 'password' (bcrypt)
    cy.login('admin', 'password');
    
    cy.url().should('include', 'index.php');
    cy.get('.page-title').should('contain', 'Meetings');
    
    cy.get('.sidebar-nav').within(() => {
      cy.get('.nav-section').contains('Data Master').should('exist');
      cy.get('a[href="branches.php"]').should('exist');
      cy.get('a[href="rooms.php"]').should('exist');
      cy.get('a[href="employees.php"]').should('exist');
      cy.get('a[href="divisions.php"]').should('exist');
      cy.get('a[href="templates.php"]').should('exist');
    });
  });

  it('should successfully login as a normal employee, redirect to my_schedule.php, and restrict menu access', () => {
    cy.login('finance', 'password');
    
    cy.url().should('include', 'my_schedule.php');
    cy.get('.page-title').should('contain', 'Jadwal Absensi Anda');

    cy.get('.sidebar-nav').within(() => {
      cy.get('.nav-section').contains('Data Master').should('not.exist');
      cy.get('a[href="branches.php"]').should('not.exist');
      cy.get('a[href="rooms.php"]').should('not.exist');
      cy.get('a[href="employees.php"]').should('not.exist');
      cy.get('a[href="divisions.php"]').should('not.exist');
      cy.get('a[href="templates.php"]').should('not.exist');
    });
  });

  it('should logout successfully when clicked and confirmed', () => {
    cy.login('admin', 'password');
    cy.visit('/index.php');
    
    cy.get('#topbarAvatar').click();
    cy.get('.dropdown-item.logout').click();
    cy.get('.swal2-confirm').click();

    cy.url().should('include', 'login.php');

    cy.visit('/index.php');
    cy.url().should('include', 'login.php');
  });

  it('should show icon user (no photo) in topbar avatar since upload is disabled', () => {
    cy.login('admin', 'password');
    cy.visit('/index.php');

    // Avatar harus berisi icon user, bukan <img>
    cy.get('#topbarAvatar').within(() => {
      cy.get('i.fa-user').should('exist');
      cy.get('img').should('not.exist');
    });

    // Dropdown tidak boleh ada menu "Ubah Foto"
    cy.get('#topbarAvatar').click();
    cy.get('#profileDropdown').should('be.visible');
    cy.get('#profileDropdown').should('not.contain', 'Ubah Foto');
  });
});
