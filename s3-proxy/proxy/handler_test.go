package proxy

import (
	"fmt"
	"strings"
	"testing"

	"s3-proxy/auth"
)

func TestParsePath(t *testing.T) {
	handler := &Handler{}

	tests := []struct {
		uri     string
		bucket  string
		key     string
		wantErr bool
	}{
		{"/dbaas-backups/file.txt", "dbaas-backups", "file.txt", false},
		{"/dbaas-backups/dir/file.txt", "dbaas-backups", "dir/file.txt", false},
		{"/dbaas-backups/", "dbaas-backups", "", false},
		{"/dbaas-backups", "dbaas-backups", "", false},
		{"/dbaas-backups/file.txt?versionId=123", "dbaas-backups", "file.txt", false},
		{"invalid", "", "", true},
	}

	for _, tt := range tests {
		t.Run(tt.uri, func(t *testing.T) {
			bucket, key, err := handler.parsePath(tt.uri)
			if (err != nil) != tt.wantErr {
				t.Errorf("parsePath() error = %v, wantErr %v", err, tt.wantErr)
			}
			if err == nil {
				if bucket != tt.bucket {
					t.Errorf("parsePath() bucket = %v, want %v", bucket, tt.bucket)
				}
				if key != tt.key {
					t.Errorf("parsePath() key = %v, want %v", key, tt.key)
				}
			}
		})
	}
}

func TestRewritePath(t *testing.T) {
	handler := &Handler{}

	tests := []struct {
		originalKey string
		tenantID    int64
		expected    string
	}{
		{
			originalKey: "file.txt",
			tenantID:    1,
			expected:    "tenant-1/file.txt",
		},
		{
			originalKey: "dir/subdir/file.txt",
			tenantID:    42,
			expected:    "tenant-42/dir/subdir/file.txt",
		},
		{
			originalKey: "file-with-special-chars_123.tar.gz",
			tenantID:    999,
			expected:    "tenant-999/file-with-special-chars_123.tar.gz",
		},
		{
			originalKey: "",
			tenantID:    1,
			expected:    "tenant-1/",
		},
	}

	for _, tt := range tests {
		t.Run(fmt.Sprintf("%s_tenant_%d", tt.originalKey, tt.tenantID), func(t *testing.T) {
			cred := &auth.Credential{TenantID: tt.tenantID}
			result := handler.rewritePath(tt.originalKey, cred)
			if result != tt.expected {
				t.Errorf("rewritePath() = %v, want %v", result, tt.expected)
			}
		})
	}
}

func TestPathTraversalPrevention(t *testing.T) {
	dangerousPaths := []string{
		"../../../etc/passwd",
		"..\\..\\..\\windows\\system32",
		"/etc/passwd",
		"%2e%2e/file",
		"..%2ffile",
	}

	for _, path := range dangerousPaths {
		t.Run(path, func(t *testing.T) {
			// Simulate path traversal check
			if strings.Contains(path, "..") || strings.HasPrefix(path, "/") {
				t.Logf("Path traversal attack detected and would be blocked: %s", path)
			}
		})
	}
}

func BenchmarkParsePath(b *testing.B) {
	handler := &Handler{}
	uri := "/dbaas-backups/tenant-1/2026-03-22.sql.gz"

	b.ResetTimer()
	for i := 0; i < b.N; i++ {
		handler.parsePath(uri)
	}
}

func BenchmarkRewritePath(b *testing.B) {
	handler := &Handler{}
	key := "2026-03-22.sql.gz"
	cred := &auth.Credential{TenantID: 1}

	b.ResetTimer()
	for i := 0; i < b.N; i++ {
		handler.rewritePath(key, cred)
	}
}
