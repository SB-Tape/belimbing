# Belimbing (BLB)

An open-source framework where AI is a first-class citizen. Build, customize, and own your operational systems — ERP, CRM, HR, logistics, or any custom process — with a team of AI agents working alongside you.

Everyone can vibe code today, but it takes an expert to vibe engineer a production-grade business system. Belimbing handles the complexity so you can focus on what your business actually needs — robust, secure, and exactly right.

## How It Works

Meet **Lara**, the built-in system agent. She guides setup, explains features, troubleshoots issues, and orchestrates work across your AI team. Connect any LLM provider to activate her.

AI agents in Belimbing are **employees** — managed through the same workforce model as humans. Assign them supervisors, roles, and permissions. They follow your org structure, respect delegation rules, and can never exceed their supervisor's authority. Every action is auditable.

Belimbing ships with foundational modules (Company, Employee, User, AI) and a structured, convention-driven codebase designed for AI agents to extend. Use your favorite coding agent, ask Lara to build what you need, or both.

## Why Belimbing

- **Self-hosted, open source forever** — Your code, your data, your infrastructure. No vendor lock-in, no per-seat fees.
- **Bring your own model** — OpenAI, Anthropic, Google, Ollama, or any compatible endpoint. Mix providers across agents. Ordered fallback built in.
- **Real tools, real guardrails** — Agents can run commands, query data, search the web, navigate the UI, and more. Every action gated by the same authorization system that governs human users.
- **Built on Laravel** — PHP 8.2+, PostgreSQL, Livewire, Tailwind CSS, Alpine.js. Battle-tested stack, massive ecosystem.

## Getting Started

### Prerequisites

- Linux (Ubuntu 22.04+, Debian 12+) or WSL2
- 2 GB RAM, 10 GB disk, internet connection

### Quick Install

```bash
git clone https://github.com/BelimbingApp/lara.git belimbing
cd belimbing
./scripts/setup.sh local
./scripts/start-app.sh
```

## Documentation

| Topic | Link |
|-------|------|
| Project vision & principles | [docs/brief.md](./docs/brief.md) |
| Architecture & directory conventions | [docs/architecture/](./docs/architecture/) |
| Development environment setup | [docs/guides/development-setup.md](./docs/guides/development-setup.md) |
| Guides (theming, extensions) | [docs/guides/](./docs/guides/) |
| Module documentation | [docs/modules/](./docs/modules/) |
| Tutorials (Caddy, Vite, Livewire) | [docs/tutorials/](./docs/tutorials/) |

## Contributing

1. Fork the repository
2. Create a feature branch
3. Open a Pull Request

All contributors must agree to the [CLA](./CLA.md).

## License

[GNU Affero General Public License v3.0 (AGPL-3.0)](./LICENSE) — see [LICENSE](./LICENSE) for license terms and [NOTICE](./NOTICE) for third-party attributions.
