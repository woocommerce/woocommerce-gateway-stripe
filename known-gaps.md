# Known gaps: undelivered event processing

Temporary file for the draft PR. Remove before marking it ready for review.

## Rollout
- No feature flag. Everything (recording webhooks, the job, the tools) is active on every store.
- No tests.
- No BC note yet for the new public surface: the store interface, the webhook handler's constructor argument, and the 409 response for events another request is processing.

## Processing
- No retries. A failed fetch or a thrown exception is final (`failed`).
- No attempt counter, and no `abandoned` status for events that cannot be fetched (expired after 30 days, or `resource_missing`).
- Events left in `processing` are only recovered when the same event arrives again. Nothing sweeps them.
- The job can spin for up to the lock TTL (10 minutes) when a pending event's lock was left behind by a request that died, fetching the event on every pass. It re-enqueues immediately with no backoff, including during a Stripe outage.
- No filtering by event type, connected account, or agentic (`v1.delegated_checkout.*`) events.
- `delivery_success=false` covers every endpoint on the account, so events that failed for another site or service on the same account are processed here too.

## Listing
- No lookback cap on the first run, and no page cap per run.
- Switching to a different Stripe account in the same mode is not handled: the cursor carries over and hides older events from the new account, and the old account's pending events fail. A lazy account ID check next to the cursor is proposed.

## Storage
- Lock rows left by requests that died are reclaimed on the next claim of the same event, and deleted by the "Clear stored Stripe events" tool and on uninstall, but nothing sweeps them during normal operation.
- Records created before the `livemode` meta was added have no mode, and are never picked up by the job.

## Scope limits
These are outside what event replay can fix, and need other work:
- Signature validation failures answer 204, so a wrong webhook secret looks like a successful delivery and the events never appear as undelivered.
- Events are not delivered at all when the store has no endpoint, or a disabled one.
- A webhook answered with 200 where the handler did nothing (for example, the order could not be found yet) counts as delivered.
- Deferred webhooks stay `processing` until the deferred job completes.

## Noted for later, unrelated to this PR
- `lock_order_payment()` checks then sets order meta, which is not atomic.
