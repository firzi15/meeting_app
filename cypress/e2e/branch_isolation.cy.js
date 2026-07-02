describe('Row-Level Multi-Branch Isolation Scenarios', () => {
  beforeEach(() => {
    cy.resetDb();
  });

  it('should switch branches as Admin and display branch-isolated master data', () => {
    cy.login('admin', 'admin');
    cy.visit('/index.php');

    // 1. Initially, default branch is Jakarta (ID: 1).
    cy.get('.profile-container').first().should('contain', 'Jakarta');
    
    // Go to rooms page and verify Jakarta rooms exist
    cy.visit('/rooms.php');
    cy.get('tbody').should('contain', 'Ruang Meeting Besar');

    // 2. Switch branch to Surabaya (ID: 2) in topbar
    cy.get('.profile-info').contains('Jakarta').click();
    cy.get('#branchDropdown').should('be.visible');
    cy.get('#branchDropdown').contains('Surabaya').click();

    // Verify header reflects branch change
    cy.get('.profile-info').should('contain', 'Surabaya');

    // Go to rooms page and verify Jakarta rooms are NOT visible under Surabaya branch
    cy.visit('/rooms.php');
    cy.get('tbody').should('not.contain', 'Ruang Meeting Besar');
    cy.get('tbody').should('contain', 'Online');
  });

  it('should isolate normal employees to their branch automatically without branch switcher', () => {
    // 1. Login as 'admin' (Jakarta admin, branch_id: 1)
    cy.login('admin', 'admin');
    
    // Go to dashboard index.php
    cy.visit('/index.php');
    
    // Open schedule meeting modal
    cy.get('.action-card').first().click();
    
    // Check that Room dropdown contains Jakarta rooms
    cy.get('#meetingRoomSelect').should('contain', 'Ruang Meeting Besar');

    // 2. Login as 'asri' (Surabaya employee, branch_id: 2)
    cy.login('asri', 'asri');
    cy.visit('/index.php');
    
    // They are redirected to index.php because they have can_dashboard = true
    cy.url().should('include', 'index.php');
    
    // Open schedule meeting modal
    cy.get('.action-card').first().click();
    
    // Check that Room dropdown DOES NOT contain Jakarta rooms (Surabaya currently has no rooms in seed)
    cy.get('#meetingRoomSelect').should('not.contain', 'Ruang Meeting Besar');
  });
});
