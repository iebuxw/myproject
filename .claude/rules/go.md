---
paths:
  - "services/go/**"
---

# Go 项目结构

```
services/go/app/
├── main.go               # 入口：初始化 DB/Redis，启动 Gin
├── config/config.go      # 环境变量读取（含默认值回退）
├── router/router.go      # 路由注册 + CORS + Swagger 中间件
├── middleware            # 中间件（Jwt等）
├── handler               # HTTP 接口处理
├── model                 # 数据模型 & 数据库操作
└── docs/                 # Swagger 自动生成（swag init），勿手动编辑
    ├── docs.go
    ├── swagger.json
    └── swagger.yaml
```

Go 模块名 `go-api`，Go 1.18。依赖：gin, gorm (MySQL), go-redis/v8, golang-jwt/v4, x/crypto, swaggo/swag等

修改 API 注释后需重新生成 Swagger 文档：`cd services/go/app && swag init`。

## 框架文档

写 Go 代码时必须遵循 Gin/Gorm 规范，不确定用法时用 WebFetch 查阅官方文档：

- **Gin**：https://gin-gonic.com/zh-cn/docs/
- **Gorm**：https://gorm.io/zh_CN/docs/
