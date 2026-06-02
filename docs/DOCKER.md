## Docker Local Setup

Docker can be used to set up a local development environment:

- Ensure Docker is installed ([Docker Desktop](https://www.docker.com/products/docker-desktop) is a good option for developers)
- From the root of this project, run `npm run up:recreate` for the first run (also auto-starts shared infrastructure)
- For subsequent runs, `npm run up` is enough
- The fully configured site can now be accessed at `http://localhost:<PORT>/wp-admin/` (check `.env` for your port; default `8072` for the main checkout)
- The prompt to run the setup wizard can be dismissed unless there is something specific you would like to configure

To shut down:

- `npm run down` stops this worktree's WordPress container
- `npm run infra:down` stops the shared database + phpMyAdmin
- State is persisted in `docker/data` (database) and the shared Docker volumes (`wcstripe-plugins`, `wcstripe-themes`, `wcstripe-uploads`, `wcstripe-mu-plugins`). To start completely fresh, run `npm run infra:down`, then `docker volume rm wcstripe-plugins wcstripe-themes wcstripe-uploads wcstripe-mu-plugins`, then delete `docker/data`, then `npm run up:recreate`.

The shared database uses `mariadb:11.8`. If `docker/data` was previously written by a different MariaDB major, delete it before the first `npm run up:recreate`. MariaDB cannot read a redo log written by a newer major version and the container will restart-loop on `Unsupported redo log format`.

## Git worktrees

This plugin supports running multiple git worktrees in parallel — each worktree gets its own WordPress container on its own port, while sharing a single MariaDB instance and the `wp-content/{plugins,themes,uploads,mu-plugins}` directories via Docker volumes.

### First-time setup (main checkout)

```bash
npm install
composer install
npm run up:recreate
```

This brings up the shared infrastructure (db + phpMyAdmin) and starts a WordPress container for this checkout on `localhost:8072`.

### Adding a new worktree

```bash
git worktree add ../woocommerce-gateway-stripe-feature-x develop
cd ../woocommerce-gateway-stripe-feature-x
npm install
composer install
npm run up:recreate
```

`bin/docker-port-setup.sh` writes `.env` with `WORKTREE_ID=<sanitized-basename>` and an unused `WORDPRESS_PORT` in the range 8170–8189. `bin/docker-preflight.sh` auto-starts the shared infrastructure from the main checkout if it isn't already running.

The worktree indicator mu-plugin shows the active `WORKTREE_ID` in the wp-admin bar so you can tell parallel instances apart.

### Useful commands

- `npm run worktree:status` — list all worktrees, their ports/URLs, and container states. Warns about orphan containers.
- `npm run worktree:cleanup` — run before `git worktree remove` to stop the container, drop the worktree's test database (`wcstripe_tests_<id>`), and remove `.env`.
- `npm run infra:down` — stop the shared db + phpMyAdmin.

### Test databases

PHPUnit and paratest read `WORKTREE_ID` from `.env` and target `wcstripe_tests_<id>`, so parallel test runs across worktrees don't collide.
