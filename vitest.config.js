import { defineConfig } from 'vitest/config';

export default defineConfig( {
	test: {
		environment: 'jsdom',
		include: [ 'moduly/*/tests/**/*.test.js' ],
		coverage: {
			provider: 'v8',
			include: [ 'moduly/*/assets/**/*.js' ],
			reporter: [ 'text' ],
			thresholds: { lines: 100, functions: 100, branches: 100, statements: 100 },
		},
	},
} );
