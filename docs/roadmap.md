# Roadmap

This is a living document. Items are not commitments; they reflect what the
maintainers and contributors think would be worth doing next.

## Near term

- Database migration runner so upgrades do not require manual SQL.
- Page-level comments (distinct from issues).
- Per-page RSS feeds.
- Avatar upload from the profile page.
- Word-level diff in the proposal view.
- Reading time estimate on page view.
- Floating table of contents on long pages.

## Medium term

- Webhook and Slack notifications on page, issue, and proposal events.
- Batch import of Markdown files with front matter.
- Page export to Markdown, HTML, and PDF.
- Login attempt rate limiting.
- Search ranking improvements: title weight, recency weight.
- Dark mode toggle with per-user preference.

## Long term

- Right-to-left language support.
- Read-only JSON API expansion.
- Plugin system for custom page types and integrations.
- Multi-tenant mode.
- Federated wiki links across instances.

## Not planned

- A JavaScript framework front end. The project intentionally ships plain
  HTML and small inline scripts.
- A build step. The wiki has to remain deployable by uploading a folder.
- A package manager dependency tree. The project has one vendored library and
  aims to keep it that way.

## How to influence the roadmap

Open an issue describing the problem you are trying to solve. If a maintainer
agrees it fits, they will tag it as `enhancement` and it becomes a candidate
for the next milestone.
