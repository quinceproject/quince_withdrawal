{**
 * "Moje odstąpienia" — ten sam układ co natywna lista "Zwroty produktów"
 * (customer/order-follow.tpl), żeby obie listy wyglądały jak jedna rodzina.
 *}
{extends file='customer/page.tpl'}

{block name='page_header_container'}
  <div class="q-order-header">
    <a class="q-order-back" href="{$urls.pages.my_account}">
      <svg class="q-order-back__icon" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 13 6 8l5-5"/></svg>
      {l s='Back to your account' d='Shop.Theme.Customeraccount'}
    </a>
    <h1 class="q-order-header__title" tabindex="-1">{l s='Withdrawals' d='Modules.Quincewithdrawal.Shop'}</h1>
  </div>
{/block}

{block name='page_content'}
  {if $withdrawals && count($withdrawals)}
    <div class="q-account-section q-card">
    <div class="grid-table" role="table" aria-label="{l s='Withdrawals' d='Modules.Quincewithdrawal.Shop'}">
      <div class="grid-table__inner grid-table__inner--4" role="rowgroup">
        <div class="grid-table__header" role="row">
          <span class="grid-table__cell" role="columnheader">{l s='Order' d='Shop.Theme.Customeraccount'}</span>
          <span class="grid-table__cell" role="columnheader">{l s='Statement' d='Modules.Quincewithdrawal.Shop'}</span>
          <span class="grid-table__cell" role="columnheader">{l s='Status' d='Modules.Quincewithdrawal.Shop'}</span>
          <span class="grid-table__cell" role="columnheader">{l s='Date issued' d='Shop.Theme.Customeraccount'}</span>
        </div>

        {foreach from=$withdrawals item=w}
          <div class="grid-table__row" role="row">
            <span class="grid-table__cell" role="cell" data-ps-label="{l s='Order' d='Shop.Theme.Customeraccount'}">
              <a href="{$w.detailsUrl}">{$w.order_reference}</a>
            </span>
            <span class="grid-table__cell" role="cell" data-ps-label="{l s='Statement' d='Modules.Quincewithdrawal.Shop'}">
              {$w.reference}
            </span>
            <span class="grid-table__cell" role="cell" data-ps-label="{l s='Status' d='Modules.Quincewithdrawal.Shop'}">
              {if $w.status == 'submitted'}{l s='Submitted, waiting for the shop to confirm' d='Modules.Quincewithdrawal.Shop'}
              {elseif $w.status == 'acknowledged'}{l s='Confirmed by the shop' d='Modules.Quincewithdrawal.Shop'}
              {elseif $w.status == 'accepted'}{l s='Accepted, refund pending' d='Modules.Quincewithdrawal.Shop'}
              {else}{l s='Rejected' d='Modules.Quincewithdrawal.Shop'}
              {/if}
            </span>
            <span class="grid-table__cell" role="cell" data-ps-label="{l s='Date issued' d='Shop.Theme.Customeraccount'}">
              <small class="text-secondary">{$w.date_add|date_format:"%Y-%m-%d"}</small>
            </span>
          </div>
        {/foreach}
      </div>
    </div>
    </div>
  {else}
    <div class="alert alert-info" role="alert" tabindex="0">
      <svg class="q-alert__icon" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/></svg>
      <span class="q-alert__body">{l s='You have no withdrawal statements.' d='Modules.Quincewithdrawal.Shop'}</span>
    </div>
  {/if}
{/block}
