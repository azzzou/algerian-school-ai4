# 🎓 Algerian School Support Platform

A **production-ready, multi-service** platform for an Algerian school-support
(tutoring) business targeting the **BAC** (Baccalauréat) and **BEM** (Brevet)
national exams. It connects a **Meta (Facebook) Messenger bot**, a **CrewAI +
Google Gemini** conversational agent that speaks **Algerian Darija & French**,
and a **Laravel school-management dashboard** — all wired together with a
shared Docker stack and Redis.

---

## ✨ Highlights

- 🤖 **AI Agent (CrewAI + Gemini)** that replies in **Algerian Darija / French**
  with a warm, professional tone, and extracts structured pre-registration data.
- 🔒 **Meta Messenger Gateway (FastAPI)** that **verifies `X-Hub-Signature-256`**
  (HMAC-SHA256) and responds **asynchronously** to avoid webhook timeouts.
- 🏫 **Laravel School Dashboard** (ready-made School Management System).
- 🐳 **Docker Compose** stack wiring Gateway + AI Engine + Dashboard + Redis + MySQL.
- 🧱 **Deterministic JSON output** (`reply_text` + `extracted_info`) from the AI —
  stable contract for downstream systems.

---

## 🗂 Architecture

```
┌─────────────────────────────┐       ┌──────────────────────────────┐
│   Meta Messenger (FB)       │       │  CrewAI AI Engine            │
│   ┌───────────────────────┐ │  HTTP │  ┌────────────────────────┐  │
│   │  Gateway (FastAPI)   │◄┼───────┼──►│  /v1/reply            │  │
│   │  · X-Hub-Signature   │ │       │  └────────────────────────┘  │
│   │  · Async background  │ │       │  AlgerianSupportCrew (crew)  │
│   └──────────┬────────────┘ │       │  Agent (Gemini, Darija)      │
│              │ reply        │       └──────────────────────────────┘
│              ▼              │
│   ┌───────────────────────┐ │
│   │  Redis (cache/queue) │ │
│   └───────────────────────┘ │
└─────────────────────────────┘
              │
              ▼
   ┌───────────────────────┐
   │  Dashboard (Laravel)  │  — school/student/result management
   └───────────────────────┘
```

| Service      | Tech                 | Port    | Purpose                                   |
|--------------|----------------------|---------|-------------------------------------------|
| `gateway`    | FastAPI / Python     | `8001`  | Messenger webhook (verify + async reply)  |
| `ai_engine`  | CrewAI + Gemini      | `8002`  | Darija/French support agent (`/v1/reply`) |
| `redis`      | Redis 7              | `6379`  | cache / queue                             |
| `dashboard`  | Laravel 8 / PHP      | `8080`  | school management dashboard               |
| `db`         | MySQL 8              | `3306`  | dashboard database                        |

---

## 🚀 Quick Start (Docker)

### Prerequisites
- [Docker](https://docs.docker.com/get-docker/) + Docker Compose v2
- A [Google AI Studio](https://aistudio.google.com/apikey) API key (Gemini)
- Meta App credentials (see [Messenger setup](#-meta-messenger-setup))

### 1. Configure environment

```bash
cp .env.example .env
# Fill in at minimum:
#   GOOGLE_API_KEY=...            (AI)
#   MESSENGER_APP_SECRET=...      (Gateway)
#   MESSENGER_VERIFY_TOKEN=...    (Gateway)
#   MESSENGER_PAGE_ACCESS_TOKEN=... (Gateway)
```

### 2. Build & start the whole stack

```bash
docker compose up --build -d
```

### 3. Verify

| Endpoint            | Service    | URL                                   |
|---------------------|------------|---------------------------------------|
| Gateway health      | gateway    | `http://localhost:8001/health`        |
| AI engine health    | ai_engine  | `http://localhost:8002/health`        |
| AI structured reply | ai_engine  | `POST http://localhost:8002/v1/reply` |
| School dashboard    | dashboard  | `http://localhost:8080`               |
| Swagger (Gateway)   | gateway    | `http://localhost:8001/docs`          |
| Swagger (AI)        | ai_engine  | `http://localhost:8002/docs`          |

### 4. Stop

```bash
docker compose down
# optionally reset persistent data
docker compose down -v
```

---

## 💬 AI Engine API

### `POST /v1/reply`

Deterministic structured reply to a student message.

**Request:**
```json
{
  "message": "Saha ana BAC 3AS scientifique, bghit nchrik. Chhal el bois?",
  "conversation_id": "fb_psid_123456"
}
```

**Response (always the same shape):**
```json
{
  "reply_text": "Wa3alik salam! 3andna cours BAC l les 3 bachoun... 2500 DA l madat f chahr...",
  "extracted_info": {
    "name": null,
    "phone": null,
    "level": "BAC 3AS",
    "filiere": "scientifique",
    "subject": null
  }
}
```

### `GET /health`
Liveness probe.

---

## 🔌 Meta Messenger Gateway

### Verification (`GET /webhook`)

Handles `hub.mode`, `hub.verify_token` and `hub.challenge` for the Facebook
webhook subscription handshake.

### Delivery (`POST /webhook`)

1. **Signature verification** — computes `HMAC-SHA256` of the raw body with
   `MESSENGER_APP_SECRET` and compares against `X-Hub-Signature-256` using a
   timing-safe compare. Invalid signatures → `401`.
2. **Async processing** — returns `200 {"status":"EVENT_RECEIVED"}` immediately
   and processes the message via Starlette `BackgroundTasks` (sends to the AI
   engine, then replies to the user through the Send API). This prevents the
   Messenger 20-second timeout.

### Meta Messenger Setup

1. Create a [Facebook App](https://developers.facebook.com/).
2. Add the **Messenger** product.
3. Configure the **Webhook**:
   - Callback URL: `https://<your-domain>/webhook`
   - Verify token: set it to the same value as `MESSENGER_VERIFY_TOKEN`.
4. Subscribe to the `messages` field for your Page.
5. Generate a **Page Access Token** → set as `MESSENGER_PAGE_ACCESS_TOKEN`.
6. Set `MESSENGER_APP_SECRET` from the App dashboard.

---

## 🧠 AI Agent Configuration

The agent's system prompt lives in `ai_engine/crews/algerian_support_crew/`:

```
algerian_support_crew/
├── agents.py      # Agent definition + system backstory (Darija/French)
├── prompts.py    # Few-shot examples + strict JSON output instruction
├── config.py     # Centralised school info (pricing, hours, address)
├── models.py     # Pydantic AgentReply / ExtractedInfo (deterministic output)
├── tasks.py      # Task bound to AgentReply via output_pydantic
├── main.py       # AlgerianSupportCrew.handle() → AgentReply
└── test_algerian_ai.py  # Local smoke test with Darija questions
```

### Business rules (single source of truth in `config.py`)
- **BEM:** 2000 DA / subject / month
- **BAC:** 2500 DA / subject / month
- **Intensive revision:** 5000 – 6000 DA
- **Hours:** Friday, Saturday and evening sessions
- **Location:** Algiers (the capital)

### Few-shot examples
`prompts.py` includes curated Darija / French examples covering spelling
variations (e.g. `7`=`ح`, `3`=`ع`, `9`=`ق`, `5`=`خ`, `8`=`غ`) so the agent
recognises the many ways students write in Algerian Arabic.

### Local smoke test (no Docker)
```bash
cd ai_engine/crews/algerian_support_crew
python -m venv .venv                       # first time
.venv/Scripts/pip install -r ../../../requirements.txt
.venv/Scripts/python test_algerian_ai.py   # needs GOOGLE_API_KEY in .env
```

---

## 📚 Dashboard (Laravel)

A ready-made school management system (`lav_sms`) managing students, teachers,
subjects, classes, results and more. Runs on Apache/PHP 8 + MySQL.

---

## 🛠 Local Development (without Docker compose)

**AI Engine**
```bash
cd ai_engine
python -m venv .venv
.venv/Scripts/pip install -r requirements.txt
cp .env.example .env   # add GOOGLE_API_KEY
cd service
../.venv/Scripts/uvicorn main:app --reload --port 8002
```

**Gateway**
```bash
cd meta_gateway
pip install -r requirements.txt
cp .env.example .env   # add Messenger secrets
export AI_ENGINE_URL=http://localhost:8002
uvicorn app.main:app --reload --port 8001
```

---

## 📁 Project Layout

```
algerian-school-ai/
├── docker-compose.yml
├── .env.example
├── README.md
├── meta_gateway/                 # FastAPI Messenger gateway
│   ├── app/
│   │   ├── main.py               # webhook (verify + async)
│   │   ├── security.py           # X-Hub-Signature-256 verification
│   │   ├── config/
│   │   └── services/             # messenger.py, ai_client.py
│   ├── requirements.txt
│   └── Dockerfile
├── ai_engine/                    # CrewAI + Gemini engine
│   ├── service/main.py           # FastAPI: POST /v1/reply
│   ├── crews/algerian_support_crew/  # agent, prompts, models, tasks
│   ├── requirements.txt
│   └── Dockerfile
├── school_dashboard/             # Laravel school management
│   ├── app/, routes/, resources/ ...
│   └── Dockerfile
```

---

## 🔐 Security Notes

- **Never commit** real `.env` files (they are git-ignored).
- Gateway validates every webhook via `X-Hub-Signature-256`; disable only for
  local testing behind a trusted proxy.
- API keys are read from environment variables only.
- Use HTTPS/TLS in front of the gateway in production.

## 🧾 License / Acknowledgments

- `meta_gateway` based on Meta's `messenger-platform-samples`.
- `school_dashboard` based on `4jean/lav_sms` (Laravel SMS).
- `ai_engine` based on `crewAIInc/crewAI-examples`.

---

Made for Algerian school-support success 🇩🇿 — **BAC & BEM**.
