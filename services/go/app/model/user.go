package model

import (
	"time"

	"golang.org/x/crypto/bcrypt"
	"gorm.io/gorm"
)

type User struct {
	ID        uint           `gorm:"primarykey" json:"id"`
	Phone     string         `gorm:"column:phone;uniqueIndex;size:20" json:"phone"`
	Password  string         `gorm:"column:password;size:255" json:"-"`
	Nickname  string         `gorm:"column:nickname;size:50" json:"nickname"`
	Avatar    string         `gorm:"column:avatar;size:255" json:"avatar"`
	Email     string         `gorm:"column:email;size:100" json:"email"`
	Gender    int8           `gorm:"column:gender;default:0" json:"gender"`
	Status    int8           `gorm:"column:status;default:1" json:"status"`
	CreatedAt time.Time      `json:"created_at"`
	UpdatedAt time.Time      `json:"updated_at"`
}

func (User) TableName() string {
	return "user"
}

func (u *User) SetPassword(plain string) error {
	hash, err := bcrypt.GenerateFromPassword([]byte(plain), bcrypt.DefaultCost)
	if err != nil {
		return err
	}
	u.Password = string(hash)
	return nil
}

func (u *User) CheckPassword(plain string) bool {
	return bcrypt.CompareHashAndPassword([]byte(u.Password), []byte(plain)) == nil
}

func GetUserByPhone(db *gorm.DB, phone string) (*User, error) {
	var user User
	err := db.Where("phone = ? AND status = 1", phone).First(&user).Error
	if err != nil {
		return nil, err
	}
	return &user, nil
}

func GetUserByID(db *gorm.DB, id uint) (*User, error) {
	var user User
	err := db.First(&user, id).Error
	if err != nil {
		return nil, err
	}
	return &user, nil
}
