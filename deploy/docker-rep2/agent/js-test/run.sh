#!/bin/sh
set -e
if ! command -v node >/dev/null 2>&1 || [ ! -d /opt/js-test-env/node_modules ]; then
    apk add --no-cache nodejs npm 2>&1 | tail -2
    mkdir -p /opt/js-test-env
    cp /var/www/js-test/js-test-env/package*.json /opt/js-test-env/
    ln -sfn /opt/js-test-env/node_modules /var/www/node_modules
    cd /opt/js-test-env
    if [ -f package-lock.json ]; then
        npm ci --no-audit --no-fund 2>&1 | tail -3
    else
        npm install --no-audit --no-fund 2>&1 | tail -3
    fi
fi
cd /opt/js-test-env
exec npx vitest run --config /var/www/js-test/vitest.config.mjs "$@"
