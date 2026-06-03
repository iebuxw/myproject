---
paths:
  - "services/go/**"
---

# Go 项目结构

```
services/go/app/
├── main.go              # 入口：初始化 DB/Redis，启动 Gin
├── config/config.go      # 环境变量读取（含默认值回退）
├── router/router.go      # 路由注册 + CORS 中间件
├── middleware/jwt.go     # JWT 生成/验证 + Claims 定义
├── handler/auth.go       # Login/Refresh/Logout
├── handler/user.go       # GetProfile/UpdateProfile
└── model/
    ├── user.go           # User 模型 + bcrypt 密码方法 + 数据库查询
    └── response.go       # 统一响应结构体 + Success/Error 辅助函数
```

Go 模块名 `go-api`，Go 1.18。依赖：gin, gorm (MySQL), go-redis/v8, golang-jwt/v4, x/crypto。

## 框架文档

写 Go 代码时必须遵循 Gin/Gorm 规范，不确定用法时用 WebFetch 查阅官方文档：

- **Gin**：https://gin-gonic.com/zh-cn/docs/
- **Gorm**：https://gorm.io/zh_CN/docs/
