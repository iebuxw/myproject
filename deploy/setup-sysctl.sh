#!/bin/bash
# 宿主机内核参数调优 — 需在宿主机上以 root 执行
# 用法：sudo bash deploy/setup-sysctl.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SYSCTL_SRC="$SCRIPT_DIR/sysctl.conf"
SYSCTL_DST="/etc/sysctl.d/99-app.conf"

if [ "$EUID" -ne 0 ]; then
    echo "请使用 sudo 执行此脚本"
    exit 1
fi

echo "=> 安装 sysctl 配置: $SYSCTL_SRC -> $SYSCTL_DST"
cp "$SYSCTL_SRC" "$SYSCTL_DST"

echo "=> 立即生效"
sysctl -p "$SYSCTL_DST"

echo "=> 验证结果"
sysctl fs.file-max vm.swappiness vm.overcommit_memory

echo "完成！参数已写入 $SYSCTL_DST，重启后自动生效"
