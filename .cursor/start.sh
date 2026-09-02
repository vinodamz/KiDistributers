#!/usr/bin/env bash
# Per-boot startup for the Ki Distributers Cloud Agent environment.
# Ensures MariaDB is running. Idempotent: does nothing if it is already up.
set -euo pipefail

echo "==> Ensuring MariaDB is running"
if sudo mariadb -e "SELECT 1" >/dev/null 2>&1; then
    echo "    MariaDB already running"
    exit 0
fi

sudo service mariadb start || true
for _ in $(seq 1 30); do
    if sudo mariadb -e "SELECT 1" >/dev/null 2>&1; then
        echo "    MariaDB started"
        exit 0
    fi
    sleep 1
done

echo "    Falling back to mariadbd-safe"
sudo mariadbd-safe --datadir=/var/lib/mysql >/tmp/mariadb.log 2>&1 &
for _ in $(seq 1 30); do
    if sudo mariadb -e "SELECT 1" >/dev/null 2>&1; then
        echo "    MariaDB started (mariadbd-safe)"
        exit 0
    fi
    sleep 1
done

echo "    ERROR: MariaDB did not become ready" >&2
exit 1
