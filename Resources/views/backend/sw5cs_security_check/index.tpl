<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <title>{$sw5csLabels.page_title|escape}</title>
    <style>
        body { margin: 0; padding: 16px 20px; font: 13px/1.45 "Open Sans", Arial, sans-serif; color: #475c6a; background: #f0f2f4; }
        h1 { margin: 0 0 4px; font-size: 18px; font-weight: 600; color: #333; }
        p { margin: 0 0 12px; }
        .summary { font-weight: 600; }
        table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #d1d8de; }
        th, td { padding: 8px 10px; text-align: left; vertical-align: top; border-bottom: 1px solid #e3e7ea; }
        th { background: #e9edf0; font-weight: 600; color: #333; }
        td.status { width: 90px; white-space: nowrap; font-weight: 600; }
        td.check { width: 240px; font-weight: 600; color: #333; }
        tr.error td.status { color: #b00020; }
        tr.warning td.status { color: #a15c00; }
        tr.info td.status { color: #1f5f99; }
        tr.ok td.status { color: #2e7d32; }
        tr.error { background: #fdecee; }
        tr.warning { background: #fff6e5; }
        .result { padding: 8px 10px; margin: 0 0 12px; border: 1px solid #d1d8de; background: #fff; font-weight: 600; }
        .result.failed, .result.denied { border-color: #b00020; color: #b00020; }
        .result.done { border-color: #2e7d32; color: #2e7d32; }
        form { margin: 12px 0 0; }
        button { padding: 6px 14px; font: inherit; font-weight: 600; color: #fff; background: #189eff; border: 1px solid #0b85d9; border-radius: 3px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>{$sw5csLabels.page_title|escape}</h1>
    <p>{$sw5csLabels.page_intro|escape}</p>
    {if $sw5csResult}
        {assign var="sw5csResultKey" value="restore_`$sw5csResult`"}
        <p class="result {$sw5csResult|escape}" data-result="{$sw5csResult|escape}">{$sw5csLabels.$sw5csResultKey|escape}</p>
    {/if}
    <p class="summary">{$sw5csLabels.summary|escape}</p>
    <table>
        <thead>
            <tr>
                <th>{$sw5csLabels.column_status|escape}</th>
                <th>{$sw5csLabels.column_check|escape}</th>
                <th>{$sw5csLabels.column_result|escape}</th>
            </tr>
        </thead>
        <tbody>
        {foreach $sw5csRows as $sw5csRow}
            {assign var="sw5csStatusKey" value="status_`$sw5csRow.status`"}
            <tr class="{$sw5csRow.status|escape}" data-check="{$sw5csRow.id|escape}">
                <td class="status">{$sw5csLabels.$sw5csStatusKey|escape}</td>
                <td class="check">{$sw5csRow.title|escape}</td>
                <td>{$sw5csRow.message|escape}</td>
            </tr>
        {/foreach}
        </tbody>
    </table>
    {if $sw5csShowRestore}
        <form method="post" action="{url controller="Sw5csSecurityCheck" action="restoreProtectionFiles"}">
            <input type="hidden" name="__csrf_token" value="{$sw5csCsrfToken|escape}">
            <button type="submit" data-action="restore-protection-files">{$sw5csLabels.restore_button|escape}</button>
        </form>
    {/if}
    <script src="{link file="backend/base/frame/postmessage-api.js"}"></script>
</body>
</html>
