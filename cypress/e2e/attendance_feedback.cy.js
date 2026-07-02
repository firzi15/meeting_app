describe('Attendance and Feedback Evaluation Flow', () => {
  beforeEach(() => {
    cy.resetDb();
  });

  it('should redirect unauthenticated users to login and then return to the attendance page', () => {
    cy.clearCookies();
    cy.clearLocalStorage();
    // 1. Visit attendance link directly while logged out
    cy.visit('/attendance.php?token=token-tepat-waktu');

    // 2. Verify redirect to login
    cy.url().should('include', 'login.php');

    // 3. Login using valid employee credentials
    cy.get('input[name="username"]').type('finance');
    cy.get('input[name="password"]').type('password123');
    cy.get('button[type="submit"]').click();

    // 4. Verify redirected back to the correct attendance page
    cy.url().should('include', 'attendance.php?token=token-tepat-waktu');
    cy.get('h1').should('contain', 'Presensi Meeting');
    cy.get('.meeting-meta').should('contain', 'Meeting Tepat Waktu');
  });

  it('should allow on-time check-in and late check-in with reasons', () => {
    // ---- Part A: On-Time Check-In ----
    cy.login('finance', 'password123');
    cy.visit('/attendance.php?token=token-tepat-waktu');

    // Verify late reason textarea is NOT visible because we are on time (started 5 mins ago, tolerance is 15 mins)
    cy.get('textarea[name="late_reason"]').should('not.exist');

    // Select attendance radio button & submit
    cy.get('input[name="absen"][value="1"]').check();
    cy.get('form').submit();

    // Verify successful registration banner
    cy.get('.status-badge').should('contain', 'Presensi Anda telah berhasil direkam.');

    // Logout employee
    cy.visit('/index.php'); // goes to my_schedule.php for normal employee
    cy.get('#topbarAvatar').click();
    cy.get('.dropdown-item.logout').click();
    cy.get('.swal2-confirm').click();

    // ---- Part B: Late Check-In ----
    cy.login('it', 'password123');
    cy.visit('/attendance.php?token=token-terlambat');

    // Since the meeting started 25 minutes ago, it exceeds the 15-minute tolerance.
    // Verify late reason textarea is visible and required
    cy.get('textarea[name="late_reason"]').should('be.visible').and('have.attr', 'required');

    // Select attendance radio button, fill reason, and submit
    cy.get('input[name="absen"][value="1"]').check();
    cy.get('textarea[name="late_reason"]').type('Terjebak macet parah di tol');
    cy.get('form').submit();

    // Verify success banner
    cy.get('.status-badge').should('contain', 'Presensi Anda telah berhasil direkam.');
  });

  it('should toggle Owner Privilege and record automatic attendance in reports', () => {
    // 1. Log in as Admin to grant Owner Privilege to employee 'finance' (Firzi)
    cy.login('admin', 'admin');
    cy.visit('/grant_access.php');

    // Toggle Owner Privilege switch for 'Firzi'
    // Find the row for Firzi, then within the Owner Privilege cell (3rd td),
    // find the form that has the is_owner feature hidden field and check its checkbox.
    cy.contains('tbody tr', 'Firzi')
      .find('form:has(input[name="feature"][value="is_owner"]) input[type="checkbox"]')
      .check({ force: true });

    // 2. Go to the meeting report page and select the meeting 'Meeting Tepat Waktu'
    cy.visit('/report.php');
    
    // Find the meeting row, click 'Rekap' or detail link
    
    // Admin can click 'Akses' or row to see recap/detail? Let's check report detail link
    // In report.php listing, if meeting is active, it shows 'Akses'. 
    // Wait, we can navigate directly to details of the report if we know the ID, or click on the row or find the recap link.
    // Let's click on the row to open details or click 'Akses' or wait for it to be finished.
    // Let's first end the meeting to see the full attendance report.
    cy.get('tbody tr').contains('Meeting Tepat Waktu').parents('tr').find('button').contains('Akhiri').click();
    cy.get('.swal2-confirm').click(); // Confirm end meeting
    cy.get('.swal2-container').should('contain', 'Meeting telah berhasil diselesaikan');

    // After ended, the row action will change to 'Rekap'. Click it.
    cy.get('tbody tr').contains('Meeting Tepat Waktu').parents('tr').find('a').contains('Rekap').click();

    // Verify that 'Firzi' is automatically marked as 'Hadir (Tepat Waktu)' due to Owner Privilege
    // even though he never performed physical check-in for this meeting.
    cy.get('table').should('contain', 'Firzi');
    cy.contains('tr', 'Firzi').should('contain', 'Hadir (Tepat Waktu)');
  });

  it('should open feedback portal when meeting ends and successfully submit feedback ratings', () => {
    // 1. Employee finance checks in for 'Meeting Tepat Waktu'
    cy.login('finance', 'password123');
    cy.visit('/attendance.php?token=token-tepat-waktu');
    cy.get('input[name="absen"][value="1"]').check();
    cy.get('form').submit();
    
    // Logout
    cy.visit('/my_schedule.php');
    cy.get('#topbarAvatar').click();
    cy.get('.dropdown-item.logout').click();
    cy.get('.swal2-confirm').click();

    // 2. Admin ends the meeting 'Meeting Tepat Waktu'
    cy.login('admin', 'admin');
    cy.visit('/report.php');
    cy.get('tbody tr').contains('Meeting Tepat Waktu').parents('tr').find('button').contains('Akhiri').click();
    cy.get('.swal2-confirm').click();
    
    // Logout admin
    cy.visit('/index.php');
    cy.get('#topbarAvatar').click();
    cy.get('.dropdown-item.logout').click();
    cy.get('.swal2-confirm').click();

    // 3. Employee logs back in, visits attendance page, and fills feedback ratings
    cy.login('finance', 'password123');
    cy.visit('/attendance.php?token=token-tepat-waktu');

    // Page should display feedback form
    cy.get('h1').should('contain', 'Presensi Meeting');
    cy.get('form').should('contain', 'Rating Materi Meeting');

    // Fill rating star radios (using force because styled labels might overlay them)
    cy.get('input[name="q1_rating"][value="5"]').check({ force: true });
    cy.get('input[name="q2_rating"][value="4"]').check({ force: true });
    cy.get('input[name="q3_rating"][value="5"]').check({ force: true });
    cy.get('input[name="q4_rating"][value="4"]').check({ force: true });

    // Fill comment text
    cy.get('textarea[name="feedback_text"]').type('Pembahasan materi sangat jelas dan ruangan nyaman.');

    // Submit feedback
    cy.get('form').submit();

    // Verify finished state
    cy.get('.status-badge').should('contain', 'Seluruh rangkaian meeting telah selesai.');
  });

  it('should allow submitting feedback through the standalone feedback.php page', () => {
    // 1. Employee finance checks in for 'Meeting Tepat Waktu'
    cy.login('finance', 'password123');
    cy.visit('/attendance.php?token=token-tepat-waktu');
    cy.get('input[name="absen"][value="1"]').check();
    cy.get('form').submit();
    
    // Logout
    cy.visit('/my_schedule.php');
    cy.get('#topbarAvatar').click();
    cy.get('.dropdown-item.logout').click();
    cy.get('.swal2-confirm').click();

    // 2. Admin ends the meeting
    cy.login('admin', 'admin');
    cy.visit('/report.php');
    cy.get('tbody tr').contains('Meeting Tepat Waktu').parents('tr').find('button').contains('Akhiri').click();
    cy.get('.swal2-confirm').click();

    // 3. Employee goes to the standalone feedback.php?id=2 directly (Meeting Tepat Waktu is ID 2)
    cy.login('finance', 'password123');
    cy.visit('/feedback.php?id=2');
    
    cy.get('.feedback-card').should('contain', 'Meeting Tepat Waktu');
    
    cy.get('input[name="q1_rating"][value="5"]').check({ force: true });
    cy.get('input[name="q2_rating"][value="5"]').check({ force: true });
    cy.get('input[name="q3_rating"][value="5"]').check({ force: true });
    cy.get('input[name="q4_rating"][value="5"]').check({ force: true });
    cy.get('textarea[name="feedback_text"]').type('Sangat bagus!');
    cy.get('form').submit();

    // Verify redirect to thanks.php
    cy.url().should('include', 'thanks.php?type=feedback');
    cy.get('h1').should('contain', 'Feedback Meeting');
    cy.get('p').should('contain', 'Jawaban Anda telah direkam.');
  });

  it('should display history and filter meetings by room in my_schedule.php', () => {
    cy.login('finance', 'password123');
    cy.visit('/my_schedule.php');
    
    cy.get('tbody').should('contain', 'Meeting Tepat Waktu');
    
    // Filter by another room (which has no meetings scheduled in seeds)
    cy.get('#roomFilter').select('Ruang Meeting Kecil', { force: true });
    
    // Wait for the page to reload and roomFilter to reflect the selected value
    cy.get('#roomFilter').should('have.value', 'Ruang Meeting Kecil');
    
    // Verify the list is successfully filtered
    cy.get('tbody').should('not.contain', 'Meeting Tepat Waktu');
  });

  it('should allow non-invited users to scan/access attendance and check in as Dadakan', () => {
    // 1. Log in as user 'hr' (Fathi) who is NOT invited to 'Meeting Tepat Waktu'
    cy.login('hr', 'password123');
    cy.visit('/attendance.php?token=token-tepat-waktu');

    // 2. Verify we see check-in form without access denied message
    cy.get('h1').should('contain', 'Presensi Meeting');
    cy.get('.meeting-meta').should('contain', 'Meeting Tepat Waktu');

    // 3. Perform check-in (requires no late reason because they are check in as Dadakan)
    cy.get('input[name="absen"][value="1"]').check();
    cy.get('form').submit();

    // 4. Verify successful registration banner
    cy.get('.status-badge').should('contain', 'Presensi Anda telah berhasil direkam.');

    // 5. Verify they are registered as 'Dadakan' in reports
    cy.login('admin', 'admin');
    cy.visit('/report.php');

    // End meeting first to view full report
    cy.get('tbody tr').contains('Meeting Tepat Waktu').parents('tr').find('button').contains('Akhiri').click();
    cy.get('.swal2-confirm').click();
    cy.get('.swal2-container').should('contain', 'Meeting telah berhasil diselesaikan');

    // Click 'Rekap'
    cy.get('tbody tr').contains('Meeting Tepat Waktu').parents('tr').find('a').contains('Rekap').click();

    // Verify user 'Fathi' is in the list with status 'Dadakan' and reason 'Peserta Dadakan'
    cy.get('table').should('contain', 'Fathi');
    cy.contains('tr', 'Fathi').should('contain', 'Dadakan');
  });
});
