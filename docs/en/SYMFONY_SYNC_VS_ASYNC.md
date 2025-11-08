# Symfony – Sync vs Async (Events & Messenger)

## Summary
| Component | Default | Async possible? |
|-----------|---------|-----------------|
| Event | Synchronous (data object) | N/A |
| Event listener | Synchronous | Yes – via Messenger |
| Message/Handler | Asynchronous | Designed for queues |

## Details
- Events are simple DTOs; dispatching is synchronous.  
- Listeners run immediately unless routed through Messenger.  
- Messages/commands handled by Messenger can be async when configured with transports (e.g. Redis, RabbitMQ).

## Recommendations
- Use Messenger transports for heavy work.  
- Keep domain events thin and defer processing to async handlers where possible.  
- Monitor transports for failures/retries.

**Polish source:** [`../pl/SYMFONY_SYNC_VS_ASYNC.md`](../pl/SYMFONY_SYNC_VS_ASYNC.md)
