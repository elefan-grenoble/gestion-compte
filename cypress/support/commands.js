// Custom commands for gestion-compte E2E tests
// https://on.cypress.io/custom-commands

/**
 * Login via the standard FOSUser login form.
 * @param {string} username
 * @param {string} password
 */
Cypress.Commands.add('login', (username, password) => {
    cy.visit('')
    cy.get('[data-cy=login]').click()

    cy.log('fill in the login form')
    cy.get('[data-cy=username]').type(username, { force: true })
    cy.get('[data-cy=password]').type(password, { force: true })
    cy.get('button[type=submit]').click()
})

/**
 * Login via the Keycloak OIDC flow.
 * Uses Cypress.env('KEYCLOAK_URL') by default.
 * @param {string} username
 * @param {string} password
 */
Cypress.Commands.add('loginKeycloak', (username, password) => {
    const keycloakUrl = Cypress.env('KEYCLOAK_URL')

    cy.visit('/')
    cy.get('#login').click()

    cy.origin(keycloakUrl, { args: { username, password, keycloakUrl } }, ({ username, password, keycloakUrl }) => {
        cy.log('fill in the Keycloak login form')
        cy.get('#username').type(username, { force: true })
        cy.get('#password').type(password, { force: true })

        cy.get('#kc-login').click()

        cy.location().then((location) => {
            if (location !== null && location.origin === keycloakUrl) {
                cy.get('#kc-login').click()
            } else {
                cy.log('not asked for access to user data')
            }
        })
    })
})

// ---------------------------------------------------------------------------
// Mails
//
// The Cypress job of the CI runs a mailcatcher and starts the application with
// MAILER_DSN=smtp://127.0.0.1:1025; locally `make up` starts one. Its HTTP API
// is read from CYPRESS_MAILCATCHER_URL (default http://localhost:1080). The
// OIDC job has none: specs that read mails belong to the other jobs.
// ---------------------------------------------------------------------------
const mailcatcherUrl = () => Cypress.env('MAILCATCHER_URL') || 'http://localhost:1080'

/** Empties the mailcatcher, to start a spec from a known mailbox. */
Cypress.Commands.add('mailClear', () => {
    cy.request('DELETE', `${mailcatcherUrl()}/messages`)
})

/**
 * Yields the HTML body of the last mail sent to `recipient`; fails when none
 * was received within 10 seconds (the application sends synchronously, the
 * retry is only a safety net).
 * @param {string} recipient
 * @param {string} subjectPart text the subject must contain
 */
Cypress.Commands.add('mailLastTo', (recipient, subjectPart) => {
    const lookup = (retries) => cy.request(`${mailcatcherUrl()}/messages`).then(({ body }) => {
        const matching = body.filter((message) =>
            message.recipients.some((r) => r.includes(recipient)) && message.subject.includes(subjectPart))
        if (matching.length > 0) {
            return cy.request(`${mailcatcherUrl()}/messages/${matching[matching.length - 1].id}.html`)
                .its('body')
        }
        if (retries === 0) {
            throw new Error(`No mail "${subjectPart}" received by ${recipient}`)
        }
        return cy.wait(500).then(() => lookup(retries - 1))
    })

    return lookup(20)
})
