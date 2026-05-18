package middleware

import (
	"context"
	"net/http"
	"strings"
	"time"

	"go-api/model"

	"github.com/gin-gonic/gin"
	"github.com/go-redis/redis/v8"
	"github.com/golang-jwt/jwt/v4"
)

type Claims struct {
	UserID   uint   `json:"user_id"`
	Phone    string `json:"phone"`
	jwt.RegisteredClaims
}

func GenerateToken(secret string, user *model.User) (string, int64, error) {
	expiresAt := time.Now().Add(2 * time.Hour)
	claims := &Claims{
		UserID: user.ID,
		Phone:  user.Phone,
		RegisteredClaims: jwt.RegisteredClaims{
			ExpiresAt: jwt.NewNumericDate(expiresAt),
			IssuedAt:  jwt.NewNumericDate(time.Now()),
		},
	}
	token, err := jwt.NewWithClaims(jwt.SigningMethodHS256, claims).SignedString([]byte(secret))
	if err != nil {
		return "", 0, err
	}
	return token, expiresAt.Unix(), nil
}

func GenerateRefreshToken(secret string, user *model.User) (string, error) {
	claims := &Claims{
		UserID: user.ID,
		Phone:  user.Phone,
		RegisteredClaims: jwt.RegisteredClaims{
			ExpiresAt: jwt.NewNumericDate(time.Now().Add(7 * 24 * time.Hour)),
			IssuedAt:  jwt.NewNumericDate(time.Now()),
		},
	}
	return jwt.NewWithClaims(jwt.SigningMethodHS256, claims).SignedString([]byte(secret))
}

func JWTAuth(secret string, rdb *redis.Client) gin.HandlerFunc {
	return func(c *gin.Context) {
		authHeader := c.GetHeader("Authorization")
		if authHeader == "" || !strings.HasPrefix(authHeader, "Bearer ") {
			c.AbortWithStatusJSON(http.StatusOK, model.Response{Code: 1001, Msg: "未登录", Data: nil})
			return
		}

		tokenStr := strings.TrimPrefix(authHeader, "Bearer ")

		// 检查黑名单
		ctx := context.Background()
		exists, _ := rdb.Exists(ctx, "blacklist:"+tokenStr).Result()
		if exists > 0 {
			c.AbortWithStatusJSON(http.StatusOK, model.Response{Code: 1001, Msg: "token已失效", Data: nil})
			return
		}

		claims := &Claims{}
		token, err := jwt.ParseWithClaims(tokenStr, claims, func(t *jwt.Token) (interface{}, error) {
			return []byte(secret), nil
		})

		if err != nil || !token.Valid {
			c.AbortWithStatusJSON(http.StatusOK, model.Response{Code: 1001, Msg: "token无效", Data: nil})
			return
		}

		c.Set("user_id", claims.UserID)
		c.Set("phone", claims.Phone)
		c.Set("token", tokenStr)
		c.Next()
	}
}
