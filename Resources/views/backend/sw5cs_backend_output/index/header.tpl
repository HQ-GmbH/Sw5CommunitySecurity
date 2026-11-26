{* This file is part of Sw5CommunitySecurity. (c) HQ GmbH, licensed under the GNU General Public License v3.0. *}
{block name="backend/base/header/javascript" append}
{if $user}
{* Backend views output stored data (grid cells, customers, orders, reviews,
   comments, statistics, logs, users) as text instead of HTML. Ext JS 4.1 does not encode grid cells,
   template placeholders or tooltips by itself; data from the REST API,
   imports and plugins is not filtered by Shopware's input filter. The original
   methods are wrapped, never copied: they run unchanged and only receive
   encoded values or have their output encoded. *}
{literal}
<script type="text/javascript">
(function () {
    'use strict';

    if (!window.Ext || !Ext.ClassManager || !Ext.String || window.sw5csBackendOutput) {
        return;
    }
    window.sw5csBackendOutput = true;

    var encode = function (value) {
        return typeof value === 'string' ? Ext.String.htmlEncode(value) : value;
    };
    // Tooltips (data-qtip): the browser decodes the attribute, the tooltip
    // then inserts it as HTML, so the text is encoded twice.
    var encodeAttribute = function (value) {
        return encode(encode(value));
    };

    // Runs callback once the class exists (now, or when its app is loaded).
    var onClass = function (className, callback) {
        Ext.ClassManager.onCreated(function () {
            var cls = Ext.ClassManager.get(className);
            if (cls && cls.prototype) {
                callback(cls.prototype);
            }
        }, null, className);
    };

    // Replaces prototype[method] by factory(original); applied once.
    var wrap = function (prototype, method, factory) {
        var original = prototype[method];
        if (typeof original !== 'function' || original.sw5csWrapped) {
            return;
        }
        var wrapped = factory(original);
        wrapped.sw5csWrapped = true;
        prototype[method] = wrapped;
    };

    // Record whose get() returns encoded strings; other methods unchanged.
    var encodedRecord = function (record) {
        if (!record || typeof record.get !== 'function') {
            return record;
        }
        var copy = Object.create(record);
        copy.get = function () {
            return encode(record.get.apply(record, arguments));
        };
        return copy;
    };

    // Renderer that receives the encoded value and an encoded record.
    var encodedRenderer = function (renderer) {
        var wrapped = function (value, metaData, record) {
            var args = Array.prototype.slice.call(arguments);
            args[0] = encode(value);
            args[2] = encodedRecord(record);
            return renderer.apply(this, args);
        };
        wrapped.sw5csWrapped = true;
        return wrapped;
    };

    // Grid columns (array or Shopware column config) of the given data
    // indexes: plain columns get an encoding renderer, existing renderers
    // receive encoded values.
    var secureColumns = function (columns, dataIndexes) {
        Ext.each(Ext.isArray(columns) ? columns : [], function (column) {
            if (!column || dataIndexes.indexOf(column.dataIndex) === -1) {
                return;
            }
            if (typeof column.renderer === 'function') {
                if (!column.renderer.sw5csWrapped) {
                    column.renderer = encodedRenderer(column.renderer);
                }
            } else {
                column.renderer = encode;
            }
        });
        return columns;
    };

    var secureColumnMethod = function (className, method, dataIndexes) {
        onClass(className, function (prototype) {
            wrap(prototype, method, function (original) {
                return function () {
                    return secureColumns(original.apply(this, arguments), dataIndexes);
                };
            });
        });
    };

    var encodeResult = function (className, method) {
        onClass(className, function (prototype) {
            wrap(prototype, method, function (original) {
                return function () {
                    return encode(original.apply(this, arguments));
                };
            });
        });
    };

    var encodeRecordFor = function (className, method) {
        onClass(className, function (prototype) {
            wrap(prototype, method, function (original) {
                return encodedRenderer(original);
            });
        });
    };

    // XTemplate whose simple placeholders ({field}, {.}) are encoded; code
    // blocks ({[ ]}), formats ({x:y}) and member functions stay as they are.
    // encodeMembers: member functions whose result is encoded as well.
    var safeTemplate = function (template, encodeMembers) {
        if (!template || typeof template.html !== 'string') {
            return template;
        }
        var members = {};
        Ext.Object.each(template, function (key, value) {
            if (typeof value === 'function' && template.hasOwnProperty(key)) {
                members[key] = value;
            }
        });
        Ext.each(encodeMembers || [], function (name) {
            if (typeof members[name] === 'function') {
                var member = members[name];
                members[name] = function () {
                    return encode(member.apply(this, arguments));
                };
            }
        });
        var html = template.html.replace(/\{(\.|[A-Za-z_][\w.\-]*)\}/g, '{$1:htmlEncode}');
        return new Ext.XTemplate(html, members);
    };

    var secureTemplateMethod = function (className, method, encodeMembers) {
        onClass(className, function (prototype) {
            wrap(prototype, method, function (original) {
                return function () {
                    return safeTemplate(original.apply(this, arguments), encodeMembers);
                };
            });
        });
    };

    var mailLink = function (value) {
        if (typeof value !== 'string') {
            return value;
        }
        return '<a href="mailto:' + encode(value) + '" data-qtip="' + encodeAttribute(value) + '">' + encode(value) + '</a>';
    };

    // Display fields render their value as HTML; show it as text unless
    // the field explicitly allows HTML.
    onClass('Ext.form.field.Display', function (prototype) {
        wrap(prototype, 'getDisplayValue', function (original) {
            return function () {
                var value = original.apply(this, arguments);
                return this.allowHtml === true ? value : encode(value);
            };
        });
    });

    // Grid cells of columns without a renderer (all grids and trees): the
    // prepared cell text is output as text. Columns with a renderer or
    // allowHtml are left to their renderer.
    onClass('Ext.grid.header.Container', function (prototype) {
        wrap(prototype, 'prepareData', function (original) {
            return function () {
                var result = original.apply(this, arguments),
                    headers = this.gridDataColumns || this.getGridColumns();
                if (!result || !headers) {
                    return result;
                }
                Ext.each(headers, function (header) {
                    var value = result[header.id];
                    if (typeof header.renderer !== 'function' && header.allowHtml !== true
                        && typeof value === 'string' && value !== header.emptyCellText) {
                        result[header.id] = encode(value);
                    }
                });
                return result;
            };
        });
    });

    // Generic grids and info panels (Shopware.grid.Panel, Shopware.listing.InfoPanel).
    onClass('Shopware.grid.Panel', function (prototype) {
        wrap(prototype, 'createColumn', function (original) {
            return function (model, field) {
                var column = original.apply(this, arguments);
                if (column && !column.renderer && field && field.type && field.type.type === 'string') {
                    column.renderer = encode;
                }
                return column;
            };
        });
        wrap(prototype, 'associationColumnRenderer', function (original) {
            return function () {
                return encode(original.apply(this, arguments));
            };
        });
    });
    onClass('Shopware.listing.InfoPanel', function (prototype) {
        wrap(prototype, 'createTemplateForField', function (original) {
            return function (model, field) {
                var html = original.apply(this, arguments);
                return typeof html === 'string' && field && field.name
                    ? html.split('{' + field.name + '}').join('{' + field.name + ':htmlEncode}')
                    : html;
            };
        });
    });

    // Customers.
    secureTemplateMethod('Shopware.apps.Customer.view.detail.Window', 'createBillingTemplate');
    secureTemplateMethod('Shopware.apps.Customer.view.detail.Window', 'createShippingTemplate');
    onClass('Shopware.apps.Customer.view.detail.Window', function (prototype) {
        wrap(prototype, 'setWindowTitle', function (original) {
            return function () {
                var me = this;
                if (!me.record || !me.record.get('id') || !me.snippets) {
                    return original.apply(me, arguments);
                }
                me.setTitle(Ext.String.format('[0] [1] [2] ([3])',
                    me.snippets.titleEdit,
                    encode(me.record.get('firstname') || ''),
                    encode(me.record.get('lastname') || ''),
                    encode(me.record.get('number') || ' - ')
                ));
            };
        });
    });
    onClass('Shopware.apps.Customer.view.main.CustomerList', function (prototype) {
        wrap(prototype, 'mailRenderer', function () {
            return mailLink;
        });
    });
    encodeRecordFor('Shopware.apps.Customer.view.address.List', 'nameRenderer');
    encodeRecordFor('Shopware.apps.Customer.view.address.List', 'companyRenderer');
    encodeRecordFor('Shopware.apps.Customer.view.address.List', 'addressRenderer');
    secureColumnMethod('Shopware.apps.Customer.view.customer_stream.Preview', 'getColumns', ['customernumber', 'lastname', 'city']);
    onClass('Shopware.form.field.CustomerSingleSelection', function (prototype) {
        wrap(prototype, 'getComboConfig', function (original) {
            return function () {
                var config = original.apply(this, arguments);
                if (config) {
                    // Only the list (HTML); displayTpl fills the text input.
                    config.tpl = safeTemplate(config.tpl);
                }
                return config;
            };
        });
    });
    secureColumnMethod('Shopware.form.field.CustomerGrid', 'createColumns', ['number', 'firstname', 'lastname', 'customerGroup', 'company']);
    onClass('Shopware.form.field.CustomerGrid', function (prototype) {
        wrap(prototype, 'mailRenderer', function () {
            return mailLink;
        });
    });

    // Orders.
    secureColumnMethod('Shopware.apps.Order.view.list.List', 'getColumns', ['number', 'transactionId']);
    onClass('Shopware.apps.Order.view.list.List', function (prototype) {
        wrap(prototype, 'customerEmailColumn', function () {
            return mailLink;
        });
        wrap(prototype, 'customerColumn', function (original) {
            return function (value, metaData, record) {
                var me = this, name = '', comments = [], billing = record && record.getBilling ? record.getBilling() : null;
                if (!billing || !(billing instanceof Ext.data.Store)) {
                    return original.apply(me, arguments);
                }
                if (billing.first() instanceof Ext.data.Model) {
                    billing = billing.first();
                    name = Ext.String.trim(encode(billing.get('lastName')) + ', ' + encode(billing.get('firstName')));
                    if (billing.get('company') && billing.get('company').length > 0) {
                        name += ' (' + encode(billing.get('company')) + ')';
                    }
                }
                Ext.each([['customerComment', 'customerComment'], ['internalComment', 'internalComment'], ['comment', 'externalComment']], function (pair) {
                    var text = record.get(pair[0]);
                    if (text && text.length > 0) {
                        comments.push('<b>' + encode(me.snippets[pair[1]]) + '</b><br/>' + encode(text));
                    }
                });
                if (comments.length > 0 && metaData) {
                    metaData.tdAttr = 'data-qtip="' + encode(comments.join('<br/><br/>')) + '"';
                    return '<div class="sprite-balloon customer-column-icon"></div><p class="customer-column-text">' + name + '</p>';
                }
                return name;
            };
        });
    });
    secureTemplateMethod('Shopware.apps.Order.view.detail.Overview', 'createAddressTemplate');
    secureTemplateMethod('Shopware.apps.Order.view.detail.Overview', 'createPaymentTemplate');
    onClass('Shopware.apps.Order.view.detail.Overview', function (prototype) {
        wrap(prototype, 'renderCustomerEmail', function () {
            return function (value) {
                return typeof value === 'string' ? '<a href="mailto:' + encode(value) + '">' + encode(value) + '</a>' : value;
            };
        });
    });
    secureColumnMethod('Shopware.apps.Order.view.detail.Position', 'getColumns', ['articleNumber', 'articleName']);
    secureColumnMethod('Shopware.apps.Order.view.list.Position', 'getColumns', ['articleNumber', 'articleName']);
    onClass('Shopware.apps.CanceledOrder.view.tabs.order.Orders', function (prototype) {
        wrap(prototype, 'transactionRenderer', function (original) {
            return function () {
                return encode(original.apply(this, arguments));
            };
        });
        wrap(prototype, 'commentRenderer', function (original) {
            return function () {
                return encode(original.apply(this, arguments));
            };
        });
        wrap(prototype, 'customerRenderer', function (original) {
            return function () {
                return encode(original.apply(this, arguments));
            };
        });
    });

    // Reviews and blog comments.
    encodeResult('Shopware.apps.Vote.view.list.Vote', 'nameRenderer');
    secureTemplateMethod('Shopware.apps.Vote.view.list.extensions.Info', 'createTemplate', ['nameRenderer']);
    secureColumnMethod('Shopware.apps.Index.view.widgets.Rating', 'createColumns', ['productTitle', 'headline']);
    onClass('Shopware.apps.Index.view.widgets.Merchant', function (prototype) {
        wrap(prototype, 'emailColumn', function () {
            // The name (value) is encoded on the server (Controllers/Backend/Widgets.php), the address is not.
            return function (value, cellEl, record) {
                var email = record ? record.get('email') : '';
                return '<a href="mailto:' + encode(email) + '">' + value + '</a>';
            };
        });
    });
    secureColumnMethod('Shopware.apps.Blog.view.blog.detail.comments.Grid', 'getColumns', ['name', 'headline']);
    secureTemplateMethod('Shopware.apps.Blog.view.blog.detail.comments.InfoPanel', 'createInfoPanelTemplate');
    onClass('Shopware.apps.Blog.controller.Comment', function (prototype) {
        wrap(prototype, 'onDeleteSingleBlogComment', function (original) {
            // The confirmation message contains the headline as HTML.
            return function (grid, rowIndex) {
                var store = this.subApplication && this.subApplication.commentStore,
                    record = store ? store.getAt(rowIndex) : null,
                    get = record ? record.get : null;
                if (record) {
                    record.get = function (name) {
                        var result = get.apply(record, arguments);
                        return name === 'headline' ? encode(result) : result;
                    };
                }
                try {
                    return original.apply(this, arguments);
                } finally {
                    if (record) {
                        record.get = get;
                    }
                }
            };
        });
    });

    // Statistics and further lists.
    secureColumnMethod('Shopware.apps.Analytics.view.table.SearchTerms', 'getColumns', ['keyword']);
    secureColumnMethod('Shopware.apps.Analytics.view.table.SearchUrls', 'getColumns', ['referrer']);
    secureColumnMethod('Shopware.apps.Analytics.view.table.Search', 'getColumns', ['searchterm', 'shop']);
    secureColumnMethod('Shopware.apps.Notification.view.notification.Customer', 'getColumns', ['mail', 'name']);
    secureColumnMethod('Shopware.apps.NewsletterManager.view.tabs.Recipients', 'getColumns', ['address.email']);
    secureColumnMethod('Shopware.apps.Voucher.view.code.List', 'getColumns', ['code', 'number', 'firstName', 'lastName']);
    onClass('Shopware.apps.Customer.view.customer_stream.Listing', function (prototype) {
        wrap(prototype, 'nameRenderer', function (original) {
            // Cell with encoded values; the tooltip (data-qtip) with values
            // encoded twice, built by the same original renderer.
            return function (value, metaData, record) {
                var args = Array.prototype.slice.call(arguments), tooltip = {}, result;
                args[0] = encode(value);
                args[2] = encodedRecord(record);
                result = original.apply(this, args);
                if (metaData && record && typeof record.get === 'function') {
                    args[0] = encodeAttribute(value);
                    args[1] = tooltip;
                    args[2] = encodedRecord(encodedRecord(record));
                    original.apply(this, args);
                    metaData.tdAttr = tooltip.tdAttr;
                }
                return result;
            };
        });
    });

    // Backend users.
    encodeRecordFor('Shopware.apps.UserManager.view.user.List', 'nameColumn');
    onClass('Shopware.apps.UserManager.view.user.List', function (prototype) {
        wrap(prototype, 'emailColumn', function () {
            return function (value) {
                return typeof value === 'string' ? '<a href="mailto:' + encode(value) + '">' + encode(value) + '</a>' : value;
            };
        });
    });
}());
</script>
{/literal}
{/if}
{/block}
