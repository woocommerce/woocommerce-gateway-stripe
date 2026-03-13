# Linear → GitHub webhook relay

A tiny Cloudflare Worker that bridges Linear webhooks to the GitHub Actions
`repository_dispatch` API.

## How it works

```
Linear issue labelled "create-github-issue"
        │
        ▼
Cloudflare Worker  ← verifies HMAC-SHA256 signature
        │
        ▼  POST /repos/.../dispatches  { event_type: "linear_label_added" }
GitHub Actions workflow: create-github-issue-from-linear.yml
        │
        ├─ searches for existing GitHub issue with same title
        ├─ creates GitHub issue (if none found)
        └─ comments back on the Linear issue with the GitHub issue URL
```

## Setup

### 1. Deploy the Worker

```bash
npm install -g wrangler
cd docs/linear-webhook-relay
wrangler secret put LINEAR_WEBHOOK_SECRET   # from Linear › Settings › API › Webhooks
wrangler secret put GITHUB_PAT              # GitHub PAT with `repo` scope
wrangler deploy
```

The deploy command prints your Worker URL, e.g.:
`https://linear-webhook-relay.<your-subdomain>.workers.dev`

### 2. Register the webhook in Linear

1. Go to **Linear › Settings › API › Webhooks › New webhook**.
2. Set the URL to your Worker URL.
3. Under **Data change events**, enable **Issue labels**.
4. Copy the signing secret and use it for `LINEAR_WEBHOOK_SECRET` above.

### 3. Create the label in Linear

Create a label named exactly **`create-github-issue`** (or change
`LINEAR_TRIGGER_LABEL` in `wrangler.toml`).

### 4. Secrets in GitHub Actions

The workflow (`create-github-issue-from-linear.yml`) uses:

| Secret | Where to add |
|---|---|
| `LINEAR_OAUTH_TOKEN` | Org-level secret (already exists) |
| `GITHUB_TOKEN` | Automatically provided by Actions |

No additional GitHub secrets are needed for the workflow itself.
The `GITHUB_PAT` secret lives only in the Cloudflare Worker.

## Local development

```bash
wrangler dev
# Then use a tool like ngrok or https://smee.io to expose localhost to Linear.
```
