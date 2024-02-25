
export function login(keycloakUrl, username, password) {

    cy.visit("/")
    cy.get('#login').click()

    cy.origin(keycloakUrl, { args : { username, password }}, ({ username, password }) => {
        cy.log("fill in the login form")
        cy.get('#username').type(username, {force: true})
        cy.get('#password').type(password, {force: true})

        // submit
        cy.get('#kc-login').click()

        cy.location().then((location) => {
            if (location !== null && location.origin === keycloakUrl) {
                cy.get('#kc-login').click()
            } else {
                cy.log("not asked for access to user data")
            }
        })
    })
}


