#!/bin/sh
set -e

cd /app/src

# 计算当前源码 hash（检查 .go / go.mod / go.sum 是否变更）
hash=$(find . -type f \( -name '*.go' -o -name 'go.mod' -o -name 'go.sum' \) \
    -exec md5sum {} + | sort -k2 | md5sum | cut -d' ' -f1)

# 仅当源码变更或二进制不存在时才编译
if [ ! -f /app/server ] || [ "$hash" != "$(cat .src-hash 2>/dev/null)" ]; then
    GOPROXY=https://goproxy.cn,direct go mod download
    CGO_ENABLED=0 go build -ldflags="-s -w" -o /app/server main.go
    echo "$hash" > .src-hash
    echo "[go] recompiled (source changed)"
else
    echo "[go] no changes, skip compile"
fi

exec /app/server
