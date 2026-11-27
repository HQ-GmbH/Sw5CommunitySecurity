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

namespace Sw5CommunitySecurity\Components\ApiAccess;

/**
 * Restricts the REST API (module "api") to configured client addresses.
 *
 * Entries are single IPv4/IPv6 addresses or CIDR ranges, separated by line
 * breaks, commas, semicolons or spaces. Without entries the API is not
 * restricted. As soon as anything is configured, only valid entries allow
 * access: a list with typing errors only blocks the API instead of silently
 * opening it. IPv4-mapped IPv6 addresses (::ffff:a.b.c.d), as dual-stack
 * servers report IPv4 clients, are compared as IPv4, in entries and client
 * addresses alike.
 *
 * Deliberately free of Shopware dependencies to keep it unit-testable.
 */
final class ApiAccessPolicy
{
    private const IPV4_MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    /**
     * @var list<array{bytes: string, prefix: int, entry: string}>
     */
    private array $ranges = [];

    /**
     * @var list<string>
     */
    private array $invalidEntries = [];

    private bool $restricted;

    public function __construct(string $entries)
    {
        $parts = preg_split('/[\s,;]+/', trim($entries), -1, \PREG_SPLIT_NO_EMPTY);
        $parts = \is_array($parts) ? $parts : [];
        $this->restricted = $parts !== [];

        foreach (array_values(array_unique($parts)) as $entry) {
            $range = self::parse($entry);
            if ($range === null) {
                $this->invalidEntries[] = $entry;

                continue;
            }
            $this->ranges[] = $range + ['entry' => $entry];
        }
    }

    public function isRestricted(): bool
    {
        return $this->restricted;
    }

    /**
     * @return list<string>
     */
    public function invalidEntries(): array
    {
        return $this->invalidEntries;
    }

    public function validEntryCount(): int
    {
        return \count($this->ranges);
    }

    /**
     * Valid entries that admit nearly every client: IPv4 ranges shorter than
     * /8 and IPv6 ranges shorter than /16 that overlap the global unicast
     * space 2000::/3 (same limits as the trusted-proxy check).
     *
     * @return list<string>
     */
    public function wideEntries(): array
    {
        $wide = [];
        foreach ($this->ranges as $range) {
            if (\strlen($range['bytes']) === 4 ? $range['prefix'] < 8 : self::isWideIpv6($range['bytes'], $range['prefix'])) {
                $wide[] = $range['entry'];
            }
        }

        return $wide;
    }

    public function allows(?string $clientAddress): bool
    {
        if (!$this->restricted) {
            return true;
        }

        if ($clientAddress === null || $clientAddress === '') {
            return false;
        }

        $bytes = @inet_pton($clientAddress);
        if (!\is_string($bytes)) {
            return false;
        }
        $bytes = self::unmapIpv4($bytes, 128)[0];

        foreach ($this->ranges as $range) {
            if (\strlen($range['bytes']) === \strlen($bytes) && self::matches($bytes, $range['bytes'], $range['prefix'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{bytes: string, prefix: int}|null
     */
    private static function parse(string $entry): ?array
    {
        $parts = explode('/', $entry, 2);
        $address = $parts[0];
        $prefix = null;
        if (isset($parts[1])) {
            if ($parts[1] === '' || !ctype_digit($parts[1])) {
                return null;
            }
            $prefix = (int) $parts[1];
        }

        if (filter_var($address, \FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $bytes = inet_pton($address);
        if (!\is_string($bytes)) {
            return null;
        }

        $bits = \strlen($bytes) * 8;
        $prefix ??= $bits;
        if ($prefix > $bits) {
            return null;
        }

        [$bytes, $prefix] = self::unmapIpv4($bytes, $prefix);

        return ['bytes' => $bytes, 'prefix' => $prefix];
    }

    /**
     * Turns an IPv4-mapped IPv6 address or range (at least /96) into IPv4.
     *
     * @return array{string, int}
     */
    private static function unmapIpv4(string $bytes, int $prefix): array
    {
        if (\strlen($bytes) === 16 && $prefix >= 96 && strncmp($bytes, self::IPV4_MAPPED_PREFIX, 12) === 0) {
            return [(string) substr($bytes, 12), $prefix - 96];
        }

        return [$bytes, $prefix];
    }

    private static function isWideIpv6(string $bytes, int $prefix): bool
    {
        if ($prefix >= 16) {
            return false;
        }

        // Compare the top three bits with 001 (2000::/3) as far as the prefix reaches.
        $compared = min($prefix, 3);

        return $compared === 0 || (\ord($bytes[0]) >> 5 >> (3 - $compared)) === (0b001 >> (3 - $compared));
    }

    private static function matches(string $address, string $network, int $prefix): bool
    {
        $fullBytes = intdiv($prefix, 8);
        if (strncmp($address, $network, $fullBytes) !== 0) {
            return false;
        }

        $remainingBits = $prefix % 8;
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (\ord($address[$fullBytes]) & $mask) === (\ord($network[$fullBytes]) & $mask);
    }
}
