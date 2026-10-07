#!/bin/bash
# bin/stripe-listen.sh
# Forwards Stripe webhook events to this checkout's WordPress instance.

source .env 2>/dev/null

# Stripe CLI 1.51.0 stopped forwarding all events by default: `listen` exits unless
# it is given --events, --all-snapshot or --all-thin. Older versions reject
# --all-snapshot as an unknown flag, so only pass it when the installed CLI knows it.
event_args=()
if stripe listen --help 2>/dev/null | grep -q -- '--all-snapshot'; then
    event_args=(--all-snapshot)
fi

exec stripe listen "${event_args[@]}" --forward-to "http://localhost:${WORDPRESS_PORT:-8072}/?wc-api=wc_stripe" "$@"
