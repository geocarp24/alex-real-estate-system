"""SFTP a local file to Hostinger."""
import os
import sys
import paramiko

HOST = "156.67.74.243"
PORT = 65002
USER = "u433637438"

def main():
    if len(sys.argv) < 3:
        print("usage: sftp_upload.py <local> <remote>", file=sys.stderr)
        sys.exit(2)
    local, remote = sys.argv[1], sys.argv[2]
    pw = os.environ.get("SSH_PASSWORD") or os.environ.get("SSH_PASS")
    if not pw:
        print("ERROR: set $env:SSH_PASSWORD first", file=sys.stderr)
        sys.exit(2)
    t = paramiko.Transport((HOST, PORT))
    t.connect(username=USER, password=pw)
    sftp = paramiko.SFTPClient.from_transport(t)
    # Ensure parent dir exists
    parent = os.path.dirname(remote).rstrip("/")
    if parent:
        parts = parent.split("/")
        cur = ""
        for p in parts:
            cur = (cur + "/" + p) if cur else p
            try:
                sftp.stat(cur)
            except IOError:
                try:
                    sftp.mkdir(cur)
                except IOError:
                    pass
    sftp.put(local, remote)
    info = sftp.stat(remote)
    print(f"uploaded {local} -> {remote} ({info.st_size} bytes)")
    sftp.close()
    t.close()

if __name__ == "__main__":
    main()
