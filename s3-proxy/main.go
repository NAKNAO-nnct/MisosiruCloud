package main

import (
"fmt"
"log"
"net/http"
"time"

"s3-proxy/auth"
"s3-proxy/config"
"s3-proxy/middleware"
"s3-proxy/proxy"
"s3-proxy/store"
)

func main() {
cfg, err := config.Load()
if err != nil {
log.Fatalf("failed to load config: %v", err)
}

// Initialize database
dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s",
cfg.DBUser, cfg.DBPassword, cfg.DBHost, cfg.DBPort, cfg.DBName)
dbStore, err := store.New(dsn)
if err != nil {
log.Fatalf("failed to initialize database: %v", err)
}
defer dbStore.Close()

// Load credentials
creds, err := dbStore.LoadActiveCredentials()
if err != nil {
log.Fatalf("failed to load credentials: %v", err)
}
log.Printf("Loaded %d active credentials", len(creds))

// Initialize credential store
credStore := auth.NewCredentialStore()
var credPtrs []*auth.Credential
for i := range creds {
credPtrs = append(credPtrs, &creds[i])
}
credStore.LoadAll(credPtrs)

// Initialize verifier
verifier := auth.NewVerifier(credStore)

// Initialize rate limiter: 100 req/s sustained, burst of 200 per access key
rateLimiter := middleware.NewRateLimiter(100, 200)

// Register routes
http.HandleFunc("/health", healthHandler)
http.Handle("/", accessLog(rateLimiter.Handler(proxy.NewHandler(
verifier,
credStore,
cfg.S3BackendEndpoint,
cfg.S3BackendAccessKey,
cfg.S3BackendSecretKey,
cfg.S3BackendRegion,
))))

// Background refresh of credentials
go refreshCredentialsBackground(dbStore, credStore, cfg.CacheRefreshInterval)

addr := fmt.Sprintf("%s:%s", cfg.ListenAddr, cfg.ListenPort)
log.Printf("Starting S3 proxy on %s", addr)
log.Printf("Backend S3: %s (region: %s)", cfg.S3BackendEndpoint, cfg.S3BackendRegion)

if err := http.ListenAndServe(addr, nil); err != nil {
log.Fatalf("server error: %v", err)
}
}

func healthHandler(w http.ResponseWriter, r *http.Request) {
w.Header().Set("Content-Type", "application/json")
w.WriteHeader(http.StatusOK)
fmt.Fprintf(w, `{"status":"ok"}`)
}

// responseRecorder wraps ResponseWriter to capture the status code
type responseRecorder struct {
http.ResponseWriter
status int
}

func (rr *responseRecorder) WriteHeader(status int) {
rr.status = status
rr.ResponseWriter.WriteHeader(status)
}

// accessLog logs every request with method, path, status, and duration
func accessLog(next http.Handler) http.Handler {
return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
start := time.Now()
rec := &responseRecorder{ResponseWriter: w, status: http.StatusOK}

log.Printf("[access] --> %s %s  headers: Authorization=%q X-Amz-Date=%q Content-Length=%s",
r.Method, r.RequestURI,
r.Header.Get("Authorization"),
r.Header.Get("X-Amz-Date"),
r.Header.Get("Content-Length"),
)

next.ServeHTTP(rec, r)

log.Printf("[access] <-- %s %s  status=%d  duration=%s",
r.Method, r.RequestURI, rec.status, time.Since(start))
})
}

func refreshCredentialsBackground(dbStore *store.Store, credStore *auth.CredentialStore, interval time.Duration) {
ticker := time.NewTicker(interval)
defer ticker.Stop()

for range ticker.C {
creds, err := dbStore.LoadActiveCredentials()
if err != nil {
log.Printf("failed to refresh credentials: %v", err)
continue
}
var credPtrs []*auth.Credential
for i := range creds {
credPtrs = append(credPtrs, &creds[i])
}
credStore.LoadAll(credPtrs)
log.Printf("Refreshed credentials: %d active", len(creds))
}
}
