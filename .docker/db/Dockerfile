FROM mysql:5.7

# Windows で my.cnf が読み込めない問題（パーミッションが 777 になってしまうため）の解決（↓参考URL）
# https://sunday-morning.app/posts/2020-07-31-mysqld-warning-world-writable-config-file-etc-mysql-conf-d-my-cnf-is-ignored

# docker-compose の volumes は後でマウントされるため、先にDockerfileに書く
COPY ./.docker/db/my.cnf /etc/mysql/conf.d/my.cnf
RUN chmod 644 /etc/mysql/conf.d/my.cnf
#
#COPY sh/sqldump_data_c.sh /sqldump_data_c.sh
#COPY sh/sqldump_schema.sh /sqldump_schema.sh