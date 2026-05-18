package handler

import (
	"go-api/model"

	"github.com/gin-gonic/gin"
	"gorm.io/gorm"
)

type UserHandler struct {
	DB *gorm.DB
}

// GET /api/v1/user/profile
func (h *UserHandler) GetProfile(c *gin.Context) {
	userID, _ := c.Get("user_id")

	user, err := model.GetUserByID(h.DB, userID.(uint))
	if err != nil {
		model.Error(c, 1004, "用户不存在")
		return
	}

	model.Success(c, gin.H{
		"id":       user.ID,
		"phone":    user.Phone,
		"nickname": user.Nickname,
		"avatar":   user.Avatar,
		"email":    user.Email,
		"gender":   user.Gender,
	})
}

// PUT /api/v1/user/profile
func (h *UserHandler) UpdateProfile(c *gin.Context) {
	userID, _ := c.Get("user_id")

	user, err := model.GetUserByID(h.DB, userID.(uint))
	if err != nil {
		model.Error(c, 1004, "用户不存在")
		return
	}

	var req struct {
		Nickname string `json:"nickname"`
		Avatar   string `json:"avatar"`
		Email    string `json:"email"`
		Gender   int8   `json:"gender"`
	}
	if err := c.ShouldBindJSON(&req); err != nil {
		model.Error(c, 1002, "参数错误")
		return
	}

	updates := map[string]interface{}{}
	if req.Nickname != "" {
		updates["nickname"] = req.Nickname
	}
	if req.Avatar != "" {
		updates["avatar"] = req.Avatar
	}
	if req.Email != "" {
		updates["email"] = req.Email
	}
	if req.Gender > 0 {
		updates["gender"] = req.Gender
	}

	if len(updates) > 0 {
		h.DB.Model(user).Updates(updates)
	}

	model.Success(c, nil)
}
