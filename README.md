# PulseSync

PulseSync connects WordPress lead capture to a Laravel analytics dashboard.

```mermaid
flowchart LR
  Visitor --> Widget[WordPress widget]
  Widget --> Queue[WordPress MySQL retry queue]
  Queue -->|Bearer token| API[Laravel API]
  API --> Jobs[Database queue]
  Jobs --> PG[(PostgreSQL JSONB)]
  PG --> Dashboard[Vue analytics dashboard]
```
