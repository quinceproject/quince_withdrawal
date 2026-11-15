# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versioning follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.5] - 2026-11-15

### Security

- Every back-office screen of the module checks the employee's permissions on the
  **Withdrawals** tab (`AdminQuinceWithdrawal`). Before, any logged-in employee, whatever
  their profile, could open the register of statements and accept or reject them.
  Grant the tab to a profile under **Advanced Parameters → Team → Permissions**;
  SuperAdmin needs nothing.

## [0.2.4] - 2026-11-15

### Security

- The reason a customer gives, and the fields that come through the Admin API
  (`enteredBy`, `note`, `externalId`), are escaped before they are saved. Before, a
  logged-in customer with an order still open to withdrawal could inject SQL through
  the reason field and read the database. Update now.
- The withdrawal form carries a token and is accepted only as a POST from the order
  page. Before, a link on another site could submit a withdrawal statement on behalf
  of a logged-in customer.

## [0.2.3] - 2026-11-13

### Fixed

- The back office no longer sends you to the shop's home page after this module is
  installed from its release ZIP. The index.php file in every folder of the package
  redirected and stopped the process also when PrestaShop loaded it while scanning
  `src/ApiPlatform/Resources`, so the back office could not be built.

## [0.2.2] - 2026-11-10

### Changed

- An empty value in a table shows a hyphen instead of a long dash.
- Admin API descriptions no longer use long dashes.

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
