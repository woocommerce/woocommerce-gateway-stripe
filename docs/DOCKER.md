## Docker Local Setup

Docker can be used to setup a local development environment:

- Ensure Docker is installed ([Docker Desktop](https://www.docker.com/products/docker-desktop) is a good option for developers)
- From the root of this project, run `docker-compose up -d`
- Once <http://localhost:8082> displays the WordPress install screen, run `./bin/docker-setup.sh`
- The fully configured site can now be accessed on <http://localhost:8082>
- The prompt to run the setup wizard can be dismissed unless there is something specific you would like to configure

To shutdown:

- Use `docker-compose down` to stop the running containers
- The state of the environment will be persisted in `docker/wordpress` and `docker/data`. To restart the environment simply run `docker-compose up -d` again. To start afresh, delete these folders and let `docker-compose up` re-create them.
