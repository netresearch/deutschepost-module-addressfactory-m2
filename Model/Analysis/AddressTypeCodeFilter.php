<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model\Analysis;

use PostDirekt\Sdk\AddressfactoryDirect\Api\Data\RecordInterface;

class AddressTypeCodeFilter
{
    public function filterCodes(RecordInterface $record): array
    {
        $patterns = [];
        if ($record->getPostOffice() || $record->getParcelStation()) {
            $patterns = ['^PDC', '^FNC'];
        }

        if ($record->getPostalBox()) {
            $patterns = ['^PDC'];
        }

        $codes = $record->getStatusCodes();
        // no pattern - nothing to filter
        if (empty($patterns)) {
            return $codes;
        }

        return  array_filter($codes, static function ($key) use ($patterns) {
            $pattern = implode('|', $patterns);
            return !preg_match("/$pattern/", $key);
        });
    }
}
