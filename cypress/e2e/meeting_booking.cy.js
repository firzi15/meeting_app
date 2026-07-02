describe('Meeting Booking Scenarios & Validations', () => {
  beforeEach(() => {
    cy.resetDb();
    cy.login('admin', 'admin');
    cy.visit('/index.php');
  });

  it('should successfully book a meeting with all facilities, options, and check in table (exactly 1 hour)', () => {
    // 1. Open Modal
    cy.get('.action-card').first().click();
    cy.get('#scheduleModal').should('have.class', 'active');

    // 2. Fill Title & Room using Select2 UI click
    cy.get('#meetingTitle').type('Rapat Umum Pemegang Saham');
    
    cy.get('#meetingRoomSelect').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Ruang Meeting Besar').click();
    
    cy.get('#scheduleModal input[name="late_tolerance"]').clear().type('20');

    // 3. Set Date to tomorrow dynamically (local timezone safe)
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const year = tomorrow.getFullYear();
    const month = String(tomorrow.getMonth() + 1).padStart(2, '0');
    const day = String(tomorrow.getDate()).padStart(2, '0');
    const tomorrowStr = `${year}-${month}-${day}`;
    
    cy.get('#scheduleModal input[name="date"]').type(tomorrowStr);

    // 4. Set Time (Exactly 1 hour)
    cy.get('#scheduleModal input[name="time"]').type('13:00');
    cy.get('#scheduleModal input[name="end_time"]').type('14:00');

    // 5. Select PIC & Participants using Select2 option click
    cy.get('#picSelect').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Rizqi').click();

    // Select multiple participants (dropdown stays open for multiple select2)
    cy.get('#participantSelect').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Fathi').click();
    cy.get('.select2-results__option').contains('Firzi').click();

    // 6. Test snack, coffee details toggle, and zoom
    cy.get('#scheduleModal input[name="has_snack"]').check();
    cy.get('#coffeeOptionsContainer').should('have.css', 'display', 'none');
    
    // Check coffee -> sub-options should show
    cy.get('#scheduleModal #hasCoffeeCheckbox').check();
    cy.get('#coffeeOptionsContainer').should('have.css', 'display', 'block');
    
    // Suhu Kopi Select
    cy.get('#scheduleModal select[name="coffee_temp"]').select('dingin');
    
    // Metode Penyediaan Select
    cy.get('#scheduleModal select[name="coffee_type"]').select('beli');

    cy.get('#scheduleModal input[name="is_hybrid_zoom"]').check();

    // 7. Submit form
    cy.get('#scheduleForm').submit();

    // 8. Verify SweetAlert popup showing details & QR Code
    cy.get('.swal2-container').should('contain', 'Jadwal Berhasil Dibuat!');
    cy.get('.swal2-html-container img').should('have.attr', 'src').and('include', 'qrserver.com');
    
    // Refresh page explicitly to avoid location.reload race conditions
    cy.reload();

    // 9. Verify the scheduled meeting appears in dashboard table
    cy.get('#tableSearch').type('Rapat Umum Pemegang Saham');
    cy.get('tbody').should('contain', 'Rapat Umum Pemegang Saham');
    cy.get('tbody').should('contain', 'Ruang Meeting Besar');
  });

  it('should prevent booking meeting with duration not equal to 1 hour', () => {
    cy.get('.action-card').first().click();
    cy.get('#meetingTitle').type('Invalid Duration Meeting');
    
    cy.get('#meetingRoomSelect').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Ruang Meeting Besar').click();
    
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const year = tomorrow.getFullYear();
    const month = String(tomorrow.getMonth() + 1).padStart(2, '0');
    const day = String(tomorrow.getDate()).padStart(2, '0');
    const tomorrowStr = `${year}-${month}-${day}`;
    
    cy.get('#scheduleModal input[name="date"]').type(tomorrowStr);

    // Set duration to 1 hour 30 mins (13:00 to 14:30)
    cy.get('#scheduleModal input[name="time"]').type('13:00');
    cy.get('#scheduleModal input[name="end_time"]').type('14:30');

    cy.get('#picSelect').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Rizqi').click();

    cy.get('#scheduleForm').submit();

    // Verify error toast/alert message
    cy.get('.swal2-container').should('contain', 'Durasi meeting harus kelipatan 1 jam (misal: 1 jam, 2 jam, dst).');
  });

  it('should prevent booking overlapping meetings in the same room (Anti-Bentrok) for physical rooms', () => {
    const testDate = new Date();
    testDate.setDate(testDate.getDate() + 2); // 2 days from now
    const year = testDate.getFullYear();
    const month = String(testDate.getMonth() + 1).padStart(2, '0');
    const day = String(testDate.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}`;

    // Book 1st meeting: Room Besar, 09:00 - 11:00 (2 hours)
    cy.get('.action-card').first().click();
    cy.get('#meetingTitle').type('First Meeting');
    
    cy.get('#meetingRoomSelect').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Ruang Meeting Besar').click();
    
    cy.get('#scheduleModal input[name="date"]').type(dateStr);
    cy.get('#scheduleModal input[name="time"]').type('09:00');
    cy.get('#scheduleModal input[name="end_time"]').type('11:00');

    cy.get('#picSelect').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Rizqi').click();

    cy.get('#scheduleForm').submit();
    cy.get('.swal2-container').should('contain', 'Jadwal Berhasil Dibuat!');
    
    // Refresh page explicitly to avoid location.reload race conditions
    cy.reload();

    // Try to book 2nd meeting in the same room overlapping: Room Besar, 10:00 - 11:00 (1 hour duration)
    cy.get('.action-card').first().click();
    cy.get('#meetingTitle').type('Overlapping Meeting');
    
    cy.get('#meetingRoomSelect').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Ruang Meeting Besar').click();
    
    cy.get('#scheduleModal input[name="date"]').type(dateStr);
    cy.get('#scheduleModal input[name="time"]').type('10:00');
    cy.get('#scheduleModal input[name="end_time"]').type('11:00');

    cy.get('#picSelect').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Fathi').click();

    cy.get('#scheduleForm').submit();

    // Verify error toast or Swal message
    cy.get('.swal2-container').should('contain', 'Ruangan sudah dipesan untuk waktu tersebut.');
  });

  it('should allow overlapping bookings for the Online room and hide consumption panel', () => {
    const testDate = new Date();
    testDate.setDate(testDate.getDate() + 2); // 2 days from now
    const year = testDate.getFullYear();
    const month = String(testDate.getMonth() + 1).padStart(2, '0');
    const day = String(testDate.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}`;

    // 1. Check if selecting Online room hides the consumption panel
    cy.get('.action-card').first().click();
    cy.get('#consumptionPanel').should('have.css', 'display', 'block');
    
    cy.get('#meetingRoomSelect').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Online').click();
    
    cy.get('#consumptionPanel').should('have.css', 'display', 'none');

    // 2. Book 1st meeting in Online room: 09:00 - 11:00
    cy.get('#meetingTitle').type('Online Meeting 1');
    cy.get('#scheduleModal input[name="date"]').type(dateStr);
    cy.get('#scheduleModal input[name="time"]').type('09:00');
    cy.get('#scheduleModal input[name="end_time"]').type('11:00');

    cy.get('#picSelect').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Rizqi').click();

    cy.get('#scheduleForm').submit();
    cy.get('.swal2-container').should('contain', 'Jadwal Berhasil Dibuat!');
    
    // Refresh page explicitly to avoid location.reload race conditions
    cy.reload();

    // 3. Book 2nd meeting in Online room at same time: 10:00 - 11:00 (should succeed!)
    cy.get('.action-card').first().click();
    
    cy.get('#meetingRoomSelect').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Online').click();
    
    cy.get('#meetingTitle').type('Online Meeting 2');
    cy.get('#scheduleModal input[name="date"]').type(dateStr);
    cy.get('#scheduleModal input[name="time"]').type('10:00');
    cy.get('#scheduleModal input[name="end_time"]').type('11:00');

    cy.get('#picSelect').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Fathi').click();

    cy.get('#scheduleForm').submit();

    // Verify success SweetAlert
    cy.get('.swal2-container').should('contain', 'Jadwal Berhasil Dibuat!');
  });

  it('should auto-populate details when selecting a meeting Template', () => {
    cy.get('.action-card').first().click();

    // Select the 'Review KPI' template (ID: 1) using Select2 UI click
    cy.get('#templateSelect').next('.select2-container').click();
    cy.get('.select2-results__option').contains('Review KPI').click();

    // Wait for AJAX call to finish and Toast notification to appear
    cy.get('.swal2-container').should('contain', 'Template berhasil dimuat.');

    // Verify Title, PIC, and Participants are pre-filled
    cy.get('#meetingTitle').should('have.value', 'Review KPI Bulanan');
    
    // Select2 elements display their selected text inside selections
    cy.get('#picSelect').next('.select2-container').should('contain', 'Fathi');
    cy.get('#participantSelect').next('.select2-container').should('contain', 'Firzi');
    cy.get('#participantSelect').next('.select2-container').should('contain', 'Rizqi');
  });

  it('should display meetings and support month navigation on the FullCalendar interactive calendar page', () => {
    cy.visit('/calendar.php');
    cy.get('#calendar').should('be.visible');
    cy.get('.fc-view-harness').should('exist');
    
    // Capture the initial month-year title
    cy.get('.fc-toolbar-title').then(($title) => {
      const initialText = $title.text();
      
      // Click "Next month" button
      cy.get('.fc-next-button').click();
      
      // Verify title has changed
      cy.get('.fc-toolbar-title').should('not.have.text', initialText);
      
      // Click "Prev month" button to go back
      cy.get('.fc-prev-button').click();
      
      // Verify it returns to the original title
      cy.get('.fc-toolbar-title').should('have.text', initialText);
    });
    
    // The calendar should render meeting blocks in the monthly view
    cy.get('.fc-event').should('exist');
  });
});
