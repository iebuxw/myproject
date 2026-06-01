#!/bin/bash
# 清理 ThinkPHP 旧日志，保留最近 30 天
find /var/www/html/runtime/log -name "*.log" -type f -mtime +30 -delete
