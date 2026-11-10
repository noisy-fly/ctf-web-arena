package main

import "testing"

func TestWSLNames(t *testing.T) {
	b := []byte{0xff, 0xfe, 'a', 0, 'b', 0, 13, 0, 10, 0, 'c', 0, 13, 0, 10, 0}
	names := distributionNames(b)
	if len(names) != 2 || names[0] != "ab" || names[1] != "c" {
		t.Fatal(names)
	}
}
