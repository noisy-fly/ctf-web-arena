package main

import (
	"fmt"
	"net"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"time"
)

const image = "ctf-web-arena:opensource"
const container = "ctf-web-arena-local"

func localAddress(s string) string {
	host, port, err := net.SplitHostPort(strings.TrimSpace(s))
	if err != nil || host != "127.0.0.1" {
		return ""
	}
	if _, err := net.LookupPort("tcp", port); err != nil {
		return ""
	}
	return "http://" + net.JoinHostPort(host, port) + "/"
}

func docker(args ...string) ([]byte, error) { return exec.Command("docker", args...).CombinedOutput() }
func run() error {
	if out, err := docker("info", "--format", "{{.ServerVersion}}"); err != nil {
		return fmt.Errorf("请先安装并启动 Docker Desktop：%s", strings.TrimSpace(string(out)))
	}
	if _, err := docker("image", "inspect", image); err != nil {
		exe, _ := os.Executable()
		archive := filepath.Join(filepath.Dir(exe), "ctf-web-arena-image.tar.gz")
		if _, err := os.Stat(archive); err != nil {
			return fmt.Errorf("找不到镜像压缩包 %s，请将它放在程序旁边", archive)
		}
		fmt.Println("正在导入镜像，请稍候……")
		if out, err := docker("load", "-i", archive); err != nil {
			return fmt.Errorf("导入镜像失败：%s", out)
		}
	}
	if _, err := docker("inspect", container); err != nil {
		if out, err := docker("run", "-d", "--name", container, "-p", "127.0.0.1::80", "-p", "127.0.0.1::8090", "-p", "127.0.0.1::8307", image); err != nil {
			return fmt.Errorf("创建容器失败：%s", out)
		}
	} else if out, err := docker("start", container); err != nil {
		return fmt.Errorf("启动容器失败：%s", out)
	}
	out, err := docker("port", container, "80/tcp")
	if err != nil {
		return fmt.Errorf("获取端口失败：%s", out)
	}
	url := localAddress(string(out))
	if url == "" {
		return fmt.Errorf("无效的本地端口：%s", out)
	}
	fmt.Println("正在启动靶场：", url)
	client := http.Client{Timeout: 2 * time.Second}
	for i := 0; i < 90; i++ {
		resp, err := client.Get(url + "api/health")
		if err == nil {
			resp.Body.Close()
			if resp.StatusCode == 200 {
				fmt.Println("启动完成，正在打开浏览器。关闭此窗口不会停止靶场。")
				return exec.Command("rundll32", "url.dll,FileProtocolHandler", url).Start()
			}
		}
		time.Sleep(time.Second)
	}
	return fmt.Errorf("启动超时，请运行 docker logs %s 查看日志", container)
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
