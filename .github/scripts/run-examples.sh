#!/usr/bin/env bash
# Runs every <category>/<pattern>/<file> from its own folder and reports ALL failures,
# instead of stopping at the first one (a plain loop would also hide failures:
# without set -e its exit code is only the LAST command's).
#
# Usage: run-examples.sh <file-name> <command...>
#   run-examples.sh example.py python
#   run-examples.sh example.go go run
set -u

file="$1"
shift

failed=0
for path in */*/"$file"; do
  echo "::group::$path"
  if (cd "$(dirname "$path")" && "$@" "$file"); then
    echo "OK: $path"
  else
    # ::error:: is a GitHub "workflow command": it becomes an annotation on the run/PR.
    echo "::error file=$path::$path exited with a non-zero code"
    failed=1
  fi
  echo "::endgroup::"
done

exit "$failed"
