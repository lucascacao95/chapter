# AGENTS.md

## 1. Project Overview

This project is a cloud-based personal ebook library initially targeting Brazilian e-reader users.

The initial product focuses on:
- Uploading and organizing personal EPUB and PDF files.
- Extracting ebook metadata and covers.
- Sending supported books to Kindle through Amazon's Send to Kindle email workflow.
- Providing clear delivery status and error feedback.
- Offering a simple, mobile-friendly experience in Brazilian Portuguese.

The product is not an ebook store, DRM removal tool, or replacement for every Calibre feature.

Prioritize a reliable, small product over a broad feature set.

## 2. Product Principles

- Brazilian Portuguese (`pt-BR`) is the primary user-facing language.
- Keep the product simple and accessible to non-technical users.
- Do not promise successful device delivery when only an email or cloud upload has been completed.
- Support only files the user is authorized to upload and process.
- Never attempt to bypass DRM or manufacturer security restrictions.
- Treat Kindle as the first delivery integration, not as the entire product.
- Keep future delivery integrations possible without implementing them prematurely.
- Prefer working, testable features over speculative abstractions.

## 3. Technology Stack

- Backend: PHP and Laravel.
- Frontend: Vue 3, TypeScript, and Inertia.js.
- Styling: Tailwind CSS.
- Database: PostgreSQL.
- Queues: Redis and Laravel Horizon where appropriate.
- File storage: S3-compatible object storage.
- Local development: Docker.
- CI: GitHub Actions.
- Testing: use the testing framework already configured in the repository; prefer Pest for new tests if the project uses it.

Go may be introduced later as an independent book-processing worker. Do not create Go services before the Laravel-based product flow is functional and there is a concrete reason to extract processing.

Use the existing dependency versions and project conventions. Do not upgrade major framework versions without an explicit reason.

## 4. Architecture Principles

- Use TDD as our development method.
- Keep Laravel as the primary application and source of truth for business entities.
- Keep controllers thin; move business logic into appropriate application services.
- Use Form Requests or equivalent validation at input boundaries.
- Use authorization policies for user-owned resources.
- Enforce ownership checks on every operation involving books, files, devices, or deliveries.
- Use database transactions for operations requiring atomic database changes.
- Use asynchronous jobs for slow or unreliable external operations.
- Make retryable jobs idempotent where practical.
- Store ebook binaries in object storage, not directly in PostgreSQL.
- Store file metadata and application state in PostgreSQL.
- Keep external delivery integrations behind a small, explicit provider contract when multiple providers become relevant.
- Do not introduce microservices, event buses, additional databases, or infrastructure without a demonstrated requirement.

## 5. File Handling and Security

- Treat uploaded files as untrusted input.
- Validate file type, size, and structural integrity.
- Do not trust filenames, extensions, MIME types, or client-supplied metadata alone.
- Generate storage keys on the server; never use user filenames directly as storage paths.
- Keep original uploads separate from processed artifacts.
- Use private object storage and authorized download mechanisms.
- Never expose permanent public URLs for private books.
- Do not log file contents, credentials, OAuth tokens, or sensitive personal data.
- Protect against cross-user access and insecure file retrieval.
- Set explicit upload and processing limits.

## 6. Frontend Conventions

- Use Vue 3 Composition API and TypeScript.
- Use shadcn-vue both as a reference and as our components library
- Follow the existing Inertia.js patterns in the repository.
- Reuse existing components before introducing new dependencies.
- Keep interfaces responsive and accessible.
- Use Brazilian Portuguese for user-facing labels, errors, and instructions.
- Keep source code identifiers, API contracts, and technical terminology consistent with existing conventions.
- Represent loading, empty, success, and failure states explicitly.
- Do not simulate successful uploads or deliveries in production code.

## 7. External Integrations

- Treat Amazon and other device ecosystems as external dependencies outside our control.
- Do not assume that private APIs are stable or officially supported.
- Kindle delivery must use a supported workflow and document sender authorization requirements.
- Track delivery stages accurately: queued, processing, submitted, failed, and confirmed only when confirmation is actually available.
- Never mark a book as successfully delivered to a device merely because an email was accepted by an SMTP server.
- Keep provider-specific behavior out of unrelated domain logic.

## 8. Testing Requirements

- Add or update tests for meaningful behavior changes.
- Test authorization and isolation between users.
- Test file validation and malformed uploads.
- Test job retries and duplicate processing where relevant.
- Test delivery success and failure paths with mocked external dependencies.
- Never require real Amazon credentials or external services in the standard test suite.
- Run relevant automated tests and static checks before declaring a task complete.
- Report checks that could not be executed; never claim they passed without running them.

## 9. Documentation

- Keep significant product and architectural decisions documented under `/docs`.
- Update relevant Markdown documentation in the same task as a significant feature or architecture change.
- Use short Architecture Decision Records for decisions with meaningful long-term consequences.
- Keep the README accurate for setup, development, testing, and deployment.
- Do not duplicate the entire product specification in this file.

## 10. Working Rules for the Agent

Before making changes:
1. Inspect the relevant source code and existing documentation.
2. Identify applicable repository instructions.
3. Understand existing conventions before introducing new patterns.
4. For significant changes, state a concise implementation plan before editing.

During implementation:
- Prefer small, focused changes.
- Avoid unrelated refactors.
- Do not add dependencies without a clear justification.
- Do not silently change public APIs, database contracts, or major architecture decisions.
- Ask for clarification when an unresolved product decision materially affects the implementation.

After implementation:
1. Run relevant tests, formatting, and static analysis.
2. Review the diff for security, correctness, and unintended scope expansion.
3. Update relevant documentation.
4. Summarize changes, validation results, limitations, and remaining work.

Do not implement the entire roadmap in a single task. Work in small, verifiable increments.

## 11. User collaboration preferences

- Keep each task strictly within the scope the user requested; do not add adjacent features or make unrequested assumptions.
- Ask the user when a material product or implementation decision is unclear instead of guessing.
