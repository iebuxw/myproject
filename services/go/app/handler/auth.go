package handler

import (
	"context"
	"time"

	"go-api/middleware"
	"go-api/model"

	"github.com/gin-gonic/gin"
	"github.com/go-redis/redis/v8"
	"github.com/golang-jwt/jwt/v4"
	"gorm.io/gorm"
)

type AuthHandler struct {
	DB     *gorm.DB
	RDB    *redis.Client
	Secret string
}

type LoginReq struct {
	Phone    string `json:"phone" binding:"required"`
	Password string `json:"password" binding:"required"`
}

// POST /api/v1/auth/login
func (h *AuthHandler) Login(c *gin.Context) {
	var req LoginReq
	if err := c.ShouldBindJSON(&req); err != nil {
		model.Error(c, 1002, "参数错误")
		return
	}

	user, err := model.GetUserByPhone(h.DB, req.Phone)
	if err != nil {
		model.Error(c, 1003, "手机号或密码错误")
		return
	}

	if !user.CheckPassword(req.Password) {
		model.Error(c, 1003, "手机号或密码错误")
		return
	}

	accessToken, expiresIn, err := middleware.GenerateToken(h.Secret, user)
	if err != nil {
		model.Error(c, 500, "生成token失败")
		return
	}

	refreshToken, err := middleware.GenerateRefreshToken(h.Secret, user)
	if err != nil {
		model.Error(c, 500, "生成refresh token失败")
		return
	}

	model.Success(c, model.LoginRes{
		AccessToken:  accessToken,
		RefreshToken: refreshToken,
		ExpiresIn:    expiresIn,
	})
}

// POST /api/v1/auth/refresh
func (h *AuthHandler) Refresh(c *gin.Context) {
	var req struct {
		RefreshToken string `json:"refresh_token" binding:"required"`
	}
	if err := c.ShouldBindJSON(&req); err != nil {
		model.Error(c, 1002, "参数错误")
		return
	}

	claims := &middleware.Claims{}
	token, err := jwt.ParseWithClaims(req.RefreshToken, claims, func(t *jwt.Token) (interface{}, error) {
		return []byte(h.Secret), nil
	})

	if err != nil || !token.Valid {
		model.Error(c, 1001, "refresh token无效")
		return
	}

	user, err := model.GetUserByID(h.DB, claims.UserID)
	if err != nil {
		model.Error(c, 1001, "用户不存在")
		return
	}

	accessToken, expiresIn, err := middleware.GenerateToken(h.Secret, user)
	if err != nil {
		model.Error(c, 500, "生成token失败")
		return
	}

	model.Success(c, model.LoginRes{
		AccessToken: accessToken,
		ExpiresIn:   expiresIn,
	})
}

// POST /api/v1/auth/logout
func (h *AuthHandler) Logout(c *gin.Context) {
	tokenStr, _ := c.Get("token")
	// 加入黑名单，有效期同 token
	ctx := context.Background()
	h.RDB.Set(ctx, "blacklist:"+tokenStr.(string), "1", 2*time.Hour)
	model.Success(c, nil)
}
