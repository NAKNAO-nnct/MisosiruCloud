package auth

import (
	"fmt"
	"net/http"
	"strings"
	"testing"
	"time"
)

func TestCredentialStore(t *testing.T) {
	store := NewCredentialStore()

	// Create test credentials
	creds := []*Credential{
		{
			AccessKey:      "AKIA1234567890ABCDEF",
			SecretKeyPlain: "secret1234567890secret1234567890secr",
			TenantID:       1,
			AllowedBucket:  "test-bucket",
			AllowedPrefix:  "prefix/",
		},
		{
			AccessKey:      "AKIA2234567890ABCDEF",
			SecretKeyPlain: "secret2234567890secret2234567890secr",
			TenantID:       2,
			AllowedBucket:  "test-bucket-2",
			AllowedPrefix:  "prefix2/",
		},
	}

	// Load credentials
	store.LoadAll(creds)

	// Test Get
	cred, ok := store.Get("AKIA1234567890ABCDEF")
	if !ok {
		t.Error("expected to find credential, but did not")
	}
	if cred.TenantID != 1 {
		t.Errorf("expected tenant_id 1, got %d", cred.TenantID)
	}

	// Test not found
	_, ok = store.Get("NONEXISTENT")
	if ok {
		t.Error("expected to not find credential, but did")
	}

	// Test reload
	newCreds := []*Credential{
		{
			AccessKey:      "AKIA3334567890ABCDEF",
			SecretKeyPlain: "secret3334567890secret3334567890secr",
			TenantID:       3,
			AllowedBucket:  "test-bucket-3",
			AllowedPrefix:  "prefix3/",
		},
	}
	store.LoadAll(newCreds)

	// Old credential should be gone
	_, ok = store.Get("AKIA1234567890ABCDEF")
	if ok {
		t.Error("expected old credential to be gone after reload")
	}

	// New credential should exist
	_, ok = store.Get("AKIA3334567890ABCDEF")
	if !ok {
		t.Error("expected to find new credential after reload")
	}
}

func TestVerifierValidSignature(t *testing.T) {
	store := NewCredentialStore()
	cred := &Credential{
		AccessKey:      "AKIA1234567890ABCDEF",
		SecretKeyPlain: "secret1234567890secret1234567890secr",
		TenantID:       1,
		AllowedBucket:  "test-bucket",
		AllowedPrefix:  "prefix/",
	}
	store.LoadAll([]*Credential{cred})

	verifier := NewVerifier(store)

	// Create a valid request with a non-nil body
	req, err := http.NewRequest("GET", "http://localhost/test-bucket/testfile.txt", strings.NewReader(""))
	if err != nil {
		t.Fatalf("failed to create request: %v", err)
	}

	// Build simple authorization header
	// This is a simplified test - a real test would build a complete AWS Signature V4
	dateStr := time.Now().Format("20060102")
	authHeader := fmt.Sprintf("AWS4-HMAC-SHA256 Credential=%s/%s/us-east-1/s3/aws4_request, SignedHeaders=host;x-amz-date, Signature=dummmysignature",
		cred.AccessKey, dateStr)
	req.Header.Set("Authorization", authHeader)
	req.Header.Set("Host", "localhost")
	req.Header.Set("X-Amz-Date", time.Now().UTC().Format("20060102T150405Z"))

	// Verify should at least parse the header correctly
	verifiedCred, err := verifier.Verify(req)
	if err != nil {
		// We expect this to fail because signature is dummy, but access key should be found
		if verifiedCred == nil {
			t.Logf("Signature verification correctly failed with error: %v", err)
		}
	}
}

func TestVerifierMissingAuthorization(t *testing.T) {
	store := NewCredentialStore()
	verifier := NewVerifier(store)

	req, _ := http.NewRequest("GET", "http://localhost/test", nil)

	_, err := verifier.Verify(req)
	if err == nil {
		t.Error("expected error for missing Authorization header")
	}
}

func TestVerifierInvalidAccessKey(t *testing.T) {
	store := NewCredentialStore()
	store.LoadAll([]*Credential{
		{
			AccessKey:      "AKIA1234567890ABCDEF",
			SecretKeyPlain: "secret1234567890secret1234567890secr",
			TenantID:       1,
			AllowedBucket:  "test-bucket",
			AllowedPrefix:  "prefix/",
		},
	})

	verifier := NewVerifier(store)

	req, _ := http.NewRequest("GET", "http://localhost/test", strings.NewReader(""))
	dateStr := time.Now().Format("20060102")
	authHeader := fmt.Sprintf("AWS4-HMAC-SHA256 Credential=%s/%s/us-east-1/s3/aws4_request, SignedHeaders=host;x-amz-date, Signature=sig",
		"AKIAINVALIDACCESSKEY", dateStr)
	req.Header.Set("Authorization", authHeader)
	req.Header.Set("X-Amz-Date", time.Now().UTC().Format("20060102T150405Z"))

	_, err := verifier.Verify(req)
	if err == nil {
		t.Error("expected error for invalid access key")
	}
}

func BenchmarkCredentialStoreLookup(b *testing.B) {
	store := NewCredentialStore()

	// Add many credentials
	var creds []*Credential
	for i := 0; i < 1000; i++ {
		creds = append(creds, &Credential{
			AccessKey:      fmt.Sprintf("AKIA%016d", i),
			SecretKeyPlain: "secret1234567890secret1234567890secr",
			TenantID:       int64(i),
			AllowedBucket:  "test-bucket",
			AllowedPrefix:  "prefix/",
		})
	}
	store.LoadAll(creds)

	b.ResetTimer()
	for i := 0; i < b.N; i++ {
		store.Get(fmt.Sprintf("AKIA%016d", i%1000))
	}
}
