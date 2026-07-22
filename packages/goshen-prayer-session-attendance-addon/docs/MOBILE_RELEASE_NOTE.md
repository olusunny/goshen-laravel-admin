# Flutter Dormant Feature Release Note

## One-time mobile release requirement

The Laravel ZIP cannot add Dart screens to an already-installed mobile application. Before an administrator can expose Prayer Session Attendance to members or Control Hub staff, release a Flutter application version that contains the dormant, capability-gated integration.

The stable feature key is `prayer_session_attendance`. Flutter must fail closed: when the server reports the add-on as absent, inactive, incompatible, unavailable, or unauthorized, it hides the navigation entry and shows an unavailable state for cached pages and deep links.

## What the mobile release includes

- Attendee session QR scan and an accessible camera-permission recovery path.
- Clear processing, Confirmed, Already Confirmed, closed-session, wrong-event, invalid-QR, and network-error states.
- Coordinator and Attendance Staff Control Hub access determined by server capability and server permission, not by a broad local role guess.
- Coordinator QR preview, authenticated QR download, and native share support.
- Staff ticket scan/manual lookup and the established encrypted offline queue pattern where supported by the server contract.
- Notification and deep-link revalidation before navigation.

## Rollout order

1. Complete and publish the Flutter release containing the dormant integration.
2. Confirm the server capability endpoint returns the feature as unavailable while the add-on is not active.
3. Upload, install, and explicitly activate the signed Laravel ZIP.
4. Verify only eligible members and authorized staff can see the new surfaces.

After the one-time Flutter release, server-side install, activation, and deactivation control availability without a further mobile release, provided the API capability contract remains compatible.

## Support guidance

An attendee without connectivity should be directed to an authorized Attendance Staff member for a ticket scan. The client must not create an offline self-confirmation based on client time or a cached QR.
