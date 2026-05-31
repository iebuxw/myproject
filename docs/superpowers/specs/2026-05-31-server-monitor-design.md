# 系统资源监控设计文档

## 概述

在后台管理系统仪表盘页面添加服务器 CPU、内存、磁盘使用率展示，以 3 张卡片形式呈现在页面顶部。

## 监控目标

服务器宿主机整体资源占用，非 Docker 容器级别。

## 后端 API

### 接口

`GET /admin/server/info`，需认证（归入现有路由组）。

### 控制器

新增 `services/php/app/application/admin/controller/Server.php`。

### 返回结构

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "cpu": {
      "usage": 23.5,
      "cores": 4,
      "load_avg": "0.15, 0.12, 0.08"
    },
    "memory": {
      "total": 16384,
      "used": 8192,
      "free": 8192,
      "usage": 50.0
    },
    "disk": {
      "total": 500,
      "used": 250,
      "free": 250,
      "usage": 50.0
    }
  }
}
```

单位：内存 MB，磁盘 GB，使用率百分比。某个指标获取失败时该字段返回 `null`。

### 数据获取方式

- **CPU**：优先读取 `/host_proc/stat` 两次采样（间隔 1s）计算使用率 + 核心数，回退 `sys_getloadavg()`
- **内存**：优先解析 `/host_proc/meminfo` 的 MemTotal/MemAvailable，回退 `shell_exec('free -m')`
- **磁盘**：`disk_total_space()` / `disk_free_space()`（PHP 原生函数，跨平台）

### docker-compose 修改

将宿主机 `/proc` 只读挂载到 PHP 容器 `/host_proc`：

```yaml
php:
  volumes:
    - /proc:/host_proc:ro
```

PHP 优先读 `/host_proc`，读不到则回退容器内 `/proc` 或系统命令。

## 前端展示

### 修改文件

`frontend/src/views/dashboard/index.vue`

### 布局

仪表盘页面上方新增 `el-row`，内含 3 个 `el-col :span="8"`，每个包含一张 `el-card`。

### 卡片内容

| 卡片 | 标题 | 主数据 | 辅助信息 | 进度条 |
|------|------|--------|----------|--------|
| CPU | CPU 使用率 | `23.5%` | 4 核 / 负载 0.15 | `el-progress` |
| 内存 | 内存使用率 | `8.0 / 16.0 GB` | 已用 / 总量 | `el-progress` |
| 磁盘 | 磁盘使用率 | `250 / 500 GB` | 已用 / 总量 | `el-progress` |

### 进度条颜色

`el-progress` 的 `color` 属性根据使用率动态变化：

- < 60%：绿色 `#67C23A`
- 60%-80%：橙色 `#E6A23C`
- >= 80%：红色 `#F56C6C`

### 数据加载

页面 `created` 钩子调用 `GET /admin/server/info`，复用现有 axios 实例，赋值组件 data，无轮询。

### 错误处理

- API 请求失败：卡片区域显示"暂无数据"占位文字
- 某字段为 `null`：显示 `--` 占位

## 权限与菜单

- 接口归入现有路由组，受 Auth 中间件保护，无需额外权限配置
- 不新增菜单项，数据展示在现有仪表盘页面
