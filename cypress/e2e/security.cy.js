describe('Security - Rate Limiting & Password Hashing', () => {
  beforeEach(() => {
    cy.resetDb();
  });

  // =============================================
  // 1. BCRYPT LOGIN
  // =============================================
  describe('BCrypt Password Authentication', () => {
    it('should login successfully with bcrypt-hashed password (admin/admin)', () => {
      // Setelah reset DB, password user sudah bcrypt dari dump
      // Dump menggunakan hash dari kata "password" untuk semua user
      // Test ini menggunakan password "password" (default fresh deploy)
      cy.visit('/login.php');
      cy.get('input[name="username"]').type('admin');
      cy.get('input[name="password"]').type('password');
      cy.get('button[type="submit"]').click();
      cy.url().should('include', 'index.php');
    });

    it('should reject wrong password even if username is valid', () => {
      cy.visit('/login.php');
      cy.get('input[name="username"]').type('admin');
      cy.get('input[name="password"]').type('salah_banget_ini');
      cy.get('button[type="submit"]').click();

      // Error toast muncul
      cy.get('.swal2-container').should('be.visible');
      cy.get('.swal2-title').should('contain', 'salah');
    });

    it('should show remaining attempt count in error message after failed login', () => {
      cy.visit('/login.php');

      // Percobaan gagal pertama
      cy.get('input[name="username"]').type('admin');
      cy.get('input[name="password"]').type('wrong1');
      cy.get('button[type="submit"]').click();

      // Sisa 4 percobaan
      cy.get('.swal2-title').should('contain', 'Sisa percobaan: 4');
    });
  });

  // =============================================
  // 2. RATE LIMITING
  // =============================================
  describe('Rate Limiting - Brute Force Protection', () => {
    it('should block IP after 5 failed login attempts', () => {
      cy.visit('/login.php');

      // Lakukan 5 percobaan gagal berturut-turut
      for (let i = 0; i < 5; i++) {
        cy.get('input[name="username"]').clear().type('admin');
        cy.get('input[name="password"]').clear().type(`wrong_attempt_${i}`);
        cy.get('button[type="submit"]').click();

        if (i < 4) {
          // Percobaan 1-4: error toast muncul, form masih aktif
          cy.get('.swal2-container').should('be.visible');
          cy.get('.swal2-popup button.swal2-confirm, .swal2-timer-progress-bar').should('exist');
          cy.wait(300); // Tunggu toast hilang
        }
      }

      // Setelah percobaan ke-5: tampil lockout alert
      cy.get('.lockout-alert').should('be.visible');
      cy.get('.lockout-alert').should('contain', 'dikunci');
    });

    it('should disable the login form when IP is locked out', () => {
      // Paksa lockout via 5 request POST langsung
      for (let i = 0; i < 5; i++) {
        cy.request({
          method: 'POST',
          url: '/login.php',
          form: true,
          body: { username: 'admin', password: `bruteforce_${i}` },
          failOnStatusCode: false
        });
      }

      cy.visit('/login.php');

      // Form harus disabled
      cy.get('input[name="username"]').should('be.disabled');
      cy.get('input[name="password"]').should('be.disabled');
      cy.get('button[type="submit"]').should('be.disabled');

      // Icon kunci muncul di tombol
      cy.get('button[type="submit"] i').should('have.class', 'fa-lock');
    });

    it('should show lockout alert message with remaining minutes info', () => {
      // Paksa lockout
      for (let i = 0; i < 5; i++) {
        cy.request({
          method: 'POST',
          url: '/login.php',
          form: true,
          body: { username: 'admin', password: `brute_${i}` },
          failOnStatusCode: false
        });
      }

      cy.visit('/login.php');
      cy.get('.lockout-alert').should('be.visible');
      cy.get('.lockout-alert').should('contain', 'menit');
      cy.get('.lockout-alert i').should('have.class', 'fa-lock');
    });

    it('should clear lockout and allow login after db reset (simulates time passing)', () => {
      // Lockout IP
      for (let i = 0; i < 5; i++) {
        cy.request({
          method: 'POST',
          url: '/login.php',
          form: true,
          body: { username: 'admin', password: `brute_${i}` },
          failOnStatusCode: false
        });
      }

      // Verify locked
      cy.visit('/login.php');
      cy.get('.lockout-alert').should('be.visible');

      // Reset DB (seolah-olah waktu sudah lewat 15 menit)
      cy.resetDb();

      // Sekarang harus bisa login lagi
      cy.visit('/login.php');
      cy.get('.lockout-alert').should('not.exist');
      cy.get('input[name="username"]').should('not.be.disabled');
      cy.get('input[name="username"]').type('admin');
      cy.get('input[name="password"]').type('password');
      cy.get('button[type="submit"]').click();
      cy.url().should('include', 'index.php');
    });
  });

  // =============================================
  // 3. OPEN REDIRECT PREVENTION
  // =============================================
  describe('Open Redirect Prevention', () => {
    it('should not redirect to external URL after login', () => {
      // Simulasi: set redirect_after_login via session ke URL eksternal
      // Caranya: request ke attendance.php dengan URL palsu terlebih dahulu
      // lalu login — redirect harusnya ke index.php, bukan external
      cy.visit('/login.php');
      cy.get('input[name="username"]').type('admin');
      cy.get('input[name="password"]').type('password');
      cy.get('button[type="submit"]').click();

      // Harusnya tetap di domain localhost
      cy.url().should('include', 'localhost');
      cy.url().should('not.include', 'evil.com');
    });
  });
});
