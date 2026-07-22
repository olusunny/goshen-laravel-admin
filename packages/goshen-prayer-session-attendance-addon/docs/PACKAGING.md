# Packaging and Signature Trust

## ZIP layout

Build the ZIP with the package root at archive root, not nested under an extra directory. The archive must contain at least the manifest, Composer metadata, package source, package configuration, migrations, routes, resources, and documentation required by the host convention.

```text
addon.json
composer.json
config/
database/migrations/
routes/
src/
resources/
docs/
```

Use the host add-on namespace, package key, version, compatibility declarations, provider, PSR-4 map, route prefixes, migrations path, and permission list. Do not ship arbitrary vendor dependencies, executable installers, private keys, environment files, or secrets.

## Explicit activation

The manifest/lifecycle implementation must declare that a fresh installation remains inactive. Existing add-ons may retain their historical default, but this add-on requires an explicit Administrator activation after a successful upload/install. Its provider and capability exposure must remain dormant until activation succeeds.

## Integrity and signature trust

Production trust configuration belongs in protected deployment configuration, never in this repository or the ZIP:

- Configure the trusted public key or checksum allowlist through the host's `addons` signature-verifier configuration.
- Keep private signing keys in the approved secure signing environment only.
- Sign the final archive or publish its approved checksum using the existing host-supported mechanism.
- Upload the final signed archive through the Add-ons manager and confirm that the manager reports signature verification before activation.

Do not place a signing key, checksum allowlist secret, token, or server path credential in `addon.json`, `composer.json`, documentation examples, commits, or support tickets.

### Required signing hand-off

The final ZIP is intentionally unsigned in the source workspace. Before it can be uploaded to an environment where `addons.signatures.required` is enabled, an authorized release operator must complete one of these protected steps:

1. Sign the ZIP's canonical entry payload with the approved private key and add the base64 signature as `addon.sig`, then configure the matching public key in the protected `addons.signatures.public_keys` or `addons.signatures.public_key_paths` setting.
2. Add the exact lowercase SHA-256 ZIP checksum to the protected `addons.signatures.trusted_checksums` allowlist.

Neither option may be performed by committing secrets or changing production configuration from this package. Rebuild the ZIP before calculating its checksum or signing it; changing any archive entry invalidates both forms of trust.

## Repeatable packaging procedure

1. Start from a clean package staging directory containing only the files intended for the package.
2. Validate the manifest and Composer metadata against the host add-on validator.
3. Run the package's focused tests and the host lifecycle tests in a safe environment.
4. Create the archive using the exact accepted root layout.
5. Sign the archive or register its checksum using the protected trust workflow.
6. Record the package version and non-secret verification result.
7. Upload to staging through the Add-ons manager, confirm it remains inactive, then execute the staging checklist.

## Do not claim verification prematurely

This documentation describes the intended procedure. Record actual command output, package checksum, and staging results only after they have been run and reviewed. A ZIP is not release-ready merely because it can be created.
