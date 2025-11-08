# Synchronous vs Asynchronous in Laravel

| Component | Default | Async option |
|-----------|---------|--------------|
| Event | Synchronous (data object) | N/A |
| Listener | Synchronous | Yes – implement `ShouldQueue` |
| Job | Asynchronous | Built for queues |

## Key points
- Events carry data only; dispatching is immediate.  
- Listeners run inline unless they implement `ShouldQueue`.  
- Jobs are executed by workers (Horizon/queue:work), support retries/backoff.

**Polish source:** [`../pl/SYNC_VS_ASYNC.md`](../pl/SYNC_VS_ASYNC.md)
