#!/bin/sh
# 生成本地开发用自签名证书
# 证书打在镜像里，线上用 volume 挂载真实证书覆盖

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
CERTS_DIR="$SCRIPT_DIR/certs"

mkdir -p "$CERTS_DIR"

openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
  -keyout "$CERTS_DIR/server.key" \
  -out "$CERTS_DIR/server.crt" \
  -subj "/C=CN/ST=Beijing/L=Beijing/O=Dev/CN=localhost"

echo "证书已生成: $CERTS_DIR/server.crt, $CERTS_DIR/server.key"
