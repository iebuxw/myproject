package main

import (
	"fmt"
	"log"

	"go-api/config"
	"go-api/router"

	"github.com/go-redis/redis/v8"
	"gorm.io/driver/mysql"
	"gorm.io/gorm"
)

func main() {
	cfg := config.Load()

	// MySQL
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?charset=utf8mb4&parseTime=True&loc=Local",
		cfg.DBUser, cfg.DBPass, cfg.DBHost, cfg.DBPort, cfg.DBName)

	db, err := gorm.Open(mysql.Open(dsn), &gorm.Config{})
	if err != nil {
		log.Fatalf("MySQL connect failed: %v", err)
	}

	// Redis
	rdb := redis.NewClient(&redis.Options{
		Addr:     fmt.Sprintf("%s:%s", cfg.RedisHost, cfg.RedisPort),
		Password: cfg.RedisPass,
		DB:       0,
	})

	// Router
	r := router.Setup(db, rdb, cfg.JWTSecret)

	log.Printf("Go API starting on :%s", cfg.GoPort)
	if err := r.Run(fmt.Sprintf(":%s", cfg.GoPort)); err != nil {
		log.Fatalf("Server start failed: %v", err)
	}
}
