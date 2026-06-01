#!/bin/bash
# 宿主机内核参数调优 — 需在宿主机上以 root 执行
# 用法：
#   应用：sudo bash deploy/setup-sysctl.sh
#   回滚：sudo bash deploy/setup-sysctl.sh rollback（删除配置文件，重启后恢复默认值）

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SYSCTL_SRC="$SCRIPT_DIR/sysctl.conf"
SYSCTL_DST="/etc/sysctl.d/99-app.conf"

if [ "$EUID" -ne 0 ]; then
    echo "请使用 sudo 执行此脚本"
    exit 1
fi

# 回滚：删除配置文件，重启后自动恢复系统默认值
if [ "$1" = "rollback" ]; then
    rm -f "$SYSCTL_DST"
    echo "=> 已删除 $SYSCTL_DST，重新加载系统默认值"
    sysctl --system
    echo "=> 验证结果"
    sysctl fs.file-max vm.swappiness vm.overcommit_memory
    exit 0
fi

echo "=> 安装 sysctl 配置: $SYSCTL_SRC -> $SYSCTL_DST"
cp "$SYSCTL_SRC" "$SYSCTL_DST"

echo "=> 立即生效"
sysctl -p "$SYSCTL_DST"

echo "=> 验证结果"
sysctl fs.file-max vm.swappiness vm.overcommit_memory

echo "完成！参数已写入 $SYSCTL_DST，重启后自动生效"
echo "如需回滚：sudo bash $0 rollback"
