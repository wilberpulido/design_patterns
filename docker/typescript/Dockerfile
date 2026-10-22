# Node 22 = the version CI pins with setup-node (.github/workflows/ci.yml).
FROM node:22

# No package.json in the repo, so the tools are installed globally in the image — the same
# command the CI job runs on its VM. ts-node type-checks with the root tsconfig.json (strict).
RUN npm install --global typescript@5 ts-node@10
