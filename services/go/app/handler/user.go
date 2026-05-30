package handler

import (
	"go-api/model"

	"github.com/gin-gonic/gin"
	"gorm.io/gorm"
)

type UserHandler struct {
	DB *gorm.DB
}

type ProfileRes struct {
	ID       uint   `json:"id"`
	Phone    string `json:"phone"`
	Nickname string `json:"nickname"`
	Avatar   string `json:"avatar"`
	Email    string `json:"email"`
	Gender   int8   `json:"gender"`
}

type UpdateProfileReq struct {
	Nickname string `json:"nickname"`
	Avatar   string `json:"avatar"`
	Email    string `json:"email"`
	Gender   int8   `json:"gender"`
}

// @Summary 获取用户信息
// @Tags 用户
// @Produce json
// @Security Bearer
// @Success 200 {object} model.Response{data=handler.ProfileRes}
// @Router /user/profile [get]
func (h *UserHandler) GetProfile(c *gin.Context) {
	userID, _ := c.Get("user_id")

	user, err := model.GetUserByID(h.DB, userID.(uint))
	if err != nil {
		model.Error(c, 1004, "用户不存在")
		return
	}

	model.Success(c, ProfileRes{
		ID:       user.ID,
		Phone:    user.Phone,
		Nickname: user.Nickname,
		Avatar:   user.Avatar,
		Email:    user.Email,
		Gender:   user.Gender,
	})
}

// @Summary 更新用户信息
// @Tags 用户
// @Accept json
// @Produce json
// @Security Bearer
// @Param body body handler.UpdateProfileReq true "更新参数"
// @Success 200 {object} model.Response
// @Router /user/profile [put]
func (h *UserHandler) UpdateProfile(c *gin.Context) {
	userID, _ := c.Get("user_id")

	user, err := model.GetUserByID(h.DB, userID.(uint))
	if err != nil {
		model.Error(c, 1004, "用户不存在")
		return
	}

	var req UpdateProfileReq
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
