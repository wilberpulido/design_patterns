# Python 3.13 = the version CI pins with setup-python (.github/workflows/ci.yml).
# slim: Debian without compilers or docs — the examples only use the standard library.
FROM python:3.13-slim

# Same ruff version as CI: without a ruff.toml, the rule set depends on the version.
RUN pip install --no-cache-dir ruff==0.17.1
