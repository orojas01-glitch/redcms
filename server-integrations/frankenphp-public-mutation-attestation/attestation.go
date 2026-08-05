// Package publicmutationattestation provides an optional Caddy HTTP handler
// for a future RED-CMS public-mutation dispatcher.
//
// It is deliberately a server integration rather than CMS runtime code. The
// handler is inert unless an operator builds it into FrankenPHP and places it
// before php_server in an explicit Caddy route. It never selects a route,
// writes a response, issues a cookie, or invokes PHP/package code itself.
package publicmutationattestation

import (
	"bytes"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"regexp"
	"strings"

	"github.com/caddyserver/caddy/v2"
	"github.com/caddyserver/caddy/v2/caddyconfig/caddyfile"
	"github.com/caddyserver/caddy/v2/caddyconfig/httpcaddyfile"
	"github.com/caddyserver/caddy/v2/modules/caddyhttp"
)

const (
	moduleID = "http.handlers.red_public_mutation_attestation"

	// The signature protects the complete token string, not reserialized JSON.
	captureTokenPrefix = "v1."
	signaturePrefix    = "sha256="

	// Internal headers are always stripped first so a client cannot inject a
	// value that a later PHP bridge mistakes for an attested value.
	captureHeaderName   = "X-RED-Public-Mutation-Capture"
	signatureHeaderName = "X-RED-Public-Mutation-Signature"

	// These bounds intentionally match or tighten the generic PHP envelope.
	maxRequestTargetBytes = 2048
	maxBodyBytes          = 8192
	maxHeaderValueBytes   = 2048
	maxCaptureHeaderBytes = 6144
)

var environmentNamePattern = regexp.MustCompile(`^[A-Z][A-Z0-9_]{0,79}$`)

var criticalHeaderNames = []string{
	"Origin",
	"Content-Type",
	"Cookie",
	"X-RED-CMS-CSRF",
	"Idempotency-Key",
}

// Attestation is an optional Caddy middleware. KeyEnvironment names the
// process environment variable that contains the 64-lowercase-hex, 256-bit
// HMAC key. The key itself never enters the Caddyfile or JSON configuration.
type Attestation struct {
	KeyEnvironment string `json:"key_environment,omitempty"`

	key []byte
}

// HeaderLine is one fixed security-relevant header that the later PHP bridge
// may hand to the existing explicit-envelope normalizer. Unrelated request
// headers never enter the signed capture.
type HeaderLine struct {
	Name  string `json:"name"`
	Value string `json:"value"`
}

type capturePayload struct {
	V          int          `json:"v"`
	Method     string       `json:"method"`
	Target     string       `json:"target"`
	BodyBytes  int64        `json:"bodyBytes"`
	BodySHA256 string       `json:"bodySha256"`
	Headers    []HeaderLine `json:"headers"`
}

type replayReadCloser struct {
	io.Reader
	closer io.Closer
}

func (body *replayReadCloser) Close() error {
	return body.closer.Close()
}

func init() {
	caddy.RegisterModule(Attestation{})
	httpcaddyfile.RegisterHandlerDirective(
		"red_public_mutation_attestation",
		parseCaddyfile,
	)
}

// CaddyModule returns the Caddy module information.
func (Attestation) CaddyModule() caddy.ModuleInfo {
	return caddy.ModuleInfo{
		ID:  moduleID,
		New: func() caddy.Module { return new(Attestation) },
	}
}

// Provision resolves the operator-owned environment name to a 256-bit HMAC
// key. It intentionally never logs or returns the key material.
func (attestation *Attestation) Provision(_ caddy.Context) error {
	if !environmentNamePattern.MatchString(attestation.KeyEnvironment) {
		return fmt.Errorf(
			"red_public_mutation_attestation requires one uppercase key environment name",
		)
	}
	configuredKey, available := os.LookupEnv(attestation.KeyEnvironment)
	if !available {
		return fmt.Errorf(
			"red_public_mutation_attestation key environment is unavailable",
		)
	}
	key, valid := decodeKey(configuredKey)
	if !valid {
		return fmt.Errorf(
			"red_public_mutation_attestation key must be 64 lowercase hexadecimal characters",
		)
	}
	attestation.key = key
	return nil
}

// Validate confirms that Provision supplied the fixed HMAC key shape.
func (attestation Attestation) Validate() error {
	if len(attestation.key) != sha256.Size {
		return fmt.Errorf("red_public_mutation_attestation key is unavailable")
	}
	return nil
}

// ServeHTTP strips client-supplied internal headers on every request. It then
// conditionally adds a signed bounded capture only for a strict candidate POST
// under /addons/. It always continues to the next handler and writes no
// response itself.
func (attestation Attestation) ServeHTTP(
	w http.ResponseWriter,
	r *http.Request,
	next caddyhttp.Handler,
) error {
	r.Header.Del(captureHeaderName)
	r.Header.Del(signatureHeaderName)

	if attestation.candidate(r) {
		attestation.attest(r)
	}

	return next.ServeHTTP(w, r)
}

func (attestation Attestation) candidate(r *http.Request) bool {
	if len(attestation.key) != sha256.Size || r.Method != http.MethodPost {
		return false
	}
	if r.ContentLength < 0 || r.ContentLength > maxBodyBytes || r.Body == nil {
		return false
	}
	if len(r.TransferEncoding) != 0 ||
		len(r.Header.Values("Transfer-Encoding")) != 0 ||
		len(r.Header.Values("Content-Encoding")) != 0 {
		return false
	}
	target := r.RequestURI
	path, _, _ := strings.Cut(target, "?")
	return len(target) >= len("/addons/") &&
		len(target) <= maxRequestTargetBytes &&
		strings.HasPrefix(path, "/addons/") &&
		!strings.ContainsAny(target, "#\x00\r\n\t ")
}

func (attestation Attestation) attest(r *http.Request) {
	headers, valid := capturedHeaders(r)
	if !valid {
		return
	}

	originalBody := r.Body
	body, err := io.ReadAll(io.LimitReader(originalBody, maxBodyBytes+1))
	if err != nil || int64(len(body)) != r.ContentLength {
		r.Body = &replayReadCloser{
			Reader: io.MultiReader(bytes.NewReader(body), originalBody),
			closer: originalBody,
		}
		return
	}

	r.Body = &replayReadCloser{
		Reader: bytes.NewReader(body),
		closer: originalBody,
	}
	bodyHash := sha256.Sum256(body)
	payloadBytes, err := json.Marshal(capturePayload{
		V:          1,
		Method:     http.MethodPost,
		Target:     r.RequestURI,
		BodyBytes:  int64(len(body)),
		BodySHA256: hex.EncodeToString(bodyHash[:]),
		Headers:    headers,
	})
	if err != nil {
		return
	}
	token := captureTokenPrefix + base64.RawURLEncoding.EncodeToString(payloadBytes)
	r.Header.Set(captureHeaderName, token)
	r.Header.Set(
		signatureHeaderName,
		signaturePrefix+sign(token, attestation.key),
	)
}

func capturedHeaders(r *http.Request) ([]HeaderLine, bool) {
	headers := make([]HeaderLine, 0, len(criticalHeaderNames))
	totalBytes := 0
	for _, name := range criticalHeaderNames {
		values := r.Header.Values(name)
		if len(values) > 1 {
			return nil, false
		}
		if len(values) == 0 {
			continue
		}
		value := values[0]
		if len(value) > maxHeaderValueBytes || containsControl(value) {
			return nil, false
		}
		totalBytes += len(name) + len(value) + 2
		if totalBytes > maxCaptureHeaderBytes {
			return nil, false
		}
		headers = append(headers, HeaderLine{Name: name, Value: value})
	}
	return headers, true
}

func containsControl(value string) bool {
	for index := 0; index < len(value); index++ {
		if value[index] < 0x20 || value[index] == 0x7f {
			return true
		}
	}
	return false
}

func decodeKey(configuredKey string) ([]byte, bool) {
	if len(configuredKey) != sha256.Size*2 {
		return nil, false
	}
	for index := 0; index < len(configuredKey); index++ {
		character := configuredKey[index]
		if !(character >= '0' && character <= '9') &&
			!(character >= 'a' && character <= 'f') {
			return nil, false
		}
	}
	key, err := hex.DecodeString(configuredKey)
	return key, err == nil && len(key) == sha256.Size
}

func sign(token string, key []byte) string {
	mac := hmac.New(sha256.New, key)
	_, _ = mac.Write([]byte(token))
	return hex.EncodeToString(mac.Sum(nil))
}

// verifyToken is intentionally package-private. Tests use it to prove that
// the handler signs the exact token with the configured binary key.
func verifyToken(token, signature string, key []byte) (capturePayload, bool) {
	if len(key) != sha256.Size ||
		!strings.HasPrefix(token, captureTokenPrefix) ||
		!strings.HasPrefix(signature, signaturePrefix) ||
		!hmac.Equal(
			[]byte(signaturePrefix+sign(token, key)),
			[]byte(signature),
		) {
		return capturePayload{}, false
	}
	encoded := strings.TrimPrefix(token, captureTokenPrefix)
	payloadBytes, err := base64.RawURLEncoding.DecodeString(encoded)
	if err != nil {
		return capturePayload{}, false
	}
	var payload capturePayload
	if err := json.Unmarshal(payloadBytes, &payload); err != nil {
		return capturePayload{}, false
	}
	return payload, payload.V == 1
}

// UnmarshalCaddyfile implements caddyfile.Unmarshaler. Syntax:
//
//	red_public_mutation_attestation <HMAC_KEY_ENVIRONMENT_NAME>
//
// The directive must be placed in an explicit route before php_server so its
// position remains deterministic.
func (attestation *Attestation) UnmarshalCaddyfile(
	d *caddyfile.Dispenser,
) error {
	d.Next()
	if !d.NextArg() {
		return d.ArgErr()
	}
	attestation.KeyEnvironment = d.Val()
	if d.NextArg() {
		return d.ArgErr()
	}
	for nesting := d.Nesting(); d.NextBlock(nesting); {
		return d.Errf(
			"red_public_mutation_attestation accepts no subdirectives",
		)
	}
	return nil
}

func parseCaddyfile(
	h httpcaddyfile.Helper,
) (caddyhttp.MiddlewareHandler, error) {
	var attestation Attestation
	err := attestation.UnmarshalCaddyfile(h.Dispenser)
	return &attestation, err
}

var (
	_ caddy.Provisioner           = (*Attestation)(nil)
	_ caddy.Validator             = (*Attestation)(nil)
	_ caddyhttp.MiddlewareHandler = (*Attestation)(nil)
	_ caddyfile.Unmarshaler       = (*Attestation)(nil)
)
