#!/bin/sh
# Nginx 日志轮转脚本
# 按大小切割，超过 10MB 时轮转，保留最近 7 个归档

LOG_DIR="/var/log/nginx"
MAX_SIZE=10485760  # 10MB
KEEP=7

for log_file in "$LOG_DIR"/*.log; do
    [ -f "$log_file" ] || continue

    size=$(stat -f%z "$log_file" 2>/dev/null || echo 0)
    if [ "$size" -lt "$MAX_SIZE" ]; then
        continue
    fi

    base="${log_file%.log}"
    # 轮转已有归档
    i=$((KEEP - 1))
    while [ "$i" -ge 1 ]; do
        src="${base}.${i}.gz"
        dst="${base}.$((i + 1)).gz"
        [ -f "$src" ] && mv "$src" "$dst"
        i=$((i - 1))
    done

    # 压缩当前日志为 .1.gz
    mv "$log_file" "${base}.1"
    gzip -f "${base}.1"

    # 删除超出保留数量的归档
    rm -f "${base}."$((KEEP + 1)).gz
done

# 通知 Nginx 重新打开日志文件
if [ -f /var/run/nginx.pid ]; then
    kill -USR1 "$(cat /var/run/nginx.pid)"
fi
