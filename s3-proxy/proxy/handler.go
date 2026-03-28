package proxy

import (
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strings"

	"s3-proxy/auth"
)

// Handler handles S3 proxy requests
type Handler struct {
	verifier         *auth.Verifier
	credStore        *auth.CredentialStore
	backendURL       string
	backendAccessKey string
	backendSecretKey string
	backendRegion    string
	client           *http.Client
}

// NewHandler creates a new handler
func NewHandler(verifier *auth.Verifier, credStore *auth.CredentialStore, backendURL, backendAccessKey, backendSecretKey, backendRegion string) *Handler {
	return &Handler{
		verifier:         verifier,
		credStore:        credStore,
		backendURL:       backendURL,
		backendAccessKey: backendAccessKey,
		backendSecretKey: backendSecretKey,
		backendRegion:    backendRegion,
		client:           &http.Client{},
	}
}

// ServeHTTP handles incoming requests
func (h *Handler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	cred, err := h.verifier.Verify(r)
	if err != nil {
		h.writeError(w, http.StatusForbidden, "InvalidSignature", err.Error())
		return
	}

	bucket, key, err := h.parsePath(r.RequestURI)
	if err != nil || bucket != cred.AllowedBucket {
		h.writeError(w, http.StatusForbidden, "AccessDenied", "Request does not match allowed bucket")
		return
	}

	if strings.Contains(key, "..") || strings.HasPrefix(key, "/") {
		h.writeError(w, http.StatusForbidden, "AccessDenied", "Invalid key path")
		return
	}

	newKey := h.rewritePath(key, cred)
	newQuery := h.rewriteListPrefix(r.URL.RawQuery, cred)

	if err := h.forwardRequest(w, r, bucket, newKey, newQuery, cred); err != nil {
		h.writeError(w, http.StatusInternalServerError, "InternalError", err.Error())
		return
	}
}

// parsePath extracts bucket and key from request URI
func (h *Handler) parsePath(requestURI string) (bucket, key string, err error) {
	path := strings.Split(requestURI, "?")[0]
	parts := strings.SplitN(path, "/", 3)
	if len(parts) < 2 {
		return "", "", fmt.Errorf("invalid path")
	}
	bucket = parts[1]
	if len(parts) == 3 {
		key = parts[2]
	}
	return bucket, key, nil
}

// rewritePath inserts tenant prefix and allowed prefix into the object key.
// Example: "test/file.txt" with allowed_prefix "data/" -> "tenant-1/data/test/file.txt"
func (h *Handler) rewritePath(key string, cred *auth.Credential) string {
	tenantPrefix := fmt.Sprintf("tenant-%d/", cred.TenantID)
	allowedPrefix := cred.AllowedPrefix
	if allowedPrefix != "/" && !strings.HasSuffix(allowedPrefix, "/") {
		allowedPrefix += "/"
	}
	if allowedPrefix == "/" {
		allowedPrefix = ""
	}
	if key == "" {
		return tenantPrefix + allowedPrefix
	}
	return tenantPrefix + allowedPrefix + key
}

// rewriteListPrefix injects the tenant prefix and allowed prefix into the ?prefix= query parameter
// for ListObjectsV2 operations so tenants only see their own objects within their allowed prefix.
func (h *Handler) rewriteListPrefix(rawQuery string, cred *auth.Credential) string {
	q, err := url.ParseQuery(rawQuery)
	if err != nil || q.Get("list-type") == "" {
		return rawQuery
	}
	tenantPrefix := fmt.Sprintf("tenant-%d/", cred.TenantID)
	allowedPrefix := cred.AllowedPrefix
	if allowedPrefix != "/" && !strings.HasSuffix(allowedPrefix, "/") {
		allowedPrefix += "/"
	}
	if allowedPrefix == "/" {
		allowedPrefix = ""
	}
	fullPrefix := tenantPrefix + allowedPrefix
	existing := q.Get("prefix")
	if strings.HasPrefix(existing, fullPrefix) {
		return rawQuery
	}
	q.Set("prefix", fullPrefix+existing)
	return q.Encode()
}

// forwardRequest forwards the request to the backend S3 service
func (h *Handler) forwardRequest(w http.ResponseWriter, r *http.Request, bucket, key, rawQuery string, cred *auth.Credential) error {
	backendReqURL := fmt.Sprintf("%s/%s/%s", h.backendURL, bucket, key)
	if rawQuery != "" {
		backendReqURL += "?" + rawQuery
	}

	backendReq, err := http.NewRequest(r.Method, backendReqURL, r.Body)
	if err != nil {
		return fmt.Errorf("failed to create backend request: %w", err)
	}
	backendReq.ContentLength = r.ContentLength

	for k, values := range r.Header {
		if k == "Authorization" {
			continue
		}
		for _, value := range values {
			backendReq.Header.Add(k, value)
		}
	}
	backendReq.Host = ""
	backendReq.Header.Set("Host", r.Host)

	signer := NewSigner(h.backendAccessKey, h.backendSecretKey, h.backendRegion)
	if err := signer.Sign(backendReq); err != nil {
		return fmt.Errorf("failed to sign backend request: %w", err)
	}

	resp, err := h.client.Do(backendReq)
	if err != nil {
		return fmt.Errorf("failed to forward request: %w", err)
	}
	defer resp.Body.Close()

	for k, values := range resp.Header {
		for _, value := range values {
			w.Header().Add(k, value)
		}
	}
	w.WriteHeader(resp.StatusCode)

	if _, err := io.Copy(w, resp.Body); err != nil {
		return fmt.Errorf("failed to copy response body: %w", err)
	}
	return nil
}

func (h *Handler) writeError(w http.ResponseWriter, status int, code, message string) {
	w.Header().Set("Content-Type", "application/xml")
	w.WriteHeader(status)
	fmt.Fprintf(w, `<?xml version="1.0" encoding="UTF-8"?>
<Error>
  <Code>%s</Code>
  <Message>%s</Message>
</Error>`, code, message)
}
