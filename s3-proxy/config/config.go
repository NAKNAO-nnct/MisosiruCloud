package config

import (
	"fmt"
	"os"
	"time"

	"github.com/joho/godotenv"
)

// Config holds all configuration for the S3 proxy
type Config struct {
	// Server settings
	ListenAddr string
	ListenPort string

	// Backend S3
	S3BackendEndpoint  string
	S3BackendRegion    string
	S3BackendAccessKey string
	S3BackendSecretKey string

	// Database
	DBHost     string
	DBPort     string
	DBUser     string
	DBPassword string
	DBName     string

	// Cache
	CacheRefreshInterval time.Duration

	// Logging
	LogLevel string
}

// Load reads configuration from environment
func Load() (*Config, error) {
	_ = godotenv.Load(".env")

	cfg := &Config{
		ListenAddr:           getEnv("LISTEN_ADDR", "0.0.0.0"),
		ListenPort:           getEnv("LISTEN_PORT", "9000"),
		S3BackendEndpoint:    getEnv("S3_BACKEND_ENDPOINT", ""),
		S3BackendRegion:      getEnv("S3_BACKEND_REGION", ""),
		S3BackendAccessKey:   getEnv("S3_BACKEND_ACCESS_KEY", ""),
		S3BackendSecretKey:   getEnv("S3_BACKEND_SECRET_KEY", ""),
		DBHost:               getEnv("DB_HOST", "localhost"),
		DBPort:               getEnv("DB_PORT", "3306"),
		DBUser:               getEnv("DB_USER", ""),
		DBPassword:           getEnv("DB_PASSWORD", ""),
		DBName:               getEnv("DB_NAME", "database"),
		CacheRefreshInterval: parseDuration(getEnv("CACHE_REFRESH_INTERVAL", "5m"), 5*time.Minute),
		LogLevel:             getEnv("LOG_LEVEL", "info"),
	}

	if err := cfg.Validate(); err != nil {
		return nil, err
	}

	return cfg, nil
}

// Validate checks required configuration
func (c *Config) Validate() error {
	required := map[string]string{
		"S3_BACKEND_ENDPOINT":   c.S3BackendEndpoint,
		"S3_BACKEND_REGION":     c.S3BackendRegion,
		"S3_BACKEND_ACCESS_KEY": c.S3BackendAccessKey,
		"S3_BACKEND_SECRET_KEY": c.S3BackendSecretKey,
		"DB_USER":               c.DBUser,
		"DB_PASSWORD":           c.DBPassword,
	}

	for key, val := range required {
		if val == "" {
			return fmt.Errorf("required environment variable not set: %s", key)
		}
	}

	return nil
}

func getEnv(key, defaultVal string) string {
	if val := os.Getenv(key); val != "" {
		return val
	}
	return defaultVal
}

func parseDuration(val string, defaultVal time.Duration) time.Duration {
	if d, err := time.ParseDuration(val); err == nil {
		return d
	}
	return defaultVal
}
