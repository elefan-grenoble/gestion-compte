export function login(username, password) {
    cy.visit('')
    cy.get('[data-cy=login]').click()

    cy.log("fill in the login form")
    cy.get('[data-cy=username]').type(username, {force: true})
    cy.get('[data-cy=password]').type(password, {force: true})
    cy.get('button[type=submit]').click()
}


