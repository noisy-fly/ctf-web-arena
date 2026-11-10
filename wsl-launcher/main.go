package main

import (
	"compress/gzip"
	"errors"
	"fmt"
	"io"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"time"
	"unicode/utf16"
)

func distributionNames(b []byte) []string {
	if len(b) >= 2 && (b[0] == 0xff && b[1] == 0xfe || b[1] == 0) {
		if b[0] == 0xff && b[1] == 0xfe {
			b = b[2:]
		}
		u := make([]uint16, 0, len(b)/2)
		for i := 0; i+1 < len(b); i += 2 {
			u = append(u, uint16(b[i])|uint16(b[i+1])<<8)
		}
		b = []byte(string(utf16.Decode(u)))
	}
	lines := strings.Split(strings.ReplaceAll(string(b), "\x00", ""), "\n")
	var out []string
	for _, line := range lines {
		if s := strings.TrimSpace(line); s != "" {
			out = append(out, s)
		}
	}
	return out
}
func command(args ...string) ([]byte, error) {
	return exec.Command("wsl.exe", args...).CombinedOutput()
}
func run() error {
	name := "CTFWebArena"
	if len(os.Args) > 1 && os.Args[1] == "--test" {
		name = "CTFWebArenaTest"
	}
	raw, err := command("--list", "--quiet")
	if err != nil {
		return errors.New("无法访问 WSL。请先启用 WSL 2，然后重新启动电脑")
	}
	found := false
	for _, n := range distributionNames(raw) {
		if n == name {
			found = true
		}
	}
	if !found {
		exe, err := os.Executable()
		if err != nil {
			return err
		}
		archive := filepath.Join(filepath.Dir(exe), "ctf-wsl-rootfs.tar.gz")
		src, err := os.Open(archive)
		if err != nil {
			return fmt.Errorf("请将 ctf-wsl-rootfs.tar.gz 放在启动程序旁边：%w", err)
		}
		defer src.Close()
		gz, err := gzip.NewReader(src)
		if err != nil {
			return err
		}
		defer gz.Close()
		tmp, err := os.CreateTemp("", "ctf-wsl-*.tar")
		if err != nil {
			return err
		}
		defer os.Remove(tmp.Name())
		fmt.Println("正在准备 Linux 运行环境，请稍候……")
		if _, err = io.Copy(tmp, gz); err != nil {
			tmp.Close()
			return err
		}
		if err = tmp.Close(); err != nil {
			return err
		}
		base, err := os.UserConfigDir()
		if err != nil {
			return err
		}
		dir := filepath.Join(base, "CTFWebArena")
		if err = os.MkdirAll(dir, 0700); err != nil {
			return err
		}
		fmt.Println("正在安装本地运行环境……")
		if out, err := command("--import", name, dir, tmp.Name(), "--version", "2"); err != nil {
			return fmt.Errorf("WSL 安装失败：%s", string(out))
		}
	}
	fmt.Println("正在启动靶场……")
	if _, err := command("-d", name, "-u", "root", "--exec", "pgrep", "-f", "^/bin/bash /entry.sh$"); err != nil {
		logPath := filepath.Join(os.TempDir(), "ctf-web-arena-wsl.log")
		logFile, err := os.OpenFile(logPath, os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0600)
		if err != nil {
			return err
		}
		defer logFile.Close()
		child := exec.Command("wsl.exe", "-d", name, "-u", "root", "--exec", "/bin/bash", "/entry.sh")
		child.Stdout, child.Stderr = logFile, logFile
		if err := child.Start(); err != nil {
			return fmt.Errorf("启动 WSL 失败：%w", err)
		}
	}

	url := "http://127.0.0.1:18763/"
	client := http.Client{Timeout: 2 * time.Second}
	for i := 0; i < 120; i++ {
		resp, err := client.Get(url + "api/health")
		if err == nil {
			resp.Body.Close()
			if resp.StatusCode == 200 {
				fmt.Println("启动成功：", url)
				return exec.Command("rundll32", "url.dll,FileProtocolHandler", url).Start()
			}
		}
		time.Sleep(time.Second)
	}
	return fmt.Errorf("启动超时。请检查本机端口 18763 是否被占用，或查看临时目录中的 ctf-web-arena-wsl.log")
}
func main() {
	if err := run(); err != nil {
		fmt.Println("启动失败：", err)
		fmt.Println("按回车键退出。")
		fmt.Scanln()
		os.Exit(1)
	}
	time.Sleep(3 * time.Second)
}
