// cypress/support/commands.js

// Command to reset database before test runs
Cypress.Commands.add('resetDb', () => {
  cy.request('/cypress_reset_db.php').then((response) => {
    expect(response.status).to.eq(200);
    expect(response.body.status).to.eq('success');
  });
});

// Command to perform a standard UI login
Cypress.Commands.add('login', (username, password) => {
  cy.clearCookies();
  cy.clearLocalStorage();
  cy.visit('/login.php');
  cy.get('input[name="username"]').clear().type(username);
  cy.get('input[name="password"]').clear().type(password);
  cy.get('button[type="submit"]').click();
  cy.url().should('satisfy', (url) => {
    return url.includes('index.php') || url.includes('my_schedule.php');
  });
});

// Command to interact with Select2 dropdowns (robust for both single and multi-select elements)
Cypress.Commands.add('select2', (selector, text) => {
  // Click on the select2 element container next to the target select element
  cy.get(selector).next('.select2-container').click();
  
  // Find the visible search field (if any) and type the query
  cy.get('body').then(($body) => {
    const searchField = $body.find('.select2-search__field:visible');
    if (searchField.length > 0) {
      cy.wrap(searchField).first().clear({ force: true }).type(text, { force: true });
    }
  });
  
  // Click on the matching dropdown list item
  cy.get('.select2-results__option:visible')
    .contains(text)
    .click();
});
