# Website location

http://localhost:8084

# Starting and Stopping Docker

```
docker-compose up -d
docker-compose stop
```

# RabbitMQ

Purge messages from the queue:

```
docker exec -i strava-rabbitmq sh -c "rabbitmqctl purge_queue messages"
```

Start a consumer:

```
docker exec -i strava-php-fpm sh -c "php bin/console messenger:consume &" 2> /dev/null
```

# Running tests

You can run tests as individual docker commands:

```
docker exec -i strava-php-fpm sh -c "APP_ENV=test ./vendor/bin/phpunit"
docker exec -i strava-php-fpm sh -c "APP_ENV=test ./vendor/bin/behat"
```

Or run it as a single composer command (inside docroot):

```
composer test
```
