package store

import (
	"database/sql"
	"fmt"

	"s3-proxy/auth"

	_ "github.com/go-sql-driver/mysql"
)

// Store manages database operations
type Store struct {
	db *sql.DB
}

// New creates a new store
func New(dsn string) (*Store, error) {
	db, err := sql.Open("mysql", dsn)
	if err != nil {
		return nil, fmt.Errorf("failed to open database: %w", err)
	}

	if err := db.Ping(); err != nil {
		return nil, fmt.Errorf("failed to connect to database: %w", err)
	}

	return &Store{db: db}, nil
}

// LoadActiveCredentials loads all active credentials from the database
func (s *Store) LoadActiveCredentials() ([]auth.Credential, error) {
	rows, err := s.db.Query(
		"SELECT id, tenant_id, access_key, COALESCE(secret_key_plain, ''), allowed_bucket, allowed_prefix, is_active FROM s3_credentials WHERE is_active = true AND secret_key_plain IS NOT NULL",
	)
	if err != nil {
		return nil, fmt.Errorf("failed to query credentials: %w", err)
	}
	defer rows.Close()

	var creds []auth.Credential
	for rows.Next() {
		var cred auth.Credential
		if err := rows.Scan(
			&cred.ID,
			&cred.TenantID,
			&cred.AccessKey,
			&cred.SecretKeyPlain,
			&cred.AllowedBucket,
			&cred.AllowedPrefix,
			&cred.IsActive,
		); err != nil {
			return nil, fmt.Errorf("failed to scan credential: %w", err)
		}
		// Skip credentials without a secret key
		if cred.SecretKeyPlain == "" {
			continue
		}
		creds = append(creds, cred)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("error iterating credentials: %w", err)
	}

	return creds, nil
}

// UpdateLastUsedAt updates the last_used_at timestamp for a credential
func (s *Store) UpdateLastUsedAt(accessKey string) error {
	result, err := s.db.Exec(
		"UPDATE s3_credentials SET last_used_at = NOW() WHERE access_key = ?",
		accessKey,
	)
	if err != nil {
		return fmt.Errorf("failed to update last_used_at: %w", err)
	}

	affected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("failed to get rows affected: %w", err)
	}

	if affected == 0 {
		return fmt.Errorf("no credential found with access_key: %s", accessKey)
	}

	return nil
}

// Close closes the database connection
func (s *Store) Close() error {
	return s.db.Close()
}
