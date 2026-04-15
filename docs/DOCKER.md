## Local Development Environment

### wp-env (Recommended)

The project uses [@wordpress/wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) for local development, consistent with the WooCommerce ecosystem.

**Prerequisites:**
- Docker Desktop installed and running
- Node.js 20.18.1+ and npm 10.2.3+

**Getting started:**

```bash
npm install          # Installs dependencies including @wordpress/env
npm run up           # Starts the wp-env environment
```

- The development site is available at <http://localhost:8072>
- Default credentials: admin / password
- WooCommerce is automatically installed and activated
- The Stripe plugin is mounted from the current directory

**Useful commands:**

```bash
npm run up           # Start the environment
npm run down         # Stop the environment (preserves state)
npm run destroy      # Remove the environment entirely
npm run wp -- <cmd>  # Run WP-CLI commands (e.g., npm run wp -- plugin list)
npm run test:php:wp-env  # Run PHPUnit tests
```

### Legacy Docker Setup

The legacy Docker setup using docker-compose is still available:

- Run `npm run docker:up` to start the legacy Docker environment
- Run `npm run docker:down` to stop it
- Run `npm run test:php` to run PHPUnit tests via the legacy Docker setup
- The state of the environment will be persisted in `docker/wordpress` and `docker/data`. To restart the environment simply run `npm run docker:up` again. To start afresh, delete these folders and let `npm run docker:up` re-create them.
