FROM mysql:8.0
COPY --chmod=0644 docker/mysql/10-privileges.sh /docker-entrypoint-initdb.d/
COPY --chmod=0644 docker/mysql/20-init-data.sql /docker-entrypoint-initdb.d/

