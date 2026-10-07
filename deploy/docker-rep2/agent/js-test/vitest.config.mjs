import { defineConfig } from 'vitest/config';

export default defineConfig({
  server: {
    fs: {
      allow: ['/var/www'],
    },
  },
  test: {
    include: ['/var/www/test/**/*.spec.js'],
    environment: 'jsdom',
  },
});
