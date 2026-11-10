# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versioning follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.1] - 2026-11-10

### Added

- Polish translation of every text the module shows, in the back office and in the shop.

### Changed

- The Polish e-mail confirming a withdrawal statement is written in Polish.
- The options of the "Right of withdrawal" field on the product page are translated.
- Texts no longer use long dashes.

## [0.2.0] - 2026-11-10

First public release.

### Added

- Online withdrawal form on the order page of the customer account, with an optional
  reason, and a "Withdrawals" list in the account.
- Acknowledgement by e-mail with a PDF statement attached, made with mPDF.
- Deadline worked out from the order status the shop picks for goods, for services
  and for digital content, with a withdrawal period of 14 to 30 days.
- Consumer or business order told apart by one of three rules: every order, a company
  name on the invoice address, or a chosen customer group.
- Products excluded from the right of withdrawal marked with a product field.
- Consent to early performance at checkout when the cart holds a service or digital
  content, through the native terms and conditions checkbox.
- Back office screens: statement list, statement page with confirm, accept and reject,
  and a block on the order page.
- Refunds through PrestaShop credit slips, or through an outside accounting system
  that reports the settlement over the Admin API.
- Admin API endpoints for statements, with read and write scopes.
- Events for Quince Events: `withdrawal.submitted`, `withdrawal.acknowledged`,
  `withdrawal.accepted`, `withdrawal.rejected` and `withdrawal.settled`.
- Declared dependency on Quince Engine.
- Released under GPL-2.0-only, because the module ships mPDF.
