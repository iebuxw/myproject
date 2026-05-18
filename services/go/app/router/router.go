package router

import (
	"go-api/handler"
	"go-api/middleware"

	"github.com/gin-gonic/gin"
	"github.com/go-redis/redis/v8"
	"gorm.io/gorm"
)

func Setup(db *gorm.DB, rdb *redis.Client, secret string) *gin.Engine {
	r := gin.Default()

	// CORS
	r.Use(func(c *gin.Context) {
		c.Header("Access-Control-Allow-Origin", "*")
		c.Header("Access-Control-Allow-Methods", "GET,POST,PUT,DELETE,OPTIONS")
		c.Header("Access-Control-Allow-Headers", "Content-Type,Authorization")
		if c.Request.Method == "OPTIONS" {
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

		// 需要 JWT 认证
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
