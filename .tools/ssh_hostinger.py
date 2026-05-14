"""Quick SSH helper to run a single remote command on Hostinger.

Usage:
  python ssh_hostinger.py "<remote command>"

Password is read from $env:SSH_PASSWORD to avoid logging it.
"""
import os
import sys
import paramiko

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
    _, out, err = cli.exec_command(cmd, timeout=60)
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
