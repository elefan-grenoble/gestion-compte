const { defineConfig } = require("cypress");

module.exports = defineConfig({
  e2e: {
    baseUrl: 'http://membres.yourcoop.local:8000',
    viewportWidth: 1920,
    viewportHeight: 1080,
  },
  env: {
    keycloakUrl: 'http://localhost:8080',
  },
});
