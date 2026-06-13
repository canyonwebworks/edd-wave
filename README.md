# edd-wave

Connects Easy Digital Downloads to Wave Apps. If you are accepting Stripe payments using EDD's Stripe extension or PayPal Payments using EDD's PayPal Commerce Pro, this EDD extension will move bookkeeping data into your Wave Apps transactions using Wave's GraphQL API. It saves a ton of data-entry, and picks up where free Zapier leaves off.

The plugin has a settings panel where you can map your downloads and accounts to Wave accounts (in Chart of Accounts).
## Authentication

Wave Apps requires a paid account to acquire Oath Client keys. There is a way around that using this plugin, but we don't necessarily recommend using it.

When using Oath and setting up a Wave Application, the whitelisted redirect URL should look like:

https://yourdomain.com/wp-admin/admin-post.php?action=edd_wave_oauth_callback

(Replace yourdomain.com with your real domain)

## Development
Tax, multicurrency, refunds, and fee coverage is minimal so far, so if you'd like to see this developed, get in touch or make a pull request. Keep in mind that WaveApp does NOT allow for editing already-created transactions using the GraphQL API.
