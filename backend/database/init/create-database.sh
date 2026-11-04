#!/bin/bash
set -e

SQLCMD=/opt/mssql-tools18/bin/sqlcmd
if [ ! -x "$SQLCMD" ]; then
  SQLCMD=/opt/mssql-tools/bin/sqlcmd
fi

echo "Menunggu SQL Server siap..."
for attempt in $(seq 1 30); do
  if "$SQLCMD" -S sqlserver -U sa -P "$MSSQL_SA_PASSWORD" -C -b -Q "SELECT 1" >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

echo "Membuat database konveksi bila belum ada..."
"$SQLCMD" -S sqlserver -U sa -P "$MSSQL_SA_PASSWORD" -C -b -Q \
  "IF DB_ID(N'konveksi') IS NULL CREATE DATABASE konveksi;"

echo "Database konveksi siap."
