package middleware

import (
	"fmt"
	"net/http"
	"sync"

	"golang.org/x/time/rate"
)

// RateLimiter provides per-access-key rate limiting.
type RateLimiter struct {
	mu       sync.Mutex
	limiters map[string]*rate.Limiter
	rps      rate.Limit // requests per second per key
	burst    int        // burst size
}

// NewRateLimiter creates a per-key rate limiter.
// rps: sustained requests per second. burst: maximum burst size.
func NewRateLimiter(rps float64, burst int) *RateLimiter {
	return &RateLimiter{
		limiters: make(map[string]*rate.Limiter),
		rps:      rate.Limit(rps),
		burst:    burst,
	}
}

func (rl *RateLimiter) get(key string) *rate.Limiter {
	rl.mu.Lock()
	defer rl.mu.Unlock()

	if lim, ok := rl.limiters[key]; ok {
		return lim
	}
	lim := rate.NewLimiter(rl.rps, rl.burst)
	rl.limiters[key] = lim
	return lim
}

// Allow reports whether the request for key is allowed.
func (rl *RateLimiter) Allow(key string) bool {
	return rl.get(key).Allow()
}

// Handler returns an HTTP middleware that rate-limits by access key.
// The access key is extracted from the Authorization header
// (AWS4-HMAC-SHA256 Credential=<accessKey>/...).
// Requests that exceed the limit receive 429 Too Many Requests.
func (rl *RateLimiter) Handler(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		key := extractAccessKey(r.Header.Get("Authorization"))
		if key == "" {
			// No key means unauthenticated — let the auth middleware handle it
			next.ServeHTTP(w, r)
			return
		}

		if !rl.Allow(key) {
			w.Header().Set("Content-Type", "application/xml")
			w.WriteHeader(http.StatusTooManyRequests)
			fmt.Fprintf(w, `<?xml version="1.0" encoding="UTF-8"?>
<Error>
  <Code>SlowDown</Code>
  <Message>Please reduce your request rate.</Message>
</Error>`)
			return
		}

		next.ServeHTTP(w, r)
	})
}

// extractAccessKey parses the access key from an AWS4-HMAC-SHA256 Authorization header.
// Returns empty string if the header is absent or malformed.
func extractAccessKey(authHeader string) string {
	// Format: AWS4-HMAC-SHA256 Credential=<accessKey>/date/region/service/aws4_request, ...
	const prefix = "Credential="
	idx := len("AWS4-HMAC-SHA256 ")
	if len(authHeader) <= idx {
		return ""
	}
	rest := authHeader[idx:]

	for _, part := range splitComma(rest) {
		if len(part) > len(prefix) && part[:len(prefix)] == prefix {
			cred := part[len(prefix):]
			slashIdx := 0
			for i, ch := range cred {
				if ch == '/' {
					slashIdx = i
					break
				}
			}
			if slashIdx > 0 {
				return cred[:slashIdx]
			}
		}
	}
	return ""
}

func splitComma(s string) []string {
	var parts []string
	for _, p := range splitOn(s, ',') {
		parts = append(parts, trimSpace(p))
	}
	return parts
}

func splitOn(s string, sep byte) []string {
	var parts []string
	start := 0
	for i := 0; i < len(s); i++ {
		if s[i] == sep {
			parts = append(parts, s[start:i])
			start = i + 1
		}
	}
	parts = append(parts, s[start:])
	return parts
}

func trimSpace(s string) string {
	for len(s) > 0 && (s[0] == ' ' || s[0] == '\t') {
		s = s[1:]
	}
	for len(s) > 0 && (s[len(s)-1] == ' ' || s[len(s)-1] == '\t') {
		s = s[:len(s)-1]
	}
	return s
}
