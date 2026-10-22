#!/usr/bin/env bash
# Compiles one example.java and runs the class that declares main().
#
# Why not `java example.java`? The single-file launcher runs the FIRST top-level
# class in the file; in several examples main() lives in a later class, so the
# launcher fails with "can't find main(String[])" even though the code is fine.
set -euo pipefail

file="$1"
out="$(mktemp -d)"

javac -d "$out" "$file"

# Last top-level class declared before the line with main().
main_class="$(awk '
  /^(public )?(final )?(abstract )?class [A-Za-z_]+/ { match($0, /class [A-Za-z_]+/); c = substr($0, RSTART + 6, RLENGTH - 6) }
  /static void main *\(/ { print c; exit }
' "$file")"

java -cp "$out" "$main_class"
