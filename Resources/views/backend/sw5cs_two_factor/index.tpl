<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <title>{$sw5csLabels.two_factor_title|escape}</title>
    <style>
        body { margin: 0; padding: 16px 20px; font: 13px/1.45 "Open Sans", Arial, sans-serif; color: #475c6a; background: #f0f2f4; }
        h1 { margin: 0 0 4px; font-size: 18px; font-weight: 600; color: #333; }
        h2 { margin: 20px 0 8px; font-size: 15px; font-weight: 600; color: #333; }
        p { margin: 0 0 12px; }
        .box { padding: 12px 14px; margin: 0 0 12px; background: #fff; border: 1px solid #d1d8de; }
        .status { font-weight: 600; }
        .status.on { color: #2e7d32; }
        .result { padding: 8px 10px; margin: 0 0 12px; border: 1px solid #d1d8de; background: #fff; font-weight: 600; }
        .result.invalid_code, .result.denied { border-color: #b00020; color: #b00020; }
        .result.enabled, .result.disabled, .result.reset { border-color: #2e7d32; color: #2e7d32; }
        .secret { font: 600 18px/1.4 Menlo, Consolas, monospace; letter-spacing: 1px; color: #333; word-break: break-all; }
        .uri { font: 12px/1.4 Menlo, Consolas, monospace; word-break: break-all; }
        form { display: inline-block; margin: 0 8px 0 0; }
        input[type=text] { width: 110px; padding: 5px 6px; font: inherit; letter-spacing: 2px; }
        button { padding: 6px 14px; font: inherit; font-weight: 600; color: #fff; background: #189eff; border: 1px solid #0b85d9; border-radius: 3px; cursor: pointer; }
        button.secondary { color: #475c6a; background: #fff; border-color: #d1d8de; }
        table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #d1d8de; }
        td { padding: 6px 10px; border-bottom: 1px solid #e3e7ea; }
    </style>
</head>
<body>
    <h1>{$sw5csLabels.two_factor_title|escape}</h1>
    <p>{$sw5csLabels.two_factor_intro|escape}</p>
    {if !$sw5csModuleEnabled}
        <p class="result">{$sw5csLabels.two_factor_module_off|escape}</p>
    {/if}
    {if $sw5csResult}
        {assign var="sw5csResultKey" value="two_factor_result_`$sw5csResult`"}
        <p class="result {$sw5csResult|escape}" data-result="{$sw5csResult|escape}">{$sw5csLabels.$sw5csResultKey|escape}</p>
    {/if}

    <div class="box">
    {if $sw5csEnrolled}
        <p class="status on" data-status="enabled">{$sw5csLabels.two_factor_status_on|escape}</p>
        <form method="post" action="{url controller="Sw5csTwoFactor" action="disable"}" autocomplete="off">
            <input type="hidden" name="__csrf_token" value="{$sw5csCsrfToken|escape}">
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" placeholder="{$sw5csLabels.two_factor_code|escape}">
            <button type="submit" class="secondary" data-action="disable">{$sw5csLabels.two_factor_disable|escape}</button>
        </form>
    {elseif $sw5csPendingSecret}
        <p>{$sw5csLabels.two_factor_setup_step1|escape}</p>
        <p class="secret" data-secret="{$sw5csPendingSecret|escape}">{$sw5csPendingSecret|escape}</p>
        <p>{$sw5csLabels.two_factor_setup_uri|escape}<br><span class="uri">{$sw5csPendingUri|escape}</span></p>
        <p>{$sw5csLabels.two_factor_setup_step2|escape}</p>
        <form method="post" action="{url controller="Sw5csTwoFactor" action="confirm"}" autocomplete="off">
            <input type="hidden" name="__csrf_token" value="{$sw5csCsrfToken|escape}">
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" placeholder="{$sw5csLabels.two_factor_code|escape}">
            <button type="submit" data-action="confirm">{$sw5csLabels.two_factor_confirm|escape}</button>
        </form>
        <form method="post" action="{url controller="Sw5csTwoFactor" action="cancel"}">
            <input type="hidden" name="__csrf_token" value="{$sw5csCsrfToken|escape}">
            <button type="submit" class="secondary" data-action="cancel">{$sw5csLabels.two_factor_cancel|escape}</button>
        </form>
    {else}
        <p class="status" data-status="disabled">{$sw5csLabels.two_factor_status_off|escape}</p>
        <form method="post" action="{url controller="Sw5csTwoFactor" action="start"}">
            <input type="hidden" name="__csrf_token" value="{$sw5csCsrfToken|escape}">
            <button type="submit" data-action="start">{$sw5csLabels.two_factor_start|escape}</button>
        </form>
    {/if}
    </div>

    {if $sw5csUsers}
        <h2>{$sw5csLabels.two_factor_users|escape}</h2>
        <table>
        {foreach $sw5csUsers as $sw5csUser}
            <tr data-user="{$sw5csUser.id|escape}">
                <td>{$sw5csUser.username|escape}</td>
                <td>
                    <form method="post" action="{url controller="Sw5csTwoFactor" action="reset"}">
                        <input type="hidden" name="__csrf_token" value="{$sw5csCsrfToken|escape}">
                        <input type="hidden" name="userId" value="{$sw5csUser.id|escape}">
                        <button type="submit" class="secondary" data-action="reset">{$sw5csLabels.two_factor_reset|escape}</button>
                    </form>
                </td>
            </tr>
        {/foreach}
        </table>
    {/if}
    <script src="{link file="backend/base/frame/postmessage-api.js"}"></script>
</body>
</html>
