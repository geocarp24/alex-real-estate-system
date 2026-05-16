"""One-shot deploy of front-page.php + style.css + functions.php + images/ to Hostinger.

Steps:
1. Backup style.css and functions.php with .bak.20260516 suffix
2. mkdir images/ if missing
3. SFTP upload: front-page.php, style.css, functions.php, images/*.jpg
4. SSH exec: wp litespeed-purge all
5. Print summary
"""
import os
import sys
import time
from pathlib import Path
import paramiko

HOST = "156.67.74.243"
PORT = 65002
USER = "u433637438"
REMOTE_THEME = "/home/u433637438/domains/geocarpentry.com/public_html/wp-content/themes/geo-carpentry-child"
LOCAL_THEME = r"C:\Users\Admin\OneDrive\Documents\Geo-Carpentry-Repo\automation\wordpress\child-theme"
BAK_SUFFIX = ".bak.20260516"
WP_PATH = "/home/u433637438/domains/geocarpentry.com/public_html"


def run_remote(ssh, cmd, label=None):
    label = label or cmd
    stdin, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode("utf-8", "replace").strip()
    err = stderr.read().decode("utf-8", "replace").strip()
    rc = stdout.channel.recv_exit_status()
    status = "OK" if rc == 0 else f"FAIL({rc})"
    print(f"  [{status}] {label}")
    if out:
        for line in out.splitlines()[:6]:
            print(f"        > {line}")
    if err:
        for line in err.splitlines()[:6]:
            print(f"        ! {line}")
    return rc, out, err


def upload(sftp, local_path, remote_path):
    info = sftp.put(local_path, remote_path)
    print(f"  [OK] uploaded {Path(local_path).name} -> {remote_path} ({info.st_size} bytes)")


def ensure_dir(sftp, remote_dir):
    try:
        sftp.stat(remote_dir)
    except IOError:
        sftp.mkdir(remote_dir)
        print(f"  [OK] mkdir {remote_dir}")


def main():
    pw = os.environ.get("SSH_PASSWORD") or os.environ.get("SSH_PASS")
    if not pw:
        print("ERROR: set $env:SSH_PASSWORD first", file=sys.stderr)
        sys.exit(2)

    print(f"[deploy] connecting to {USER}@{HOST}:{PORT} ...")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, port=PORT, username=USER, password=pw, timeout=30)
    sftp = ssh.open_sftp()

    print("\n[1/5] backup existing files on server")
    for fname in ["style.css", "functions.php"]:
        run_remote(ssh, f"cd {REMOTE_THEME} && [ -f {fname} ] && cp -n {fname} {fname}{BAK_SUFFIX} && echo backup-ok || echo no-source", f"backup {fname}")

    print("\n[2/5] ensure images/ directory exists")
    ensure_dir(sftp, f"{REMOTE_THEME}/images")

    print("\n[3/5] upload theme files")
    upload(sftp, f"{LOCAL_THEME}/front-page.php",  f"{REMOTE_THEME}/front-page.php")
    upload(sftp, f"{LOCAL_THEME}/style.css",       f"{REMOTE_THEME}/style.css")
    upload(sftp, f"{LOCAL_THEME}/functions.php",   f"{REMOTE_THEME}/functions.php")

    print("\n[4/5] upload images")
    img_dir = Path(LOCAL_THEME) / "images"
    for img in sorted(img_dir.glob("*.jpg")):
        upload(sftp, str(img), f"{REMOTE_THEME}/images/{img.name}")

    print("\n[5/5] purge LiteSpeed cache")
    # Try wp-cli first (preferred). If not in PATH, try common paths.
    rc, out, err = run_remote(ssh, f"cd {WP_PATH} && wp litespeed-purge all 2>&1", "wp litespeed-purge all")
    if rc != 0:
        run_remote(ssh, f"cd {WP_PATH} && php /usr/local/bin/wp litespeed-purge all 2>&1 || true", "fallback wp-cli")

    sftp.close()
    ssh.close()
    print("\n[deploy] DONE.")


if __name__ == "__main__":
    main()
