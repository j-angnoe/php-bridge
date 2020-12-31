context('Actions', () => {
    // https://on.cypress.io/interacting-with-elements
  
    it('./bin/phpbridge file.php works', () => {
        // 31890: Launches in single file mode.
        cy.visit('http://localhost:31890/');

        cy.get('h1').should('contain', 'PHP Bridge prototype')

        cy.contains('Execute').click()
        cy.get('#debug').should('contain','"sum": 3');

        cy.contains('Second execute').click()
        cy.get('#debug').should('contain','"sum": 7');
    })
  
    it('./bin/phpbridge dir +options work', () => {
        // 31891: Launches in directory mode
        cy.visit('http://localhost:31891/');

        cy.get('body').should('contain', 'Index file')

        cy.visit('http://localhost:31891/test-file.php');
        cy.contains('Execute').click()
        cy.get('#debug').should('contain','"sum": 3');

        cy.contains('Second execute').click()
        cy.get('#debug').should('contain','"sum": 7');
    })
});