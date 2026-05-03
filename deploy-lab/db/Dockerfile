FROM mysql:8.0

COPY deploy-lab/db/init/001_init.sql /docker-entrypoint-initdb.d/001_init.sql
COPY deploy-lab/db/init/2026_05_02_signal_mail.sql /docker-entrypoint-initdb.d/2026_05_02_signal_mail.sql
