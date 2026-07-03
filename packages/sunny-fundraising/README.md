# Fundraising Campaigns Add-on

Premium wallet-funded fundraising campaign module for Laravel hosts and Flutter mobile apps.

This package is designed to be installed either as a Composer path repository during development or as an admin-uploaded ZIP through the host add-on manager.

## Development Install

Add a path repository to the host `composer.json`:

```json
{
  "repositories": [
    {
      "name": "sunny-fundraising",
      "type": "path",
      "url": "packages/sunny-fundraising"
    }
  ]
}
```

Then require `sunny/fundraising` normally in development environments.

## Admin ZIP Install

Package this folder as a ZIP with `addon.json` at the package root, then upload it from the admin Add-ons page. The host add-on manager validates the manifest, rejects unsafe paths, extracts to staging, installs files, runs package migrations, activates the provider, and logs every lifecycle step.

## Wallet Integration

Bind `Sunny\Fundraising\Contracts\WalletGatewayContract` to a host adapter or configure `fundraising.wallet.gateway`. The default gateway throws a clear configuration exception.

## Data Safety

Uninstalling the add-on removes package files by default but preserves database data and host wallet transaction records.
