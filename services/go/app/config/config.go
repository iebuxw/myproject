package config

import "os"

type Config struct {
	DBHost    string
	DBPort    string
	DBName    string
	DBUser    string
	DBPass    string
	RedisHost string
	RedisPort string
	RedisPass string
	JWTSecret string
	GoPort    string
	Debug     bool
}

func Load() *Config {
	return &Config{
		DBHost:    getEnv("DB_HOST", "mysql"),
		DBPort:    getEnv("DB_PORT", "3306"),
		DBName:    getEnv("DB_NAME", "myproject"),
		DBUser:    getEnv("DB_USER", "app"),
		DBPass:    getEnv("DB_PASS", "app123"),
		RedisHost: getEnv("REDIS_HOST", "redis"),
		RedisPort: getEnv("REDIS_PORT", "6379"),
		RedisPass: getEnv("REDIS_PASSWORD", ""),
		JWTSecret: getEnv("JWT_SECRET", "myproject-jwt-secret"),
		GoPort:    getEnv("GO_PORT", "8080"),
		Debug:     getEnv("APP_DEBUG", "0") == "1",
	}
}

func getEnv(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}
