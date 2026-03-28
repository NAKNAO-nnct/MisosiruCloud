package auth

import (
	"bytes"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"io"
	"log"
	"net/http"
	"sort"
	"strings"
)

// Verifier verifies AWS Signature V4 signatures
type Verifier struct {
	credStore *CredentialStore
}

// NewVerifier creates a new verifier
func NewVerifier(credStore *CredentialStore) *Verifier {
	return &Verifier{
		credStore: credStore,
	}
}

// ParsedAuth holds parsed Authorization header fields
type ParsedAuth struct {
	AccessKey       string
	CredentialScope string // date/region/service/aws4_request
	SignedHeaders   []string
	Signature       string
}

// parseAuthHeader parses the AWS Signature V4 Authorization header.
// Format: AWS4-HMAC-SHA256 Credential=key/scope, SignedHeaders=h1;h2, Signature=sig
func parseAuthHeader(authHeader string) (*ParsedAuth, error) {
	// Split algorithm from the rest on the first space only
	idx := strings.IndexByte(authHeader, ' ')
	if idx < 0 {
		return nil, fmt.Errorf("invalid Authorization header: no space found")
	}
	algorithm := authHeader[:idx]
	if algorithm != "AWS4-HMAC-SHA256" {
		return nil, fmt.Errorf("unsupported algorithm: %s", algorithm)
	}

	rest := authHeader[idx+1:]
	parsed := &ParsedAuth{}

	// Split by comma, trimming spaces (e.g. "Credential=..., SignedHeaders=..., Signature=...")
	for _, part := range strings.Split(rest, ",") {
		part = strings.TrimSpace(part)
		switch {
		case strings.HasPrefix(part, "Credential="):
			cred := strings.TrimPrefix(part, "Credential=")
			// cred = access_key/date/region/service/aws4_request
			slashIdx := strings.IndexByte(cred, '/')
			if slashIdx < 0 {
				return nil, fmt.Errorf("invalid Credential field: %s", cred)
			}
			parsed.AccessKey = cred[:slashIdx]
			parsed.CredentialScope = cred[slashIdx+1:]
		case strings.HasPrefix(part, "SignedHeaders="):
			parsed.SignedHeaders = strings.Split(strings.TrimPrefix(part, "SignedHeaders="), ";")
		case strings.HasPrefix(part, "Signature="):
			parsed.Signature = strings.TrimPrefix(part, "Signature=")
		}
	}

	if parsed.AccessKey == "" || parsed.Signature == "" || len(parsed.SignedHeaders) == 0 {
		return nil, fmt.Errorf("Authorization header missing Credential, SignedHeaders, or Signature")
	}
	return parsed, nil
}

// Verify verifies the signature of a request.
// Returns the credential if signature is valid, or an error.
func (v *Verifier) Verify(r *http.Request) (*Credential, error) {
	authHeader := r.Header.Get("Authorization")
	log.Printf("[auth] Authorization: %s", authHeader)
	log.Printf("[auth] X-Amz-Date: %s", r.Header.Get("X-Amz-Date"))
	log.Printf("[auth] X-Amz-Content-Sha256: %s", r.Header.Get("X-Amz-Content-Sha256"))
	log.Printf("[auth] Host: %s", r.Host)

	if authHeader == "" {
		return nil, fmt.Errorf("missing Authorization header")
	}

	parsed, err := parseAuthHeader(authHeader)
	if err != nil {
		log.Printf("[auth] parse error: %v", err)
		return nil, fmt.Errorf("invalid Authorization header format: %w", err)
	}
	log.Printf("[auth] parsed access_key=%s scope=%s signedHeaders=%v", parsed.AccessKey, parsed.CredentialScope, parsed.SignedHeaders)

	cred, ok := v.credStore.Get(parsed.AccessKey)
	if !ok {
		log.Printf("[auth] access_key not found: %s (loaded keys: %v)", parsed.AccessKey, v.credStore.Keys())
		return nil, fmt.Errorf("access_key not found: %s", parsed.AccessKey)
	}

	if err := v.verifySignature(r, cred, parsed); err != nil {
		return nil, err
	}

	return cred, nil
}

func (v *Verifier) verifySignature(r *http.Request, cred *Credential, parsed *ParsedAuth) error {
	// Read body and restore it
	bodyBytes, err := io.ReadAll(r.Body)
	if err != nil {
		return fmt.Errorf("failed to read request body: %w", err)
	}
	r.Body = io.NopCloser(bytes.NewReader(bodyBytes))

	// Use X-Amz-Content-Sha256 if provided, otherwise compute
	payloadHashStr := r.Header.Get("X-Amz-Content-Sha256")
	if payloadHashStr == "" || payloadHashStr == "UNSIGNED-PAYLOAD" {
		hash := sha256.Sum256(bodyBytes)
		payloadHashStr = hex.EncodeToString(hash[:])
	}

	// Get X-Amz-Date (format: 20060102T150405Z)
	xAmzDate := r.Header.Get("X-Amz-Date")
	if xAmzDate == "" {
		return fmt.Errorf("missing X-Amz-Date header")
	}
	if len(xAmzDate) < 8 {
		return fmt.Errorf("invalid X-Amz-Date: %s", xAmzDate)
	}
	dateStr := xAmzDate[:8] // YYYYMMDD

	// Extract region from credential scope: date/region/service/aws4_request
	scopeParts := strings.SplitN(parsed.CredentialScope, "/", 4)
	if len(scopeParts) < 3 {
		return fmt.Errorf("invalid credential scope: %s", parsed.CredentialScope)
	}
	region := scopeParts[1]

	// Build canonical request (AWS SigV4 spec)
	canonicalReq := v.buildCanonicalRequest(r, payloadHashStr, parsed.SignedHeaders)
	canonicalReqHash := sha256.Sum256([]byte(canonicalReq))
	canonicalReqHashStr := hex.EncodeToString(canonicalReqHash[:])

	// Build string to sign
	stringToSign := fmt.Sprintf("AWS4-HMAC-SHA256\n%s\n%s\n%s",
		xAmzDate,
		parsed.CredentialScope,
		canonicalReqHashStr,
	)

	// Build signing key
	kSecret := "AWS4" + cred.SecretKeyPlain
	kDate := hmacSHA256([]byte(kSecret), []byte(dateStr))
	kRegion := hmacSHA256(kDate, []byte(region))
	kService := hmacSHA256(kRegion, []byte("s3"))
	kSigning := hmacSHA256(kService, []byte("aws4_request"))

	sig := hmacSHA256(kSigning, []byte(stringToSign))
	calculatedSig := hex.EncodeToString(sig)

	log.Printf("[auth] canonical_request:\n%s", canonicalReq)
	log.Printf("[auth] string_to_sign:\n%s", stringToSign)
	log.Printf("[auth] calculated_sig=%s expected_sig=%s match=%v",
		calculatedSig, parsed.Signature, calculatedSig == parsed.Signature)

	if calculatedSig != parsed.Signature {
		return fmt.Errorf("signature mismatch: check secret key and canonical request")
	}

	return nil
}

// buildCanonicalRequest builds the AWS SigV4 canonical request string.
// Spec: https://docs.aws.amazon.com/general/latest/gr/sigv4-create-canonical-request.html
func (v *Verifier) buildCanonicalRequest(r *http.Request, payloadHash string, signedHeaders []string) string {
	// CanonicalURI: URL-encoded path (not including query string)
	canonicalURI := r.URL.Path
	if canonicalURI == "" {
		canonicalURI = "/"
	}

	// CanonicalQueryString: sorted query parameters
	canonicalQuery := r.URL.RawQuery

	// CanonicalHeaders: sorted lowercase header:trimmed-value\n
	sort.Strings(signedHeaders)
	var headerLines []string
	for _, h := range signedHeaders {
		var val string
		if strings.ToLower(h) == "host" {
			val = r.Host
		} else {
			val = r.Header.Get(h)
		}
		headerLines = append(headerLines, strings.ToLower(h)+":"+strings.TrimSpace(val))
	}
	// Each header line ends with \n, and there is a blank line after the last header
	canonicalHeaders := strings.Join(headerLines, "\n") + "\n"

	return strings.Join([]string{
		r.Method,
		canonicalURI,
		canonicalQuery,
		canonicalHeaders,
		strings.Join(signedHeaders, ";"),
		payloadHash,
	}, "\n")
}

func hmacSHA256(key, data []byte) []byte {
	h := hmac.New(sha256.New, key)
	h.Write(data)
	return h.Sum(nil)
}
