#!/bin/sh
set -e

# storage/ and bootstrap/cache are bind-mounted from the host (see
# docker-compose.yml) so php-fpm's workers can persist logs/sessions/cache
# across deploys — but that means whatever chown happened at image build
# time is shadowed by the host directory's actual ownership, which is
# root:root after any `git reset --hard` run as root on the VPS. php-fpm's
# workers run as www-data and can't write to root-owned files, which fails
# silently into a bare 500 (Laravel can't even log the error it can't
# write). Fix it on every container start instead of relying on a manual
# chown that has to be repeated after every deploy.
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
