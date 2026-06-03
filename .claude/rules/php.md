---
paths:
  - "services/php/**"
---

# PHP 项目结构

```
services/php/app/
├── application/admin/
│   ├── controller/       # Auth, Admin, Role, Menu, User, Attachment, DictType/DictData, LoginLog/OperationLog, Profile, Server
│   └── middleware/Auth.php  # 登录检查 + RBAC 权限查询
├── config/               # database, session, cache, app
├── route/route.php       # 所有路由定义（admin/* 前缀）
└── public/index.php      # TP5.1 入口
```

PHP 7.4 + ThinkPHP 5.1。数据库配置从环境变量读取（`getenv()`）。所有 `/admin` 路由通过 `Auth` 中间件（除 login/logout 外），中间件通过 `admin_role` + `role_menu` 获取管理员的 `authPaths`，各控制器自行检查具体按钮权限。Nginx 通过 fastcgi_pass 转发 PHP，SCRIPT_FILENAME 指向 `/var/www/html/public/index.php`。

## ThinkPHP 5.1 文档

写 PHP 代码时必须遵循 ThinkPHP 5.1 框架规范，不确定用法时用 WebFetch 查阅官方文档：

**文档首页**：https://doc.thinkphp.cn/v5_1/default.html

常用章节直链：
- 架构：https://doc.thinkphp.cn/v5_1/architecture.html
- 路由：https://doc.thinkphp.cn/v5_1/route.html
- 控制器：https://doc.thinkphp.cn/v5_1/controller.html
- 数据库：https://doc.thinkphp.cn/v5_1/database.html
- 模型：https://doc.thinkphp.cn/v5_1/model.html
- 验证：https://doc.thinkphp.cn/v5_1/validate.html
- 中间件：https://doc.thinkphp.cn/v5_1/middleware.html
