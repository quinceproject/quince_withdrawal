# Quince Withdrawal

**The right of withdrawal from a distance contract for PrestaShop 9.2.**

Quince Withdrawal gives customers an online form to withdraw from an order and sends
them an acknowledgement on a durable medium: an e-mail with a PDF statement attached.
It works out the withdrawal deadline from the right moment for goods, services and
digital content, knows which products are excluded from the right, and keeps a
register of statements in the back office.

> Quince Withdrawal is a free, open-source module (GPL-2.0-only). There is nothing to
> buy. If it saves you time, you can support development, see
> [Supporting the project](#supporting-the-project).

> The module is a tool, not legal advice. It follows the EU rules on the right of
> withdrawal (Directive 2011/83/EU and its amendment 2023/2673) as far as its settings
> go. Check those settings against the law of the countries you sell to.

---

## Features

- **Online withdrawal form.** On the order page of their account, customers click
  **Withdraw from this order** and may give a reason, which the law does not require.
  The account has a **Withdrawals** list with the status of each statement.
- **Acknowledgement on a durable medium.** As soon as the statement is submitted, the
  customer gets an e-mail with a PDF statement attached. The PDF is stored outside the
  public folders of the shop and only leaves it as the attachment.
- **The right deadline.** The deadline for goods starts on delivery, for services and
  digital content on the conclusion of the contract. The shop picks the order status
  that stands for each moment and sets the period from 14 to 30 days.
- **Consumers only.** The right belongs to consumers, not to businesses. The shop
  chooses how to tell them apart: treat every order as a consumer order, treat an
  order with a company name on the invoice address as a business order, or treat
  orders from a chosen customer group as business orders.
- **Excluded products.** A product field marks goods and services excluded from the
  right of withdrawal.
- **Early performance consent.** When the cart holds a service or digital content,
  checkout shows a consent checkbox through the native terms and conditions block, so
  it is validated the same way. The consent is stored with the order.
- **Back office register.** A list of statements with filters (waiting for
  confirmation, return in progress, deadline today or passed), a page for each
  statement with its progress and the **Confirm**, **Accept the return** and **Reject**
  actions, the PDF, and a block on the order page.
- **Refunds.** By default the shop issues the refund as a PrestaShop credit slip. A
  shop whose invoices and credit notes come from an outside accounting system can
  turn that off and let the system report the settlement over the Admin API.
- **Admin API.** Statements can be listed, read, created, confirmed, accepted,
  rejected and settled from an outside system over OAuth2.
- **Events.** With [Quince Events](https://github.com/quinceproject/quince_events)
  installed, the module reports `withdrawal.submitted`, `withdrawal.acknowledged`,
  `withdrawal.accepted`, `withdrawal.rejected` and `withdrawal.settled`. Without it,
  statements are still saved.
- **Multistore.** Every statement and every setting belongs to the shop where the order
  was placed.

## Requirements

| Requirement | Version |
|-------------|---------|
| PrestaShop  | 9.2 (tested on 9.2.0) |
| PHP         | 8.4 (tested), 8.1 or newer required |
| Quince Engine | required |
| Quince Events | optional, for outgoing events |

Quince Engine stores the module's settings and styles its back office screens. The
module declares it as a dependency, so PrestaShop refuses to install Quince
Withdrawal without it.

## Installation

The release ZIP already contains everything the module needs, mPDF included. Composer
and the command line are not required.

**From the back office**

1. Download the latest **`quince_withdrawal.zip`** from the
   [**Releases**](https://github.com/quinceproject/quince_withdrawal/releases) page.
2. In the back office go to **Modules → Module Manager → Upload a module** and
   choose the ZIP. PrestaShop installs it.

**By copying the files to the server**

1. Unpack the release ZIP. It contains a `quince_withdrawal` folder.
2. Upload that folder to the `modules/` directory of the shop, for example by FTP.
3. In **Modules → Module Manager** find Quince Withdrawal and click **Install**.

**From Quince Engine**

With Quince Engine installed, **Quince project → Quince modules** installs and updates
this module from the Quince release list, without downloading anything by hand.

**With Composer**

A PrestaShop shop ships without a `composer.json` of its own, so Quince packages are
installed from a separate folder next to the shop. The one-time setup of that folder is
described in the [Quince Engine README](https://github.com/quinceproject/quince_engine#installation).
Then, from that folder:

```sh
composer require "quinceproject/withdrawal:*"
php ../bin/console prestashop:module install quince_withdrawal
```

> ⚠️ **Do not** use the green **Code → Download ZIP** button on this page. That
> archive contains the source only and is **missing the `vendor/` folder**, so the
> module will not run. Always install from a
> [**Release**](https://github.com/quinceproject/quince_withdrawal/releases).

Uninstalling the module deletes every withdrawal statement on record. Export them
first if you need to keep a copy.

## Configuration

Open **Customers → Customer Service → Withdrawal** in the back office menu and go to
the settings.

1. **Who the right applies to.** Choose how the shop tells a consumer from a business.
2. **When the deadline starts.** Pick the order status that means "delivered" for
   goods, "contract concluded" for services and digital content, and "delivered" for
   digital content.
3. **How long the deadline is.** Set the number of days, from 14 to 30.
4. **Refunding the customer.** Keep PrestaShop credit slips, or turn them off when an
   outside accounting system handles refunds.

Mark excluded products in the product field the module adds to the product page in
the back office.

## Admin API

All endpoints need an OAuth2 client of the PrestaShop Admin API with the
`quince_withdrawal_read` or `quince_withdrawal_write` scope.

| Method | Endpoint | Scope |
|--------|----------|-------|
| `GET` | `/quince-withdrawal/statements` | read |
| `GET` | `/quince-withdrawal/statements/{id}` | read |
| `POST` | `/quince-withdrawal/statements` | write |
| `POST` | `/quince-withdrawal/statements/{id}/confirm` | write |
| `POST` | `/quince-withdrawal/statements/{id}/accept` | write |
| `POST` | `/quince-withdrawal/statements/{id}/reject` | write |
| `PATCH` | `/quince-withdrawal/statements/{id}/settlement` | write |

## Known limitations

- A statement covers the whole order. Withdrawing from part of an order is not
  supported yet.
- Events raised from the back office take the shop from the current back office
  context. With "All shops" selected, an event may carry the wrong shop. Select the
  shop of the order before confirming, accepting or rejecting a statement.
- The back office and shop texts are in English. The acknowledgement e-mail is in
  English and Polish.

## Architecture

- **`StatementService`** and **`StatementWorkflow`** create statements and move them
  through confirmation, acceptance, rejection and settlement.
- **`DeadlineCalculator`** works out the deadline from the order history and the
  settings.
- **`PdfGenerator`** builds the PDF statement with mPDF from `libs/`.
- **`StatementMailer`** sends the acknowledgement with the PDF attached.
- **API Platform resources and processors** in `src/ApiPlatform` serve the Admin API.
- **Symfony controllers and Twig** for the back office screens.
- **Settings** are stored in PrestaShop configuration under the `QUINCE_WITHDRAWAL_`
  prefix.

## Development / building from source

This repository tracks **source only**. The `vendor/` directory is git-ignored, so a
`git clone` is **not** directly installable. **Start with `composer install`**: the
development environment mounts module directories straight from disk, and without
that step a fresh clone gives a broken back office.

```bash
git clone https://github.com/quinceproject/quince_withdrawal.git
cd quince_withdrawal
composer install
```

mPDF and its packages live in `libs/` and are part of the repository.

The folder must be called `quince_withdrawal`, the same as the module. A clone of this
repository already has that name.

To build an installable archive, zip the `quince_withdrawal/` folder **with its
`vendor/` and `libs/` directories included**, leaving out `.git/` and `.gitignore`.

## License

Code: [GNU General Public License v2.0 only (GPL-2.0-only)](LICENSE).

The module ships mPDF, which is licensed under GPL-2.0-only, so the whole module is
released under that licence. Other Quince modules use the Open Software License 3.0.
The other packages in `libs/` keep their own licences, listed in
[THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).

**The name "Quince" is not covered by the code licence.** A fork may use the code; it
may not use the name.

© 2026 Marcin Gajewski

## Supporting the project

Quince modules are free downloads and there is nothing to buy. If the module is useful
to you, you can support its development by buying me a coffee:

**[buymeacoffee.com/marcingajewski](https://buymeacoffee.com/marcingajewski)**
