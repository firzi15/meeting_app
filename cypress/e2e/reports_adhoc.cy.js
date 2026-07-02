describe('Reports and Ad-hoc Invitation Scenarios', () => {
  beforeEach(() => {
    cy.resetDb();
    cy.login('admin', 'admin');
  });

  it('should end an active meeting, view its report recap, invite an ad-hoc employee, and verify Excel export availability', () => {
    cy.visit('/report.php');

    // 2. End the meeting so we can view the Rekap
    cy.get('tbody tr').contains('Meeting Tepat Waktu').parents('tr').find('button').contains('Akhiri').click();
    cy.get('.swal2-confirm').click();
    
    // Verify toast success
    cy.get('.swal2-container').should('contain', 'Meeting telah berhasil diselesaikan');

    // 3. Click 'Rekap' link
    cy.get('tbody tr').contains('Meeting Tepat Waktu').parents('tr').find('a').contains('Rekap').click();

    // Verify detail page elements
    cy.url().should('include', 'report.php?id=');
    cy.get('.page-title').should('contain', 'Laporan Absen: Meeting Tepat Waktu');

    // 4. Invite an ad-hoc employee (e.g. Asri, who is in division General Affairs)
    cy.select2('#user_invite_select', 'Asri');
    cy.get('button').contains('Tambah').click();

    // Verify toast success
    cy.get('.swal2-container').should('contain', 'Peserta dadakan telah ditambahkan');

    // Verify Asri appears in the attendance table with Dadakan status
    cy.get('table').should('contain', 'Asri');
    cy.contains('tr', 'Asri').should('contain', 'Hadir (Dadakan)');

    // 5. Test Excel export interface options and verify file download response
    cy.get('#isForHRDetail').should('not.be.checked');
    cy.get('#isForHRDetail').check();
    cy.get('#exportDetailBtn').should('have.attr', 'href').then((href) => {
      cy.request(href).then((response) => {
        expect(response.status).to.eq(200);
        expect(response.headers['content-type']).to.include('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      });
    });
  });
});
