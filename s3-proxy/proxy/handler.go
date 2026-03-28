package proxy

import (
	"fmt"
	"io"
	"net/http"
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
	// Verify credentials
	cred, err := h.verifier.Verify(r)
	if err != nil {
		h.writeError(w, http.StatusForbidden, "InvalidSignature", err.Error())
		return
	}

	// Check if request path matches allowed bucket and prefix
	bucket, key, err := h.parsePath(r.RequestURI)
	if err != nil || bucket != cred.AllowedBucket {
		h.writeError(w, http.StatusForbidden, "AccessDenied", "Request does not match allowed bucket")
		return
	}

	// Check for path traversal attacks
	if strings.Contains(key, "..") || strings.HasPrefix(key, "/") {
		h.writeError(w, http.StatusForbidden, "AccessDenied", "Invalid key path")
		return
	}

	// Check allowed prefix
	if !strings.HasPrefix(key, cred.AllowedPrefix) && cred.AllowedPrefix != "/" {
		h.writeError(w, http.StatusForbidden, "AccessDenied", "Key does not match allowed prefix")
		return
	}

	// Rewrite path with tenant prefix
	newKey := h.rewritePath(key, cred)

	// Forward request to backend S3
	if err := h.forwardRequest(w, r, bucket, newKey, cred); err != nil {
		h.writeError(w, http.StatusInternalServerError, "InternalError", err.Error())
		return
	}
}

// parsePath extracts bucket and key from request URI
func (h *Handler) parsePath(requestURI string) (bucket, key string, err error) {
	// Remove query string
	path := strings.Split(requestURI, "?")[0]

	// Split path: /bucket/key or /bucket/
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

// rewritePath inserts tenant prefix into the object key
func (h *Handler) rewritePath(key string, cred *auth.Credential) string {
	// Insert tenant prefix
	// Example: file.txt -> tenant-1/file.txt
	// Example: dir/file.txt -> tenant-1/dir/file.txt
	tenantPrefix := fmt.Sprintf("tenant-%d", cred.TenantID)
	if key == "" {
		return tenantPrefix + "/"
	}
	return tenantPrefix + "/" + key
}

// forwardRequest forwards the request to the backend S3 service
func (h *Handler) forwardRequest(w http.ResponseWriter, r *http.Request, bucket, key string, cred *auth.Credential) error {
	// Build backend URL
	backendReqURL := fmt.Sprintf("%s/%s/%s", h.backendURL, bucket, key)

	// Preserve query string
	if r.URL.RawQuery != "" {
		backendReqURL += "?" + r.URL.RawQuery
	}

	// Create backend request
	backendReq, err := http.NewRequest(r.Method, backendReqURL, r.Body)
	if err != nil {
		return fmt.Errorf("failed to create backend request: %w", err)
	}

	// Preserve Content-Length so minio doesn't reject with MissingContentLength
	backendReq.ContentLength = r.ContentLength

	// Copy headers from original request (except Authorization)
	for key, values := range r.Header {
		if key == "Authorization" {
			continue
		}
		for _, value := range values {
			backendReq.Header.Add(key, value)
		}
	}

	// Update host
	backendReq.Host = ""
	backendReq.Header.Set("Host", r.Host)

	// Sign request with backend credentials
	signer := NewSigner(h.backendAccessKey, h.backendSecretKey, h.backendRegion)
	if err := signer.Sign(backendReq); err != nil {
		return fmt.Errorf("failed to sign backend request: %w", err)
	}

	// Send request to backend
	resp, err := h.client.Do(backendReq)
	if err != nil {
		return fmt.Errorf("failed to forward request: %w", err)
	}
	defer resp.Body.Close()

	// Copy response headers
	for key, values := range resp.Header {
		for _, value := range values {
			w.Header().Add(key, value)
		}
	}

	// Set status code
	w.WriteHeader(resp.StatusCode)

	// Copy response body
	if _, err := io.Copy(w, resp.Body); err != nil {
		return fmt.Errorf("failed to copy response body: %w", err)
	}

	// Update last used timestamp in database (async, non-blocking)
	go func() {
		// This would update the credential's last_used_at timestamp
		// For now, we'll skip this as it requires access to the database store
	}()

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
