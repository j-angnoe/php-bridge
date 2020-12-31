context('Actions', () => {
    // https://on.cypress.io/interacting-with-elements
  
    it('basic bridge anynomous class', () => {
        cy.visit('http://localhost:10291/basic-bridge/anonymous-class.php');

        cy.get('h1').should('contain', 'Anonymous class example')

        cy.contains('Execute').click()
        cy.get('#debug').should('contain','"sum": 3');

        cy.contains('Second execute').click()
        cy.get('#debug').should('contain','"sum": 7');
    });

    it('basic bridge class name', () => {
        cy.visit('http://localhost:10291/basic-bridge/class-name.php');

        cy.get('h1').should('contain', 'Class name example')

        cy.contains('Execute').click()
        cy.get('#debug').should('contain','"sum": 3');

        cy.contains('Second execute').click()
        cy.get('#debug').should('contain','"sum": 7');
    });
    it('basic bridge normal instance', () => {
        cy.visit('http://localhost:10291/basic-bridge/normal-instance.php');

        cy.get('h1').should('contain', 'Provided instance example')

        cy.contains('Execute').click()
        cy.get('#debug').should('contain','"sum": 3');

        cy.contains('Second execute').click()
        cy.get('#debug').should('contain','"sum": 7');
    });

    it('Additional parameters added to url dont mind', () => {
        cy.visit('http://localhost:10291/basic-bridge/normal-instance.php?extraParams=1');

        cy.get('h1').should('contain', 'Provided instance example')

        cy.contains('Execute').click()
        cy.get('#debug').should('contain','"sum": 3');

        cy.contains('Second execute').click()
        cy.get('#debug').should('contain','"sum": 7');
    });



      
    it('normal bridge anynomous class', () => {
        cy.visit('http://localhost:10291/bridge/anonymous-class.php');

        cy.get('h1').should('contain', 'NormalBridge: Anonymous class example')

        cy.contains('Execute').click()
        cy.get('#debug').should('contain','"sum": 3');

        cy.contains('Second execute').click()
        cy.get('#debug').should('contain','"sum": 7');
    });

    it('normal bridge class name', () => {
        cy.visit('http://localhost:10291/bridge/class-name.php');

        cy.get('h1').should('contain', 'NormalBridge: Class name example')

        cy.contains('Execute').click()
        cy.get('#debug').should('contain','"sum": 3');

        cy.contains('Second execute').click()
        cy.get('#debug').should('contain','"sum": 7');
    });
    it('normal bridge normal instance', () => {
        cy.visit('http://localhost:10291/bridge/normal-instance.php');

        cy.get('h1').should('contain', 'NormalBridge: Provided instance example')

        cy.contains('Execute').click()
        cy.get('#debug').should('contain','"sum": 3');

        cy.contains('Second execute').click()
        cy.get('#debug').should('contain','"sum": 7');
    });

    it('normal bridge: Additional parameters added to url dont mind', () => {
        cy.visit('http://localhost:10291/bridge/normal-instance.php?extraParams=1');

        cy.get('h1').should('contain', 'NormalBridge: Provided instance example')

        cy.contains('Execute').click()
        cy.get('#debug').should('contain','"sum": 3');

        cy.contains('Second execute').click()
        cy.get('#debug').should('contain','"sum": 7');
    });

    // it('VueBlocks as plugin does its job', () => {
       
    //     cy.get('#app-container').should('contain', 'Success: VueBlocks plugin worked!');

    // });
});