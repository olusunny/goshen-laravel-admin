# Requirement Traceability

| Requirement | Current component(s) | Release evidence required |
| --- | --- | --- |
| Exact identifier and capability | `addon.json`, `config/church-birthday-celebrations.php` | Installed manifest and capability response |
| Canonical verified members only | `BirthdayEligibilityService` | Eligible/ineligible test cases, including visitor and no-Triumphant-ID exclusions |
| No Goshen family/retreat data | Eligibility service queries `MobileUser` profile fields only | Code review and API privacy check |
| Preview seven days before birthday | `BirthdayLifecycleService::run/prepare` | Scheduler test across all preview days |
| Auto-publish and 24-hour window | `BirthdayLifecycleService::publish/close` | Timezone-aware lifecycle test |
| February 29 policy | `BirthdayEligibilityService::occursOn` | Leap and non-leap tests for both policies |
| 30-day private retention and purge | `BirthdayLifecycleService::purgeDue/purge` | Media, interactions, delivery, and API `410` evidence |
| Default visibility and opt-out | `BirthdayPreference`, preferences API | Opt-out revokes current preview/published content |
| Templates and approved verses | Filament template/verse resources, preference validation | Permission and inactive-option rejection tests |
| Private cards and fallback | `BirthdayCardService`, card API | PNG signatures, private headers, fallback, delete proof |
| Digest and private notification delivery | `BirthdayNotifier`, `BirthdayDelivery` | Deduplication, failed retry, private push data, no duplicate sends |
| Reactions/greetings/thank-you/reporting | `BirthdayInteractionService`, controller, moderation resource | Limits, ownership, report threshold, moderation tests |
| Admin operations | Filament resources and host Add-ons resource | Role matrix and lifecycle log screenshots/records |
| Activation and capability gating | provider, `AddonAvailability`, active middleware | Inactive API/deep-link/menu rejection |
| Update and rollback | host `AddonLifecycleService` | Staging failed-migration/provider activation restore test |
| Flutter dormant client | Flutter birthday feature and capability API | Fresh app build and inactive/add-on-stale route tests |

## Explicit exclusions

The feature must not expose birth year, age, phone, email, address, accommodation, family relationships, Goshen data, public pages, search indexing, nested comments, payments, or a second member directory.

## Open evidence, not completion claims

Source code and focused tests may exist, but a release record is incomplete until the verification template captures command output, artifact checksums, host installation status, staging lifecycle evidence, and Flutter build evidence. Do not infer these from this document.
