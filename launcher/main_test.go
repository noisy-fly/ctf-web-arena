package main

import "testing"

func TestLocalAddress(t *testing.T) {
	for _, tc := range []struct{ input, want string }{
		{"127.0.0.1:32768\n", "http://127.0.0.1:32768/"},
		{"", ""}, {"0.0.0.0:1234\n", ""}, {"127.0.0.1:invalid", ""},
	} {
		if got := localAddress(tc.input); got != tc.want {
			t.Errorf("%q: got %q, want %q", tc.input, got, tc.want)
		}
	}
}
