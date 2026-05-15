"""Quick SSH helper to run a single remote command on Hostinger.

Usage:
  python ssh_hostinger.py "<remote command>"

Password is read from $env:SSH_PASSWORD to avoid logging it.
"""
import os
import sys
import paramiko

# Force UTF-8 stdout so emojis in remote command output don't crash on Windows cp1252.
try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

HOST = "156.67.74.243"
PORT = 65002
USER = "u433637438"

def main():
    if len(sys.argv) < 2:
        print("usage: ssh_hostinger.py <command>", file=sys.stderr)
        sys.exit(2)
    cmd = sys.argv[1]
    pw = os.environ.get("SSH_PASSWORD") or os.environ.get("SSH_PASS")
    if not pw:
        print("ERROR: set $env:SSH_PASSWORD first", file=sys.stderr)
        sys.exit(2)
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, port=PORT, username=USER, password=pw, timeout=20, look_for_keys=False, allow_agent=False)
    # Per-command timeout from $env:SSH_CMD_TIMEOUT (seconds), default 120s.
    cmd_timeout = int(os.environ.get("SSH_CMD_TIMEOUT", "120"))
    _, out, err = cli.exec_command(cmd, timeout=cmd_timeout)
    so = out.read().decode("utf-8", errors="replace")
    se = err.read().decode("utf-8", errors="replace")
    if so:
        sys.stdout.write(so)
    if se:
        sys.stderr.write("\n--- stderr ---\n")
        sys.stderr.write(se)
    cli.close()

if __name__ == "__main__":
    main()
