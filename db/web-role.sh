#!/bin/sh
set -eu

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
    -v web_user="$DB_USER" -v web_password="$DB_PASSWORD" <<'SQL'
CREATE ROLE :"web_user" LOGIN PASSWORD :'web_password';
GRANT SELECT ON ALL TABLES IN SCHEMA public TO :"web_user";
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO :"web_user";
ALTER ROLE :"web_user" SET statement_timeout = '15s';
SQL
