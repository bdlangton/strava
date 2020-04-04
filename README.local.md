# Starting and Stopping Docker

```
docker-compose up -d
docker-compose stop
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
