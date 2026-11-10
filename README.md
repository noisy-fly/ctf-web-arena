# CTF Web Arena

面向 Web 安全学习的本地 CTF 靶场，包含题目、解题提示、验证 API 与训练门户。

**仅在可信任的隔离环境中运行。** 本项目有意包含命令执行、文件上传、SSRF 等漏洞；不要绑定公网、映射主机目录或将其当作生产服务。源码和镜像含题目固定答案，线上使用相同数据时，公开后答案将不再保密。

## Docker 版

下载 Release 中的 `ctf-web-arena-image.tar.gz`，执行：

```sh
docker load -i ctf-web-arena-image.tar.gz
docker run -d --name ctf-web-arena-local -p 127.0.0.1:18763:80 -p 127.0.0.1:18090:8090 -p 127.0.0.1:18307:8307 ctf-web-arena:opensource
```

浏览器打开 http://127.0.0.1:18763/。也可直接从源码构建：`docker build -t ctf-web-arena:opensource .`。

## Windows 启动程序（需要 Docker Desktop）

将 `CTF-Web-Arena.exe` 与 `ctf-web-arena-image.tar.gz` 放在同一目录，启动 Docker Desktop，然后双击程序。首次运行自动导入镜像，启动后自动打开本地网页；端口由 Docker 自动分配。停止服务：`docker stop ctf-web-arena-local`；重新双击程序即可继续。程序及镜像仅支持 Windows x64 与 Linux amd64 容器环境。

部分题目使用辅助端口，可用 `docker port ctf-web-arena-local` 查询实际端口。服务运行数据保存在容器中，删除容器会丢失进度。

## 源码与许可证

源码从现有部署镜像恢复，包含运行中的题目代码修正，但不包括历史运行数据库或用户会话。项目代码按 MIT 许可证发布；内含的 Twig 按其 MIT 许可，Smarty 按 LGPL 3.0 或更高版本许可，分别参见对应目录许可证。二进制及题目资源可能包含独立许可，使用时请查阅各自声明。
