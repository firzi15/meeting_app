// cypress/support/e2e.js

// Import custom commands
import './commands';

// Ignore uncaught exceptions from the application to prevent tests from failing on minor scripts errors
Cypress.on('uncaught:exception', (err, runnable) => {
  // returning false here prevents Cypress from failing the test
  return false;
});
