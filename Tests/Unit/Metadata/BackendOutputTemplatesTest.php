<?php

declare(strict_types=1);

/*
 * This file is part of Sw5CommunitySecurity.
 *
 * (c) HQ GmbH <https://www.hqgmbh.de>
 *
 * Licensed under the GNU General Public License v3.0.
 * See the LICENSE file distributed with this source code.
 */

namespace Sw5CommunitySecurity\Tests\Unit\Metadata;

use PHPUnit\Framework\TestCase;

/**
 * Ext JS extensions of BackendOutputSubscriber: each one appends to the
 * core block of the view it overrides, encodes its output and is registered
 * in the subscriber. The core blocks and classes are those of
 * themes/Backend/ExtJs/backend/ in Shopware 5.6.10 and 5.7.20.
 */
final class BackendOutputTemplatesTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../../';

    private const DIRECTORY = 'Resources/views/backend/sw5cs_backend_output/';

    /**
     * Template => [core block, overridden core class, expected encoding].
     */
    private const TEMPLATES = [
        'mail/view/main/info.js' => ['backend/mail/view/main/info', 'Shopware.apps.Mail.view.main.Info', ':htmlEncode}'],
        'mail_log/view/list/mail_log.js' => ['backend/mail_log/view/list/mail_log', 'Shopware.apps.MailLog.view.list.MailLog', 'Ext.String.htmlEncode'],
        'mail_log/view/detail/form_mixin.js' => ['backend/mail_log/view/detail/form_mixin', 'Shopware.apps.MailLog.view.detail.FormMixin', 'htmlEncode = true'],
    ];

    public function testEveryExtensionIsKnown(): void
    {
        $files = glob(self::ROOT . self::DIRECTORY . '{*,*/*,*/*/*,*/*/*/*}.js', GLOB_BRACE);
        static::assertIsArray($files);
        $relative = array_map(static fn (string $file): string => substr($file, \strlen(self::ROOT . self::DIRECTORY)), $files);
        sort($relative);
        $expected = array_keys(self::TEMPLATES);
        sort($expected);
        static::assertSame($expected, $relative);
    }

    public function testExtensionsAppendToTheCoreBlockAndEncode(): void
    {
        foreach (self::TEMPLATES as $template => [$block, $class, $encoding]) {
            $source = $this->read(self::DIRECTORY . $template);
            static::assertStringContainsString('//{block name="' . $block . '" append}', $source, $template);
            static::assertStringContainsString("override: '" . $class . "'", $source, $template);
            static::assertStringContainsString($encoding, $source, $template);
            static::assertStringEndsWith("//{/block}\n", $source, $template);
        }
    }

    public function testVariableListWrapsTheCoreTemplate(): void
    {
        $source = $this->read(self::DIRECTORY . 'mail/view/main/info.js');
        // The core template is used (not copied), its placeholders get :htmlEncode.
        static::assertStringContainsString('this.callParent(arguments)', $source);
        static::assertStringContainsString("'{\$1:htmlEncode}'", $source);
        static::assertStringNotContainsString('<pre', $source);
        static::assertStringNotContainsString('<tpl', $source);
    }

    public function testMailLogWrapsTheCoreRenderers(): void
    {
        $list = $this->read(self::DIRECTORY . 'mail_log/view/list/mail_log.js');
        // contactColumnRenderer of the core is wrapped, not replaced by a copy.
        static::assertStringNotContainsString('contactColumnRenderer', $list);
        static::assertStringNotContainsString('mailto:', $list);
        static::assertStringContainsString("Ext.each(['sender', 'recipients']", $list);
        static::assertStringContainsString('renderer.apply(this, args)', $list);
        static::assertStringContainsString('copy.mailAddress = encode(contact.mailAddress)', $list);

        // Shopware's Ext.form.field.Display override decodes the value unless
        // allowHtml is set (overrides/Ext.form.field.Display.js), which would
        // undo htmlEncode: both are set, so the value is encoded exactly once.
        $detail = $this->read(self::DIRECTORY . 'mail_log/view/detail/form_mixin.js');
        static::assertStringContainsString('field.htmlEncode = true;', $detail);
        static::assertStringContainsString('field.allowHtml = true;', $detail);
        static::assertSame(2, substr_count($detail, 'me.sw5csEncodeDisplayField(') + substr_count($detail, 'this.sw5csEncodeDisplayField('));
    }

    /**
     * Smarty compiles the .js files: "{" followed by non-whitespace outside
     * {literal} is a Smarty tag; only the block tags are allowed.
     */
    public function testExtensionsContainNoUnintendedSmartyTags(): void
    {
        foreach (array_keys(self::TEMPLATES) as $template) {
            $source = (string) preg_replace('/\{literal\}.*?\{\/literal\}/s', '', $this->read(self::DIRECTORY . $template));
            preg_match_all('/\{\S+/', $source, $matches);
            static::assertSame(['{block', '{/block}'], $matches[0], $template);
        }
    }

    public function testExtensionsAreRegisteredInTheSubscriber(): void
    {
        $subscriber = $this->read('Subscriber/BackendOutputSubscriber.php');
        foreach (array_keys(self::TEMPLATES) as $template) {
            static::assertStringContainsString("'backend/sw5cs_backend_output/" . $template . "'", $subscriber, $template);
        }
        static::assertStringContainsString('Sw5CommunitySecurity\Subscriber\BackendOutputSubscriber', $this->read('Resources/services.xml'));
    }

    public function testStartPageScriptIsRegisteredAndOnlyWrapsOriginals(): void
    {
        $script = $this->read(self::DIRECTORY . 'index/header.tpl');

        static::assertStringContainsString('{block name="backend/base/header/javascript" append}', $script);
        static::assertStringContainsString('{if $user}', $script);
        static::assertSame(1, substr_count($script, '{literal}'));
        static::assertStringContainsString('Ext.ClassManager.onCreated', $script);
        // Overrides with callParent fail for some classes in Ext JS 4.1.1; originals are wrapped instead.
        static::assertStringNotContainsString('callParent', $script);
        static::assertStringNotContainsString('override:', $script);
        static::assertStringContainsString("'backend/sw5cs_backend_output/index/header.tpl'", $this->read('Subscriber/BackendOutputSubscriber.php'));

        foreach ([
            'Ext.form.field.Display', 'Shopware.grid.Panel', 'Shopware.listing.InfoPanel',
            'Shopware.apps.Customer.view.detail.Window', 'Shopware.apps.Order.view.list.List',
            'Shopware.apps.Order.view.detail.Overview', 'Shopware.apps.Vote.view.list.extensions.Info',
            'Shopware.apps.Blog.view.blog.detail.comments.InfoPanel', 'Shopware.apps.Analytics.view.table.SearchTerms',
            'Ext.grid.header.Container', 'Shopware.apps.Customer.view.customer_stream.Listing', 'Shopware.apps.UserManager.view.user.List',
        ] as $className) {
            static::assertStringContainsString("'" . $className . "'", $script, $className);
        }
        // Grid cells without a renderer are encoded after Shopware prepared them.
        static::assertStringContainsString("wrap(prototype, 'prepareData'", $script);
    }

    public function testTreeColumnsEncodeTheValuePassedToTheirRenderer(): void
    {
        $script = $this->read(self::DIRECTORY . 'index/header.tpl');

        // Ext.tree.Column replaces the renderer by treeRenderer, which outputs
        // the result of the column's own renderer next to its icons.
        static::assertStringContainsString("onClass('Ext.tree.Column'", $script);
        static::assertStringContainsString("wrap(prototype, 'treeRenderer'", $script);
        static::assertMatchesRegularExpression('/this\.allowHtml !== true\) \{\s*args\[0\] = encode\(value\);/', $script);
    }

    public function testCustomerStreamPreviewEncodesStreamsAndEncodesTheMailOnce(): void
    {
        $script = $this->read(self::DIRECTORY . 'index/header.tpl');

        static::assertStringContainsString("onClass('Shopware.apps.Customer.view.customer_stream.Preview'", $script);
        static::assertStringContainsString("secureColumns(original.apply(this, arguments), ['customernumber', 'city'])", $script);
        // Stream names and ids are encoded before the original builds the links.
        static::assertStringContainsString('column.renderer = streamsRenderer(column.renderer);', $script);
        static::assertStringContainsString('copy.name = encode(item.name);', $script);
        static::assertStringContainsString('copy.id = encode(item.id);', $script);
        // The original encodes the e-mail address itself: it is passed on raw,
        // only its tooltip is encoded a second time.
        static::assertStringContainsString('column.renderer = previewCustomerRenderer(column.renderer);', $script);
        static::assertStringContainsString("encodedRecord(record, ['email'])", $script);
        static::assertStringContainsString("join('data-qtip=\"' + encode(once) + '\"')", $script);
        static::assertStringNotContainsString("'Shopware.apps.Customer.view.customer_stream.Preview', 'getColumns', ['customernumber', 'lastname'", $script);
    }

    public function testCoreCodeIsWrappedNotCopied(): void
    {
        $script = $this->read(self::DIRECTORY . 'index/header.tpl');

        // Order list customer column (order/view/list/list.js, customerColumn).
        static::assertStringNotContainsString('customer-column-icon', $script);
        static::assertStringNotContainsString("'customerComment'", $script);
        static::assertStringContainsString('encodedStore(record.getBilling.apply(record, arguments))', $script);
        static::assertStringContainsString('return original.apply(scope, args);', $script);
        // Customer window title (customer/view/detail/window.js, setWindowTitle).
        static::assertStringNotContainsString('[0] [1] [2] ([3])', $script);
        static::assertStringNotContainsString('titleEdit', $script);
        static::assertStringContainsString('me.record = encodedRecord(record);', $script);
    }

    private function read(string $file): string
    {
        $content = file_get_contents(self::ROOT . $file);
        static::assertIsString($content, $file);

        return $content;
    }
}
