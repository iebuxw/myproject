package router

import (
	"go-api/handler"
	"go-api/middleware"

	_ "go-api/docs"

	"github.com/gin-gonic/gin"
	"github.com/go-redis/redis/v8"
	swaggerFiles "github.com/swaggo/files"
	ginSwagger "github.com/swaggo/gin-swagger"
	"gorm.io/gorm"
)

func Setup(db *gorm.DB, rdb *redis.Client, secret string, debug bool) *gin.Engine {
	r := gin.Default()

	// Swagger — 仅开发环境
	if debug {
		r.GET("/swagger/*any", ginSwagger.WrapHandler(swaggerFiles.Handler))
	}

	// CORS — Nginx 同域代理下不需要跨域，仅保留安全头 + OPTIONS 兜底
	r.Use(func(c *gin.Context) {
		c.Header("X-Content-Type-Options", "nosniff")
		c.Header("X-Frame-Options", "DENY")
		if c.Request.Method == "OPTIONS" {
			c.Header("Access-Control-Allow-Methods", "GET,POST,PUT,DELETE,OPTIONS")
			c.Header("Access-Control-Allow-Headers", "Content-Type,Authorization")
			c.AbortWithStatus(204)
			return
		}
		c.Next()
	})

	authHandler := &handler.AuthHandler{DB: db, RDB: rdb, Secret: secret}
	userHandler := &handler.UserHandler{DB: db}

	api := r.Group("/api/v1")
	{
		auth := api.Group("/auth")
		{
			auth.POST("/login", authHandler.Login)
			auth.POST("/refresh", authHandler.Refresh)
		}

		authorized := api.Group("")
		authorized.Use(middleware.JWTAuth(secret, rdb))
		{
			authorized.POST("/auth/logout", authHandler.Logout)

			user := authorized.Group("/user")
			{
				user.GET("/profile", userHandler.GetProfile)
				user.PUT("/profile", userHandler.UpdateProfile)
			}
		}
	}

	return r
}
