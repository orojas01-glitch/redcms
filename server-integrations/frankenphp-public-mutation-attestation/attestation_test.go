package publicmutationattestation

import (
	"bytes"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/caddyserver/caddy/v2"
	"github.com/caddyserver/caddy/v2/caddyconfig/caddyfile"
	"github.com/caddyserver/caddy/v2/modules/caddyhttp"
)

const testKeyEnvironment = "RED_TEST_PUBLIC_MUTATION_INGRESS_HMAC_KEY"

type nextHandler func(http.ResponseWriter, *http.Request) error

func (handler nextHandler) ServeHTTP(
	w http.ResponseWriter,
	r *http.Request,
) error {
	return handler(w, r)
}

var _ caddyhttp.Handler = nextHandler(nil)

func testAttestation(t *testing.T) Attestation {
	t.Helper()
	t.Setenv(testKeyEnvironment, strings.Repeat("a", 64))
	attestation := Attestation{KeyEnvironment: testKeyEnvironment}
	if err := attestation.Provision(caddy.Context{}); err != nil {
		t.Fatalf("provision attestation: %v", err)
	}
	if err := attestation.Validate(); err != nil {
		t.Fatalf("validate attestation: %v", err)
	}
	return attestation
}

func candidateRequest(body string) *http.Request {
	request := httptest.NewRequest(
		http.MethodPost,
		"https://store.example.test/addons/redcms/store-lite/cart-intent",
		strings.NewReader(body),
	)
	request.RequestURI = "/addons/redcms/store-lite/cart-intent"
	request.Header.Set("Origin", "https://store.example.test")
	request.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	request.Header.Set("Cookie", "redcms_public_mutation_subject="+strings.Repeat("b", 64))
	request.Header.Set("X-RED-CMS-CSRF", strings.Repeat("c", 64))
	request.Header.Set("Idempotency-Key", strings.Repeat("d", 64))
	return request
}

func serveAndRead(
	t *testing.T,
	attestation Attestation,
	request *http.Request,
) (string, *httptest.ResponseRecorder) {
	t.Helper()
	recorder := httptest.NewRecorder()
	var downstreamBody string
	err := attestation.ServeHTTP(
		recorder,
		request,
		nextHandler(func(_ http.ResponseWriter, received *http.Request) error {
			body, err := io.ReadAll(received.Body)
			if err != nil {
				return err
			}
			downstreamBody = string(body)
			return nil
		}),
	)
	if err != nil {
		t.Fatalf("serve attestation: %v", err)
	}
	return downstreamBody, recorder
}

func TestAttestationSignsValidCandidateAndPreservesBody(t *testing.T) {
	attestation := testAttestation(t)
	body := "product=SKU-42&quantity=2"
	request := candidateRequest(body)
	request.Header.Set(captureHeaderName, "attacker-capture")
	request.Header.Set(signatureHeaderName, "attacker-signature")

	downstreamBody, recorder := serveAndRead(t, attestation, request)
	token := request.Header.Get(captureHeaderName)
	signature := request.Header.Get(signatureHeaderName)
	payload, valid := verifyToken(token, signature, attestation.key)

	if !valid {
		t.Fatal("the handler did not produce a valid HMAC capture")
	}
	if payload.Method != http.MethodPost ||
		payload.Target != request.RequestURI ||
		payload.BodyBytes != int64(len(body)) ||
		len(payload.BodySHA256) != 64 {
		t.Fatalf("unexpected bounded transport payload: %#v", payload)
	}
	wantHeaders := []HeaderLine{
		{Name: "Origin", Value: "https://store.example.test"},
		{Name: "Content-Type", Value: "application/x-www-form-urlencoded"},
		{Name: "Cookie", Value: "redcms_public_mutation_subject=" + strings.Repeat("b", 64)},
		{Name: "X-RED-CMS-CSRF", Value: strings.Repeat("c", 64)},
		{Name: "Idempotency-Key", Value: strings.Repeat("d", 64)},
	}
	if !sameHeaderLines(payload.Headers, wantHeaders) {
		t.Fatalf("unexpected signed headers: %#v", payload.Headers)
	}
	if downstreamBody != body {
		t.Fatalf("downstream body changed: got %q want %q", downstreamBody, body)
	}
	if recorder.Body.Len() != 0 || len(recorder.Header()) != 0 {
		t.Fatal("the attestation handler must not emit its own response")
	}
	if token == "attacker-capture" || signature == "attacker-signature" {
		t.Fatal("client-supplied internal headers were not replaced")
	}
}

func TestAttestationWithholdsCaptureForAmbiguousOrEncodedRequests(t *testing.T) {
	attestation := testAttestation(t)
	body := "product=SKU-42&quantity=2"
	cases := []struct {
		name    string
		prepare func(*http.Request)
	}{
		{
			name: "duplicate origin",
			prepare: func(request *http.Request) {
				request.Header.Add("Origin", "https://store.example.test")
			},
		},
		{
			name: "duplicate content type",
			prepare: func(request *http.Request) {
				request.Header.Add("Content-Type", "application/x-www-form-urlencoded")
			},
		},
		{
			name: "duplicate cookie",
			prepare: func(request *http.Request) {
				request.Header.Add("Cookie", "another=value")
			},
		},
		{
			name: "duplicate csrf",
			prepare: func(request *http.Request) {
				request.Header.Add("X-RED-CMS-CSRF", strings.Repeat("e", 64))
			},
		},
		{
			name: "duplicate idempotency key",
			prepare: func(request *http.Request) {
				request.Header.Add("Idempotency-Key", strings.Repeat("e", 64))
			},
		},
		{
			name: "content encoding",
			prepare: func(request *http.Request) {
				request.Header.Set("Content-Encoding", "gzip")
			},
		},
		{
			name: "transfer encoding",
			prepare: func(request *http.Request) {
				request.TransferEncoding = []string{"chunked"}
			},
		},
	}

	for _, testCase := range cases {
		t.Run(testCase.name, func(t *testing.T) {
			request := candidateRequest(body)
			request.Header.Set(captureHeaderName, "attacker-capture")
			request.Header.Set(signatureHeaderName, "attacker-signature")
			testCase.prepare(request)

			downstreamBody, _ := serveAndRead(t, attestation, request)
			if request.Header.Get(captureHeaderName) != "" ||
				request.Header.Get(signatureHeaderName) != "" {
				t.Fatal("ambiguous or encoded request retained an internal capture")
			}
			if downstreamBody != body {
				t.Fatalf("downstream body changed: got %q want %q", downstreamBody, body)
			}
		})
	}
}

func TestAttestationWithholdsCaptureForUnknownOrOversizedBody(t *testing.T) {
	attestation := testAttestation(t)
	cases := []struct {
		name    string
		body    string
		prepare func(*http.Request)
	}{
		{
			name: "unknown content length",
			body: "product=SKU-42",
			prepare: func(request *http.Request) {
				request.ContentLength = -1
			},
		},
		{
			name: "oversized content length",
			body: strings.Repeat("x", maxBodyBytes+1),
			prepare: func(request *http.Request) {
				request.ContentLength = maxBodyBytes + 1
			},
		},
	}

	for _, testCase := range cases {
		t.Run(testCase.name, func(t *testing.T) {
			request := candidateRequest(testCase.body)
			testCase.prepare(request)
			downstreamBody, _ := serveAndRead(t, attestation, request)
			if request.Header.Get(captureHeaderName) != "" ||
				request.Header.Get(signatureHeaderName) != "" {
				t.Fatal("unknown or oversized body retained an internal capture")
			}
			if downstreamBody != testCase.body {
				t.Fatalf("downstream body changed: got %d bytes want %d", len(downstreamBody), len(testCase.body))
			}
		})
	}
}

func TestAttestationStripsSpoofedHeadersOnNonCandidateRequest(t *testing.T) {
	attestation := testAttestation(t)
	request := httptest.NewRequest(http.MethodGet, "https://store.example.test/", nil)
	request.RequestURI = "/"
	request.Header.Set(captureHeaderName, "attacker-capture")
	request.Header.Set(signatureHeaderName, "attacker-signature")
	request.Header.Set("X-Unaffected", "value")

	_, recorder := serveAndRead(t, attestation, request)
	if request.Header.Get(captureHeaderName) != "" ||
		request.Header.Get(signatureHeaderName) != "" {
		t.Fatal("non-candidate request retained spoofed internal headers")
	}
	if request.Header.Get("X-Unaffected") != "value" {
		t.Fatal("non-candidate request changed an unrelated client header")
	}
	if recorder.Body.Len() != 0 || len(recorder.Header()) != 0 {
		t.Fatal("the attestation handler must not emit a response for non-candidates")
	}
}

func TestAttestationRejectsInvalidEnvironmentConfiguration(t *testing.T) {
	cases := []struct {
		name        string
		environment string
		value       string
	}{
		{
			name:        "invalid environment name",
			environment: "invalid-name",
			value:       strings.Repeat("a", 64),
		},
		{
			name:        "missing environment",
			environment: "RED_TEST_MISSING_INGRESS_HMAC_KEY",
			value:       "",
		},
		{
			name:        "invalid key value",
			environment: "RED_TEST_INVALID_INGRESS_HMAC_KEY",
			value:       strings.Repeat("A", 64),
		},
	}

	for _, testCase := range cases {
		t.Run(testCase.name, func(t *testing.T) {
			if testCase.value != "" {
				t.Setenv(testCase.environment, testCase.value)
			}
			attestation := Attestation{KeyEnvironment: testCase.environment}
			if err := attestation.Provision(caddy.Context{}); err == nil {
				t.Fatal("invalid environment configuration was accepted")
			}
		})
	}
}

func TestAttestationCaddyfileSyntax(t *testing.T) {
	valid := Attestation{}
	if err := valid.UnmarshalCaddyfile(
		caddyfile.NewTestDispenser(
			"red_public_mutation_attestation RED_TEST_PUBLIC_MUTATION_INGRESS_HMAC_KEY",
		),
	); err != nil {
		t.Fatalf("valid directive was rejected: %v", err)
	}
	if valid.KeyEnvironment != testKeyEnvironment {
		t.Fatalf("unexpected Caddyfile environment name: %q", valid.KeyEnvironment)
	}

	for _, input := range []string{
		"red_public_mutation_attestation",
		"red_public_mutation_attestation RED_TEST_PUBLIC_MUTATION_INGRESS_HMAC_KEY extra",
		"red_public_mutation_attestation RED_TEST_PUBLIC_MUTATION_INGRESS_HMAC_KEY { unexpected }",
	} {
		invalid := Attestation{}
		if err := invalid.UnmarshalCaddyfile(caddyfile.NewTestDispenser(input)); err == nil {
			t.Fatalf("invalid directive was accepted: %q", input)
		}
	}
}

func sameHeaderLines(got, want []HeaderLine) bool {
	if len(got) != len(want) {
		return false
	}
	for index := range got {
		if got[index] != want[index] {
			return false
		}
	}
	return true
}

func TestAttestationKeepsPHPVerifierFixtureCompatible(t *testing.T) {
	attestation := testAttestation(t)
	request := httptest.NewRequest(
		http.MethodPost,
		"https://x.test/addons/redcms/fixture",
		strings.NewReader("x=1"),
	)
	request.RequestURI = "/addons/redcms/fixture"
	request.Header.Set("Origin", "https://x.test")
	request.Header.Set("Content-Type", "text/plain")
	request.Header.Set("Cookie", "x")
	request.Header.Set("X-RED-CMS-CSRF", "x")
	request.Header.Set("Idempotency-Key", "x")

	downstreamBody, _ := serveAndRead(t, attestation, request)
	if downstreamBody != "x=1" {
		t.Fatalf("fixture body changed: got %q", downstreamBody)
	}
	if got := request.Header.Get(captureHeaderName); got != "v1.eyJ2IjoxLCJtZXRob2QiOiJQT1NUIiwidGFyZ2V0IjoiL2FkZG9ucy9yZWRjbXMvZml4dHVyZSIsImJvZHlCeXRlcyI6MywiYm9keVNoYTI1NiI6IjFmMjA2YjExYzIzZTI4Y2MyNTBkZWQ3ZmMwMDk4ZDM4MjNhODQ2N2E1NDM0MGYxYWM0ZTUzNWNiODU0NDQ5M2YiLCJoZWFkZXJzIjpbeyJuYW1lIjoiT3JpZ2luIiwidmFsdWUiOiJodHRwczovL3gudGVzdCJ9LHsibmFtZSI6IkNvbnRlbnQtVHlwZSIsInZhbHVlIjoidGV4dC9wbGFpbiJ9LHsibmFtZSI6IkNvb2tpZSIsInZhbHVlIjoieCJ9LHsibmFtZSI6IlgtUkVELUNNUy1DU1JGIiwidmFsdWUiOiJ4In0seyJuYW1lIjoiSWRlbXBvdGVuY3ktS2V5IiwidmFsdWUiOiJ4In1dfQ" {
		t.Fatalf("Caddy token no longer matches the PHP verifier fixture: %q", got)
	}
	if got := request.Header.Get(signatureHeaderName); got != "sha256=443c5357f7da41db33d8d6a1d6b915800c977adab522b50cbb945143bcb0272f" {
		t.Fatalf("Caddy signature no longer matches the PHP verifier fixture: %q", got)
	}
}

func TestVerifyTokenFailsWhenPayloadOrSignatureChanges(t *testing.T) {
	key, valid := decodeKey(strings.Repeat("a", 64))
	if !valid {
		t.Fatal("test key did not decode")
	}
	token := captureTokenPrefix + "eyJ2IjoxLCJtZXRob2QiOiJQT1NUIn0"
	signature := signaturePrefix + sign(token, key)
	if _, valid := verifyToken(token, signature, key); !valid {
		t.Fatal("well-formed signed test token was rejected")
	}
	if _, valid := verifyToken(token+"x", signature, key); valid {
		t.Fatal("changed token was accepted")
	}
	if _, valid := verifyToken(token, signature+"0", key); valid {
		t.Fatal("changed signature was accepted")
	}
	if _, valid := verifyToken(token, signature, bytes.Repeat([]byte{1}, 32)); valid {
		t.Fatal("wrong binary key was accepted")
	}
}
