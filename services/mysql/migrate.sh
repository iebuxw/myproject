#!/bin/bash
# 数据库迁移脚本 — 只执行未运行过的 .sql 文件
# 用法: bash services/mysql/migrate.sh

set -e

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
MIGRATIONS_DIR="$ROOT_DIR/services/mysql/migrations"
ENV_FILE="$ROOT_DIR/.env"

# 读取密码
if [ -f "$ENV_FILE" ]; then
    MYSQL_PASSWORD=$(grep MYSQL_ROOT_PASSWORD "$ENV_FILE" | cut -d= -f2)
fi
MYSQL_PASSWORD=${MYSQL_PASSWORD:-root123}

MYSQL_EXEC="docker exec -i mysql mysql -uroot -p${MYSQL_PASSWORD} --default-character-set=utf8mb4 myproject"

echo "[migrate] 检查迁移文件..."

# 确保追踪表存在（幂等，init.sql 里已经建了，这里做兜底）
$MYSQL_EXEC -e "CREATE TABLE IF NOT EXISTS migrations (filename VARCHAR(255) PRIMARY KEY, applied_at DATETIME DEFAULT CURRENT_TIMESTAMP);" 2>/dev/null

# 没有迁移文件则退出
if [ ! -d "$MIGRATIONS_DIR" ] || [ -z "$(ls -1 "$MIGRATIONS_DIR"/*.sql 2>/dev/null)" ]; then
    echo "[migrate] 没有 .sql 迁移文件"
    exit 0
fi

COUNT=0
for f in "$MIGRATIONS_DIR"/*.sql; do
    name=$(basename "$f")
    applied=$($MYSQL_EXEC -N -e "SELECT COUNT(*) FROM migrations WHERE filename='$name';" 2>/dev/null || echo "0")
    applied=$(echo "$applied" | tr -d '[:space:]')

    if [ "$applied" = "0" ]; then
        echo "[migrate] ▶ $name"
        cat "$f" | $MYSQL_EXEC
        $MYSQL_EXEC -e "INSERT INTO migrations (filename) VALUES ('$name');"
        echo "[migrate] ✓ $name"
        COUNT=$((COUNT + 1))
    else
        echo "[migrate] - $name (已执行，跳过)"
    fi
done

echo "[migrate] 完成，执行了 $COUNT 个迁移"
