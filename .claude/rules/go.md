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

## 陷阱

- **零值无法区分"未传"和"传 0"**：更新接口用 `if req.Field > 0` 判断是否更新，导致 `Gender=0`（未知）和 `Status=0`（禁用）无法显式设置。新增更新接口时优先用指针 `*int` 或 separate request struct。
- **登出仅黑名单 access token**：refresh token（7d 有效期）登出后仍可使用，直到自然过期。
- **所有响应返回 HTTP 200**：错误信息在 JSON body 的 `code` 字段，前端不可依赖 HTTP status code。

## 框架文档

写 Go 代码时必须遵循 Gin/Gorm 规范，不确定用法时用 WebFetch 查阅官方文档：

- **Gin**：https://gin-gonic.com/zh-cn/docs/
- **Gorm**：https://gorm.io/zh_CN/docs/
