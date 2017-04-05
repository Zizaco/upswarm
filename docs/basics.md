## Installation

### Requirements

Upswarm has a few system requirements. All of these requirements are satisfied by running the aplication using [Docker Compose](https://docs.docker.com/compose/install/), so it's recommended that you use Docker Compose in your local development environment.

- Unix based environment (Mac OSX or Linux)
- PHP**7**
- [ZMQ Extension](http://php.net/zmq)

### Installing Upswarm

Upswarm utilizes [Composer](https://getcomposer.org/) to manage its dependencies. So, before using Upswarm, make sure you have Composer installed on your machine.

**Creating a project**

```bash
composer create-project --prefer-dist zizaco/upswarm-project <project-name>
```

## Running

If you have PHP installed locally, to run an Upswarm application you may use the `serve` Upswarm command. This command will start a the _Supervisor_ and the _Services_ in `topology.json`:

```bash
php artisan serve
```

Of course, you can also run an Upswarm application using Docker:

```bash
docker-compose up
```
