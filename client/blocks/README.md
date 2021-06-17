# Why we chose to use our pre-existing AJAX APIs instead of the WooCommerce Blocks APIs for Payment Request Buttons

While we were in the process of migrating the Blocks integration from the WooCommerce Blocks plugin to Stripe we encountered several issues while adding support for Payment Requests with the Blocks API. The easiest way to fix these issues at the time was to use our pre-existing AJAX APIs and forego the Blocks API entirely.

The WooCommerce Blocks API has since been significantly improved and we need to re-evaluate whether we can now use the Blocks API, see https://github.com/woocommerce/woocommerce-gateway-stripe/issues/1573.

This file is meant to document why we chose to forego the Blocks API at the time, and what the downsides and benefits of that approach are.

## What were the issues we encountered?

-   3DS payments didn't work with saved cards and Payment Requests because some scripts weren't loaded in those contexts.
-   The Payment Request Dialog [didn't close during 3DS validation](https://github.com/woocommerce/woocommerce-gateway-stripe/pull/1467#discussion_r623877272).

Both of these have since been addressed in WooCommerce Blocks.

We also encountered an issue where state and zip code normalization [didn't work](https://github.com/woocommerce/woocommerce-gateway-stripe/issues/1518) because we were missing some crucial normalization functionality.

## The Downsides of Using the AJAX API

We're unable to use the framework provided by WooCommerce Blocks during checkout with Payment Request Buttons, and we miss out on many things Blocks provides by default, such as a responsive UI, improved error reporting, and more.

More specifically:

-   We have to manually make the UI react to user input, and it's simply impossible for us to make that happen in some parts of the blocks.
    -   This refers to current and future improvements to the UX; things like blocking the UI during the payment process, starting and stopping loading indicators, and general improvements in that vein.
-   The checkout process requires many more network requests, slowing the checkout process down a significant amount.
-   Some errors returned from our AJAX API are HTML notices. That HTML is not displayed correctly when the error is shown in the Cart or Checkout blocks.

## The Benefits of Using the AJAX API

There are several benefits to using our pre-existing AJAX APIs:

-   Backwards compatibility with older version of WooCommerce Blocks that were lacking support for crucial features related to Payment Requests.
    -   This is no longer true as of WooCommerce Blocks v3.0.0.
-   Code re-use; we already have well tested code in the AJAX API that's quite complicated to test, e.g. numerous normalization functions.
    -   A good example of this is how we can use the same normalization functions for the Shortcode and Block-based code.
    -   Another example is consistency in the UI; by using the AJAX API we use the same payment request information for Payment Requests started from the Blocks integration and the Shortcode flow.
    -   Another aspect to this point is that the Shortcode flow in `assets/js` is currently not built in the same system as the blocks integration; to maximize code re-use between the Shortcode and Block-based checkout we'd have to make some changes to the Shortcode checkout, making the changes required that much bigger.
-   We don't need to re-implement the payment flow using the Blocks API.
    -   The WooCommerce Blocks API has been improved significantly since we started developing this integration, so this is no longer as big of a deal as it was.
-   We get more direct control over how the Payment Request flow is managed.

Another factor in this decision was that, during development, some parts of the WooCommerce Blocks API were unable to properly handle the required flow for the Payment Request Buttons, especially when it came to 3DS payments. The WooCommerce Blocks API has since been significantly improved, and using that API should be the default choice for most projects.
