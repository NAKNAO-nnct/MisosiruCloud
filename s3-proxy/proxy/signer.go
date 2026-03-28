package proxy

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"io"
	"net/http"
	"sort"
	"strings"
	"time"
)

// Signer signs requests for the backend S3 service using AWS Signature V4
type Signer struct {
	backendAccessKey string
	backendSecretKey string
	backendRegion    string
}

// NewSigner creates a new signer for backend S3 requests
func NewSigner(accessKey, secretKey, region string) *Signer {
	return &Signer{
		backendAccessKey: accessKey,
		backendSecretKey: secretKey,
		backendRegion:    region,
	}
}

// Sign adds AWS Signature V4 to the request using backend credentials
func (s *Signer) Sign(r *http.Request) error {
	// Get request body
	bodyBytes, err := io.ReadAll(r.Body)
	if err != nil {
		return fmt.Errorf("failed to read request body: %w", err)
	}
	// Restore body for the actual request after signing
	r.Body = io.NopCloser(strings.NewReader(string(bodyBytes)))

	// Calculate payload hash
	payloadHash := sha256.Sum256(bodyBytes)
	payloadHashStr := hex.EncodeToString(payloadHash[:])

	// Determine request date
	amzDate := time.Now().UTC().Format("20060102T150405Z")
	dateStr := amzDate[:8]

	// Get host from request URL (outgoing requests use URL, not Host)
	host := r.URL.Host
	if host == "" {
		host = r.Header.Get("Host")
	}

	// Set required headers BEFORE building canonical request
	r.Header.Set("X-Amz-Date", amzDate)
	r.Header.Set("X-Amz-Content-Sha256", payloadHashStr)

	// Build canonical request
	canonicalReq := s.buildCanonicalRequest(r, payloadHashStr, host, amzDate)

	// Compute string-to-sign
	canonicalReqHash := sha256.Sum256([]byte(canonicalReq))
	canonicalReqHashStr := hex.EncodeToString(canonicalReqHash[:])
	credentialScope := fmt.Sprintf("%s/%s/s3/aws4_request", dateStr, s.backendRegion)
	stringToSign := fmt.Sprintf("AWS4-HMAC-SHA256\n%s\n%s\n%s",
		amzDate, credentialScope, canonicalReqHashStr)

	// Derive signing key
	kSecret := fmt.Sprintf("AWS4%s", s.backendSecretKey)
	kDate := hmacSHA256([]byte(kSecret), []byte(dateStr))
	kRegion := hmacSHA256(kDate, []byte(s.backendRegion))
	kService := hmacSHA256(kRegion, []byte("s3"))
	kSigning := hmacSHA256(kService, []byte("aws4_request"))

	// Sign string-to-sign
	sigBytes := hmacSHA256(kSigning, []byte(stringToSign))
	calculatedSig := hex.EncodeToString(sigBytes)

	// Build authorization header
	authHeader := fmt.Sprintf("AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=host;x-amz-content-sha256;x-amz-date, Signature=%s",
		s.backendAccessKey, credentialScope, calculatedSig)

	r.Header.Set("Authorization", authHeader)

	return nil
}

// buildCanonicalRequest builds the canonical request for signing
func (s *Signer) buildCanonicalRequest(r *http.Request, payloadHash, host, amzDate string) string {
	method := r.Method

	// CanonicalURI: use r.URL.Path (RequestURI is empty for outgoing client requests)
	path := r.URL.Path
	if path == "" {
		path = "/"
	}

	// CanonicalQueryString
	queryString := r.URL.RawQuery

	// CanonicalHeaders — must be sorted alphabetically
	signedHeaders := []string{"host", "x-amz-content-sha256", "x-amz-date"}
	sort.Strings(signedHeaders)

	canonicalHeaders := fmt.Sprintf("host:%s\nx-amz-content-sha256:%s\nx-amz-date:%s\n",
		strings.ToLower(host), payloadHash, amzDate)

	return fmt.Sprintf("%s\n%s\n%s\n%s\n%s\n%s",
		method,
		canonicalURI(path),
		queryString,
		canonicalHeaders,
		strings.Join(signedHeaders, ";"),
		payloadHash,
	)
}

// canonicalURI returns the canonical URI for AWS Signature V4
func canonicalURI(requestURI string) string {
	// Split path and query string
	parts := strings.Split(requestURI, "?")
	path := parts[0]

	// Normalize path: double-encode special characters except /
	// AWS S3 expects specific encoding rules
	var encoded strings.Builder
	for _, ch := range path {
		if ch == '/' {
			encoded.WriteRune(ch)
		} else if isUnreservedChar(ch) {
			encoded.WriteRune(ch)
		} else {
			// Percent-encode
			encoded.WriteString(fmt.Sprintf("%%%02X", ch))
		}
	}

	return encoded.String()
}

// isUnreservedChar checks if character is unreserved in RFC 3986
func isUnreservedChar(ch rune) bool {
	return (ch >= 'A' && ch <= 'Z') ||
		(ch >= 'a' && ch <= 'z') ||
		(ch >= '0' && ch <= '9') ||
		ch == '-' || ch == '_' || ch == '.' || ch == '~'
}

// hmacSHA256 computes HMAC-SHA256
func hmacSHA256(key, data []byte) []byte {
	h := hmac.New(sha256.New, key)
	h.Write(data)
	return h.Sum(nil)
}
