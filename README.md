# Wave + EDD

Connects Easy Digital Downloads to Wave Apps accounting software.

If you are accepting Stripe payments using EDD's Stripe extension or PayPal Payments using EDD's PayPal Commerce Pro, this EDD extension will move bookkeeping data into your Wave Apps transactions using Wave's GraphQL API. It picks up where free Zapier leaves off, and saves you a ton of manual data-entry.

The Wave + EDD plugin has a settings panel where you can map your downloads and accounts to Wave accounts (in Chart of Accounts).

## Authentication

Wave Apps requires a paid account to acquire Oath Client keys. There is a way around that using this plugin, but we don't necessarily recommend using it.

When using Oath and setting up a Wave Application, the whitelisted redirect URL should look like:

https://yourdomain.com/wp-admin/admin-post.php?action=edd_wave_oauth_callback

(Replace yourdomain.com with your real domain)

## Development
Tax, multicurrency, refunds, and fee coverage is minimal so far, so if you'd like to see this developed, get in touch or make a pull request. Keep in mind that WaveApp does NOT allow for editing already-created transactions using the GraphQL API, and this limits development.

## Before Using
Please review the code. See that best efforts have been made to protect everyone's data. Understand all software comes with risk. Proceed if you consent to the terms of the (included) GNU software license.


