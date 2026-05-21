FROM mysql:8.0

COPY init.sql /docker-entrypoint-initdb.d/001_init.sql
COPY migrations/003_echoes_notification.sql /docker-entrypoint-initdb.d/003_echoes_notification.sql
COPY migrations/004_liaisons_ports.sql /docker-entrypoint-initdb.d/004_liaisons_ports.sql
COPY migrations/005_flows.sql /docker-entrypoint-initdb.d/005_flows.sql
COPY migrations/2026_05_02_signal_mail.sql /docker-entrypoint-initdb.d/006_signal_mail.sql
COPY migrations/007_query_indexes.sql /docker-entrypoint-initdb.d/007_query_indexes.sql
