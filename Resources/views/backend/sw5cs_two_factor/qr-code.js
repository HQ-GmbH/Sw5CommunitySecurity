// This file is part of Sw5CommunitySecurity. (c) HQ GmbH, licensed under the GNU General Public License v3.0.
//
// Draws the set-up QR code of the two-factor page as inline SVG. The code is
// built in the browser with the bundled library "qrcode-generator" (MIT, see
// lib/qrcode-generator/LICENSE); the secret is never sent anywhere. Without
// JavaScript the page still shows the key and the set-up address.
(function () {
    'use strict';

    var SVG_NS = 'http://www.w3.org/2000/svg';
    var QUIET_ZONE = 4;

    function draw(container) {
        var uri = container.getAttribute('data-uri');
        if (!uri || typeof window.qrcode !== 'function') {
            return;
        }

        var qr = window.qrcode(0, 'M');
        qr.addData(uri, 'Byte');
        qr.make();

        var count = qr.getModuleCount();
        var size = count + 2 * QUIET_ZONE;
        var path = '';
        for (var row = 0; row < count; row++) {
            for (var col = 0; col < count; col++) {
                if (qr.isDark(row, col)) {
                    path += 'M' + (col + QUIET_ZONE) + ' ' + (row + QUIET_ZONE) + 'h1v1h-1z';
                }
            }
        }

        var svg = document.createElementNS(SVG_NS, 'svg');
        svg.setAttribute('viewBox', '0 0 ' + size + ' ' + size);
        svg.setAttribute('width', String(size * 5));
        svg.setAttribute('height', String(size * 5));
        svg.setAttribute('shape-rendering', 'crispEdges');
        svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', container.getAttribute('data-label') || 'QR code');

        var background = document.createElementNS(SVG_NS, 'rect');
        background.setAttribute('width', String(size));
        background.setAttribute('height', String(size));
        background.setAttribute('fill', '#fff');
        svg.appendChild(background);

        var modules = document.createElementNS(SVG_NS, 'path');
        modules.setAttribute('d', path);
        modules.setAttribute('fill', '#000');
        svg.appendChild(modules);

        container.appendChild(svg);
        container.hidden = false;
    }

    var containers = document.querySelectorAll('[data-sw5cs-qr]');
    for (var i = 0; i < containers.length; i++) {
        try {
            draw(containers[i]);
        } catch (e) {
            // Keep the manual key as fallback.
        }
    }
}());
