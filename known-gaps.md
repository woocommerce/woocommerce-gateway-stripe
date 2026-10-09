# Known gaps: undelivered event processing

Temporary file for the draft PR. Remove before marking it ready for review.

## ToDo

### Rollout
- No tests.
- No BC note yet for the new public surface: the store interface, the webhook handler's constructor argument, and the 409 response for events another request is processing.

### Processing
- Events left in `processing` are only recovered when the same event arrives again. Nothing sweeps them.

### Listing
- No lookback cap on the first run, and no page cap per run.
- Switching to a different Stripe account in the same mode is not handled: the cursor carries over and hides older events from the new account, and the old account's pending events are abandoned as `resource_missing`. A lazy account ID check next to the cursor is proposed.

### Storage
- Lock rows left by requests that died are reclaimed on the next claim of the same event, and deleted by the "Clear stored Stripe events" tool and on uninstall, but nothing sweeps them during normal operation.

## FYI

### Behavior
- Failed fetches are retried on the next reconciliation run (every 30 minutes) rather than with a growing delay, and abandoned after 48 attempts or on `resource_missing`. An event can be fetched more than once in a run when other events in its batches succeed.
- A thrown exception while processing is final (`failed`), since retrying would most likely repeat it.
- `delivery_success=false` covers every endpoint on the account, so events that failed for another site or service on the same account are processed here too.
- Disabling the feature flag stops all processing and cleanup, but keeps the stored records until it is enabled again or the plugin is uninstalled with `WC_REMOVE_ALL_DATA`.
- Deferred webhooks stay `processing` until the deferred job completes.

### Scope limits
These are outside what event replay can fix, and need other work:
- Signature validation failures answer 204, so a wrong webhook secret looks like a successful delivery and the events never appear as undelivered.
- Events are not delivered at all when the store has no endpoint, or a disabled one.
- A webhook answered with 200 where the handler did nothing (for example, the order could not be found yet) counts as delivered.
### Unrelated to this PR
- `lock_order_payment()` checks then sets order meta, which is not atomic.
