# Symfony vs Laravel – Events & Jobs Comparison

## Summary table
| Concept | Laravel | Symfony |
|---------|---------|---------|
| Event dispatch | `event(new Event)` | `$dispatcher->dispatch($event, $name)` |
| Queue system | Built-in queues + `ShouldQueue` | Messenger component |
| API complexity | Simpler | More configurable |
| Stop propagation | Not available | `stopPropagation()` |

## Notes
- Laravel emphasises quick DX; queueable listeners/jobs are first-class.  
- Symfony splits concerns via Messenger transports, handlers, and more granular routing.  
- Both support event subscribers/listeners, but Symfony exposes more explicit lifecycle control.

## When to choose what
- Laravel: rapid prototyping, opinionated defaults, Horizon monitoring.  
- Symfony: enterprise workflows needing fine-grained transports, middleware, routing per message type.

**Polish source:** [`../pl/SYMFONY_VS_LARAVEL_EVENTS.md`](../pl/SYMFONY_VS_LARAVEL_EVENTS.md)
