#!/bin/bash
# 数据库迁移脚本 — 在 MySQL 容器内执行
# 用法: docker exec mysql bash /scripts/migrate.sh
# 说明: services/mysql/ 已挂载到容器 /scripts，迁移文件在 /scripts/migrations/

set -e

MIGRATIONS_DIR="/scripts/migrations"

# MYSQL_ROOT_PASSWORD 是 MySQL 容器的环境变量，无需读取 .env
echo "[migrate] 检查迁移文件..."

# 确保追踪表存在（幂等，init.sql 里已经建了，这里做兜底）
mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" myproject -e "CREATE TABLE IF NOT EXISTS migrations (filename VARCHAR(255) PRIMARY KEY, applied_at DATETIME DEFAULT CURRENT_TIMESTAMP);" --default-character-set=utf8mb4

# 没有迁移文件则退出
if [ ! -d "$MIGRATIONS_DIR" ] || [ -z "$(ls -1 "$MIGRATIONS_DIR"/*.sql 2>/dev/null)" ]; then
    echo "[migrate] 没有 .sql 迁移文件"
    exit 0
fi

COUNT=0
for f in "$MIGRATIONS_DIR"/*.sql; do
    name=$(basename "$f")
    applied=$(mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" myproject -N -e "SELECT COUNT(*) FROM migrations WHERE filename='$name';" 2>/dev/null || echo "0")
    applied=$(echo "$applied" | tr -d '[:space:]')

    if [ "$applied" = "0" ]; then
        echo "[migrate] ▶ $name"
        mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" myproject --default-character-set=utf8mb4 < "$f"
        mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" myproject -e "INSERT INTO migrations (filename) VALUES ('$name');"
        echo "[migrate] ✓ $name"
        COUNT=$((COUNT + 1))
    else
        echo "[migrate] - $name (已执行，跳过)"
    fi
done

echo "[migrate] 完成，执行了 $COUNT 个迁移"
