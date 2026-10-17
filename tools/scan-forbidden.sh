#!/usr/bin/env bash
# Pemindai pola terlarang Lentera (Blueprint G.2 job 2).
# Gagal (exit 1) bila menemukan:
#  1. Pemanggilan fungsi bc* (bcmath DILARANG — Blueprint A.1).
#  2. Pemakaian float untuk uang di layer Service modul (heuristik awal;
#     daftar pola diperketat di ArchitectureTest).
set -u
fail=0

echo "== Pindai 1: fungsi bc* =="
if grep -rnE '\b(bcadd|bcsub|bcmul|bcdiv|bccomp|bcmod|bcpow|bcsqrt|bcscale|bcround|bcdivmod)\s*\(' app routes config database --include='*.php' 2>/dev/null; then
  echo "DITOLAK: ditemukan pemanggilan bc* (bcmath dilarang)."
  fail=1
else
  echo "bersih."
fi

echo "== Pindai 2: float untuk uang di Services =="
if grep -rnE '(float)\s*\$[A-Za-z_]*(amount|price|total|balance|money|harga|saldo|bayar)' app/Modules --include='*.php' 2>/dev/null; then
  echo "DITOLAK: dugaan pemakaian float untuk uang. Gunakan App\\Support\\Money."
  fail=1
else
  echo "bersih."
fi

exit $fail
