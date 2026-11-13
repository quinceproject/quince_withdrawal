{**
 * Karta "prawo odstąpienia" na stronie szczegółu zamówienia klienta.
 *
 * Ten sam wzorzec co "Zwrot towaru" (order-detail-return.tpl): formularz
 * stoi od razu w karcie, bez osobnej strony. Wysyłka wraca na tę samą
 * stronę zamówienia, a status w karcie sam się zmienia z formularza na
 * potwierdzenie.
 *}
{if $statement}
  <section class="q-account-section q-card">
    <div class="q-account-section__header">
      <h3 class="q-account-section__title">{l s='Right of withdrawal' d='Modules.Quincewithdrawal.Shop'}</h3>
    </div>
    <div class="q-order-address">
      <p class="mb-1">{$statement.reference}</p>
      <p class="text-secondary mb-0">
        {if $statement.status == 'submitted'}{l s='Submitted, waiting for the shop to confirm' d='Modules.Quincewithdrawal.Shop'}
        {elseif $statement.status == 'acknowledged'}{l s='Confirmed by the shop' d='Modules.Quincewithdrawal.Shop'}
        {elseif $statement.status == 'accepted'}{l s='Accepted, refund pending' d='Modules.Quincewithdrawal.Shop'}
        {else}{l s='Rejected' d='Modules.Quincewithdrawal.Shop'}
        {/if}
      </p>
    </div>
  </section>
{elseif $eligible}
  <section class="q-account-section q-card">
    <div class="q-account-section__header">
      <h3 class="q-account-section__title">{l s='Right of withdrawal' d='Modules.Quincewithdrawal.Shop'}</h3>
    </div>

    <form method="post" action="{$requestUrl}" class="q-order-address">
      <label class="form-label" for="q-withdrawal-reason">{l s='Reason (optional)' d='Modules.Quincewithdrawal.Shop'}</label>
      <textarea
        rows="3"
        name="reason"
        id="q-withdrawal-reason"
        class="form-control"
        aria-describedby="q-withdrawal-hint"
        placeholder="{l s='You do not have to give one.' d='Modules.Quincewithdrawal.Shop'}"
      ></textarea>

      <p class="form-text" id="q-withdrawal-hint">
        {l
          s='You can withdraw from this order within %days% days, with no reason required. We will acknowledge receipt by e-mail, with a copy attached as a PDF.'
          sprintf=['%days%' => $termDays]
          d='Modules.Quincewithdrawal.Shop'
        }
      </p>

      <div class="buttons-wrapper buttons-wrapper--end mt-3">
        <button class="btn btn-primary" type="submit" name="submitWithdrawal" value="1">
          {l s='Withdraw from this order' d='Modules.Quincewithdrawal.Shop'}
        </button>
      </div>
    </form>
  </section>
{/if}
