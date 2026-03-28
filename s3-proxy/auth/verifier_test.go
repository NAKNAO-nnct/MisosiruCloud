package auth_test

import (
"bytes"
"crypto/hmac"
"crypto/sha256"
"encoding/hex"
"fmt"
"net/http"
"net/http/httptest"
"strings"
"testing"
"time"

"s3-proxy/auth"
)

func hmacSHA256(key, data []byte) []byte {
h := hmac.New(sha256.New, key)
h.Write(data)
return h.Sum(nil)
}

func signingKey(secretKey, date, region string) []byte {
kDate := hmacSHA256([]byte("AWS4"+secretKey), []byte(date))
kRegion := hmacSHA256(kDate, []byte(region))
kService := hmacSHA256(kRegion, []byte("s3"))
return hmacSHA256(kService, []byte("aws4_request"))
}

// signRequest builds a properly signed AWS Signature V4 request.
func signRequest(t *testing.T, method, host, path, body, accessKey, secretKey, region string) *http.Request {
t.Helper()

bodyBytes := []byte(body)
payloadHash := sha256.Sum256(bodyBytes)
payloadHashStr := hex.EncodeToString(payloadHash[:])

now := time.Now().UTC()
amzDate := now.Format("20060102T150405Z")
dateStr := now.Format("20060102")

req := httptest.NewRequest(method, path, bytes.NewReader(bodyBytes))
req.Host = host
req.Header.Set("X-Amz-Date", amzDate)
req.Header.Set("X-Amz-Content-Sha256", payloadHashStr)

signedHeaders := "host;x-amz-content-sha256;x-amz-date"
canonicalHeaders := fmt.Sprintf("host:%s\nx-amz-content-sha256:%s\nx-amz-date:%s\n",
host, payloadHashStr, amzDate)

canonicalReq := strings.Join([]string{
method,
path,
"",
canonicalHeaders,
signedHeaders,
payloadHashStr,
}, "\n")

canonicalReqHash := sha256.Sum256([]byte(canonicalReq))
credentialScope := fmt.Sprintf("%s/%s/s3/aws4_request", dateStr, region)
stringToSign := fmt.Sprintf("AWS4-HMAC-SHA256\n%s\n%s\n%s",
amzDate, credentialScope, hex.EncodeToString(canonicalReqHash[:]))

kSigning := signingKey(secretKey, dateStr, region)
sig := hmacSHA256(kSigning, []byte(stringToSign))

authHeader := fmt.Sprintf("AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s",
accessKey, credentialScope, signedHeaders, hex.EncodeToString(sig))
req.Header.Set("Authorization", authHeader)

return req
}

func newVerifier(accessKey, secretKey string, tenantID int64) *auth.Verifier {
cs := auth.NewCredentialStore()
cred := &auth.Credential{
ID:             1,
TenantID:       tenantID,
AccessKey:      accessKey,
SecretKeyPlain: secretKey,
AllowedBucket:  "dbaas-backups",
AllowedPrefix:  "test/",
IsActive:       true,
}
cs.LoadAll([]*auth.Credential{cred})
return auth.NewVerifier(cs)
}

func TestVerify_ValidSignature(t *testing.T) {
const (
accessKey = "AKIATEST1234567890AB"
secretKey = "supersecretkey1234567890123456789012345"
region    = "us-east-1"
)
verifier := newVerifier(accessKey, secretKey, 42)

req := signRequest(t, http.MethodPut, "localhost:9000", "/dbaas-backups/test/file.txt",
"hello world", accessKey, secretKey, region)

cred, err := verifier.Verify(req)
if err != nil {
t.Fatalf("expected no error, got: %v", err)
}
if cred.AccessKey != accessKey {
t.Errorf("expected access key %q, got %q", accessKey, cred.AccessKey)
}
}

func TestVerify_MissingAuthorizationHeader(t *testing.T) {
verifier := newVerifier("AKIATEST1234567890AB", "secret", 1)
req := httptest.NewRequest(http.MethodGet, "/bucket/key", nil)

_, err := verifier.Verify(req)
if err == nil {
t.Fatal("expected error for missing Authorization header")
}
}

func TestVerify_UnknownAccessKey(t *testing.T) {
const (
accessKey = "AKIATEST1234567890AB"
secretKey = "supersecretkey1234567890123456789012345"
region    = "us-east-1"
)
verifier := newVerifier("AKIADIFFERENTKEY0000", "othersecret", 1)

req := signRequest(t, http.MethodGet, "localhost:9000", "/bucket/key",
"", accessKey, secretKey, region)

_, err := verifier.Verify(req)
if err == nil {
t.Fatal("expected error for unknown access key")
}
}

func TestVerify_WrongSecretKey(t *testing.T) {
const (
accessKey = "AKIATEST1234567890AB"
region    = "us-east-1"
)
verifier := newVerifier(accessKey, "correctsecretkey1234567890123456789012", 1)

req := signRequest(t, http.MethodPut, "localhost:9000", "/dbaas-backups/test/file.txt",
"payload", accessKey, "wrongsecretkey_1234567890123456789012", region)

_, err := verifier.Verify(req)
if err == nil {
t.Fatal("expected signature mismatch error")
}
}

func TestVerify_UnsupportedAlgorithm(t *testing.T) {
verifier := newVerifier("key", "secret", 1)
req := httptest.NewRequest(http.MethodGet, "/bucket/key", nil)
req.Header.Set("Authorization", "Bearer sometoken")

_, err := verifier.Verify(req)
if err == nil {
t.Fatal("expected error for unsupported algorithm")
}
if !strings.Contains(err.Error(), "unsupported algorithm") {
t.Errorf("unexpected error: %v", err)
}
}

func TestVerify_MalformedAuthorizationHeader(t *testing.T) {
verifier := newVerifier("key", "secret", 1)
req := httptest.NewRequest(http.MethodGet, "/bucket/key", nil)
req.Header.Set("Authorization", "AWS4-HMAC-SHA256 noequalssign")

_, err := verifier.Verify(req)
if err == nil {
t.Fatal("expected error for malformed Authorization header")
}
}
