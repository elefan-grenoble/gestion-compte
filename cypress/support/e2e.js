// ***********************************************************
// This example support/e2e.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Import commands.js using ES2015 syntax:
import './commands'

// Alternatively you can use CommonJS syntax:
// require('./commands')

// ---------------------------------------------------------------------------
// Uncaught exceptions
//
// An exception thrown by the application's own JavaScript fails the test
// (Cypress' default). Only the errors listed here are tolerated: each entry
// states why, so that it gets removed once the application is fixed. A new
// front-end error must be fixed, or documented here on purpose.
// ---------------------------------------------------------------------------
// Each entry: { message: '<part of the error message>' } with a comment that
// says why it is tolerated. None at the moment (JS-INIT-COLLAPSIBLE fixed).
const KNOWN_APPLICATION_ERRORS = []

Cypress.on('uncaught:exception', (err) => {
    const known = KNOWN_APPLICATION_ERRORS.some((knownError) => err.message.includes(knownError.message))

    // false: ignore the error. Anything else lets Cypress fail the test.
    return known ? false : undefined
})
