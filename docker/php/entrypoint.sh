#!/bin/sh
set -eu

APP=/var/www/app

# If ./app did not exist on the host, Docker creates it as root.
if [ "$(stat -c %u "$APP")" != "$(id -u app)" ]; then
  chown app:app "$APP"
fi

# Scaffold / install / set up WordPress as the unprivileged user,
# then hand over to php-fpm.
gosu app app-init

exec "$@"
