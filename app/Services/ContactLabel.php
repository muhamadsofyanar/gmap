<?php
namespace App\Services;

final class ContactLabel
{
    /** A punctuation-only profile name must never hide the known contact number. */
    public static function name(?string $business,?string $push,?string $phone): string
    {
        foreach ([$business,$push] as $value) {
            $value=trim($value??'');
            if ($value!=='' && preg_match('/[\p{L}\p{N}]/u',$value)) return $value;
        }
        return trim($phone??'') ?: 'Penerima belum diketahui';
    }
}
